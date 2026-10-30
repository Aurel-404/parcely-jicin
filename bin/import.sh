#!/usr/bin/env bash
# Imports the downloaded RÚIAN VFR files from data/raw into PostGIS:
#   1. ogr2ogr loads the raw layers into schema "staging" (as they are, curves converted to line segments),
#   2. db/transform.sql builds the application tables from them in one transaction and records vfr_date in meta.
#
# Runs in the gdal container, after the code lists were loaded (bin/import-codelists.php):
#
#   docker compose run --rm gdal bin/import.sh [--date=YYYYMMDD] [--obec=KOD ...]
#
#   --date=YYYYMMDD  data set to import; default = the newest date in the names of the OB files
#   --obec=KOD       import only this municipality (repeatable), for a quick trial run
#
# Database connection: libpq environment variables PGHOST, PGDATABASE, PGUSER, PGPASSWORD (set by compose.yaml).
set -euo pipefail

cd "$(dirname "$0")/.."
# Hide NOTICEs such as "table … does not exist, skipping" from DROP … IF EXISTS.
export PGOPTIONS='-c client_min_messages=warning'

readonly RAW=data/raw
readonly CSV=data/obce_okres_jicin.csv
# Official number of municipalities in the district (ČSÚ); bin/list-obce.php checks the CSV against it too.
readonly EXPECTED_MUNICIPALITIES=111
readonly LAYERS=(Parcely KatastralniUzemi Obce StavebniObjekty)

fail() { echo "Error: $*" >&2; exit 1; }
usage() { sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; }
elapsed() { printf '%.1f s' "$(( ${EPOCHREALTIME/./} - $1 ))e-6"; }
sql() { psql -X -q -v ON_ERROR_STOP=1 "$@"; }

date=""
only=()
for arg in "$@"; do
    case "$arg" in
        --date=*) date="${arg#--date=}" ;;
        --obec=*) only+=("${arg#--obec=}") ;;
        -h | --help) usage; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; usage >&2; exit 2 ;;
    esac
done

started=${EPOCHREALTIME/./}

# Municipalities of the district: code => name, from the committed CSV (kod;nazev, names may be quoted).
declare -A names=()
while IFS=';' read -r code name; do
    [[ $code == kod ]] && continue
    name=${name%$'\r'}
    names[$code]=${name//\"/}
done < "$CSV"
(( ${#names[@]} == EXPECTED_MUNICIPALITIES )) \
    || fail "$CSV lists ${#names[@]} municipalities, expected $EXPECTED_MUNICIPALITIES."

if (( ${#only[@]} > 0 )); then
    for code in "${only[@]}"; do
        [[ -v names[$code] ]] || fail "Municipality $code is not in $CSV."
    done
    codes=("${only[@]}")
else
    mapfile -t codes < <(printf '%s\n' "${!names[@]}" | sort -n)
fi

# Data set date: from --date, otherwise the newest date of the OB files. All files must have that date.
if [[ -z $date ]]; then
    date=$(find "$RAW" -maxdepth 1 -name '*_OB_*_UKSH.xml.zip' -printf '%f\n' | cut -c1-8 | sort -u | tail -n 1)
    [[ -n $date ]] || fail "No OB files in $RAW; run bin/download.php first."
fi
[[ $date =~ ^[0-9]{8}$ ]] || { echo "Error: --date must be YYYYMMDD, got \"$date\"." >&2; exit 2; }

missing=()
for code in "${codes[@]}"; do
    [[ -s "$RAW/${date}_OB_${code}_UKSH.xml.zip" ]] || missing+=("$code")
done
(( ${#missing[@]} == 0 )) || fail "Data set $date is incomplete: ${#missing[@]} of ${#codes[@]} OB files missing" \
    "(${missing[*]:0:5}…). Run: docker compose exec php php bin/download.php --date=$date"
state_file="$RAW/${date}_ST_UKSH.xml.zip"
[[ -s $state_file ]] || fail "$state_file is missing; run bin/download.php --date=$date"

[[ $(sql -tA -c "SELECT to_regclass('public.cis_druh_cislovani') IS NOT NULL") == t ]] \
    || fail "Code lists are not loaded. Run: docker compose exec php php bin/import-codelists.php"

echo "VFR data set $date: ${#codes[@]} municipalities"

# 1. Staging: raw layers as they are. Some parcel boundaries contain circular arcs (gml:ArcString, gml:Circle);
#    CONVERT_TO_LINEAR approximates them by line segments, PROMOTE_TO_MULTI gives one type per column.
sql -c 'DROP SCHEMA IF EXISTS staging CASCADE; CREATE SCHEMA staging;'
load() {
    local mode=$1 file=$2
    shift 2
    local options=()
    [[ $mode == -overwrite ]] && options=(-lco SPATIAL_INDEX=NONE -lco UNLOGGED=YES)
    ogr2ogr -f PostgreSQL PG:active_schema=staging "/vsizip/$file" "$@" "$mode" "${options[@]}" \
        -nlt CONVERT_TO_LINEAR -nlt PROMOTE_TO_MULTI --config PG_USE_COPY YES
}

for i in "${!codes[@]}"; do
    code=${codes[$i]}
    file_started=${EPOCHREALTIME/./}
    load "$( (( i == 0 )) && echo -overwrite || echo -append )" "$RAW/${date}_OB_${code}_UKSH.xml.zip" "${LAYERS[@]}"
    # Name last: printf pads by bytes, so names with diacritics would break the columns.
    printf '[%3d/%d] OB_%s %6s  %s\n' $(( i + 1 )) "${#codes[@]}" "$code" "$(elapsed "$file_started")" "${names[$code]}"
done

file_started=${EPOCHREALTIME/./}
load -overwrite "$state_file" Okresy
echo "ST_UKSH Okresy $(elapsed "$file_started")"

# 2. Transform into the application tables (one transaction: the old data stays until COMMIT).
file_started=${EPOCHREALTIME/./}
sql -1 -v vfr_date="$date" -f db/transform.sql
echo "Transform $(elapsed "$file_started")"

sql -c "
SELECT (SELECT count(*) FROM okres) AS okres, (SELECT count(*) FROM obec) AS obec, (SELECT count(*) FROM ku) AS ku,
       (SELECT count(*) FROM parcel) AS parcel, (SELECT count(*) FROM building) AS building,
       (SELECT value FROM meta WHERE key = 'vfr_date') AS vfr_date;"

echo "Import done in $(elapsed "$started")."

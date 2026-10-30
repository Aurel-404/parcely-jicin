# GDAL for reading RÚIAN VFR (GML driver + RÚIAN .gfs templates) plus psql for running db/transform.sql.
# Ubuntu 26.04 ships postgresql-client-18, the same major version as the database server.
FROM ghcr.io/osgeo/gdal:ubuntu-small-3.13.3

RUN apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-18 \
    && rm -rf /var/lib/apt/lists/*

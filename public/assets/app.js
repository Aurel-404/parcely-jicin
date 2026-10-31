// Map of all parcels in the Jičín district. MapLibre GL JS 5 comes from the UMD bundle (global maplibregl);
// tiles and JSON come from the PHP backend on the same origin. No build step.

/* global maplibregl */

/**
 * Label glyphs served from public/fonts: only ranges 0-255 and 256-511 (all characters of the names and parcel
 * numbers are below U+0200, the highest is ž). Absolute, because MapLibre may resolve it outside the page.
 */
const GLYPHS = `${window.location.origin}/fonts/{fontstack}/{range}.pbf`;
const FONT = ['Noto Sans Regular'];

/** Parcel numbers from this zoom; names of cadastral areas up to KU_LABEL_MAX_ZOOM. */
const PARCEL_LABEL_MIN_ZOOM = 17;
const KU_LABEL_MAX_ZOOM = 14;
const SEARCH_DEBOUNCE_MS = 250;
const MAX_ZOOM = 20;

/** Base maps; tile URLs and the y-before-x order of the ČÚZK ArcGIS cache verified with a tile over Jičín. */
const BASEMAPS = {
  osm: {
    source: {
      type: 'raster',
      tiles: ['https://tile.openstreetmap.org/{z}/{x}/{y}.png'],
      tileSize: 256,
      maxzoom: 19,
      attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    },
    parcelColor: '#c2410c',
    boundaryColor: '#343a40',
  },
  orto: {
    source: {
      type: 'raster',
      tiles: ['https://ags.cuzk.gov.cz/arcgis1/rest/services/ORTOFOTO_WM/MapServer/tile/{z}/{y}/{x}'],
      tileSize: 256,
      minzoom: 6,
      maxzoom: 20,
      attribution: 'Ortofoto © <a href="https://www.cuzk.cz" target="_blank" rel="noopener">ČÚZK</a>',
    },
    // Light colours: dark lines disappear on the photo.
    parcelColor: '#ffd43b',
    boundaryColor: '#f8f9fa',
  },
};

/**
 * Fill colour by druh pozemku (codes of DruhyPozemku in ST_UCIS; the legend takes the names from the API).
 * Hues of a palette validated for colour-blind readers. Nine kinds cannot all be told apart by colour on a map,
 * so related kinds share a colour, ostatní plocha (roads, paths) keeps no fill, every parcel has an outline,
 * and the legend can isolate one kind.
 */
const LAND_TYPE_COLORS = [
  { codes: [2], color: '#eda100' }, // orná půda
  { codes: [7, 8], color: '#1baf7a' }, // trvalý travní porost (two codes with the same name)
  { codes: [10], color: '#008300' }, // lesní pozemek
  { codes: [11], color: '#2a78d6' }, // vodní plocha
  { codes: [13], color: '#e34948' }, // zastavěná plocha a nádvoří
  { codes: [3, 4, 5, 6], color: '#e87ba4' }, // chmelnice, vinice, zahrada, ovocný sad
];
const COLORED_CODES = LAND_TYPE_COLORS.flatMap((group) => group.codes);

/** The same format ParcelLabel::parse() accepts; checked here only to answer without a round trip. */
const PARCEL_NUMBER = /^\s*(st\.?\s*)?\d{1,9}\s*(\/\s*\d{1,9})?\s*$/i;

const STORAGE_KEY = 'parcely.basemap';
const NUMBER = new Intl.NumberFormat('cs-CZ');

const $ = (selector) => document.querySelector(selector);

class ApiError extends Error {
  constructor(status, message) {
    super(message);
    this.status = status;
  }
}

async function getJson(url, signal) {
  const response = await fetch(url, { signal, headers: { Accept: 'application/json' } });
  if (!response.ok) {
    const body = await response.json().catch(() => null);
    throw new ApiError(response.status, body?.error ?? response.statusText);
  }
  // Not caught: an abort while reading the body must reach the caller as AbortError.
  return response.json();
}

/** Creates an element; children are nodes or strings (always inserted as text, never as HTML). */
function el(tag, attributes = {}, ...children) {
  const element = document.createElement(tag);
  for (const [name, value] of Object.entries(attributes)) {
    element.setAttribute(name, value);
  }
  element.append(...children.filter((child) => child !== null && child !== undefined));
  return element;
}

function debounce(fn, ms) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), ms);
  };
}

const formatArea = (m2) => `${NUMBER.format(m2)} m²`;

/** "2026-09-30" → "30. 9. 2026" */
function formatDate(isoDate) {
  const [year, month, day] = isoDate.split('-').map(Number);
  return `${day}. ${month}. ${year}`;
}

/** "č. p. 540 – rodinný dům", "budova bez č.p. i č.ev. – garáž" */
function buildingText(building) {
  const name = building.oznaceni ?? building.typ?.nazev ?? 'stavba';
  return building.zpusob_vyuziti ? `${name} – ${building.zpusob_vyuziti.nazev}` : name;
}

/** Parcel id from "#parcel=1755256604"; at most 15 digits, so it stays exact as a JavaScript number. */
function parcelIdFromHash() {
  const id = new URLSearchParams(window.location.hash.slice(1)).get('parcel');
  return id !== null && /^[1-9]\d{0,14}$/.test(id) ? Number(id) : null;
}

/** A square around the district, 2.2× its larger side: room to look around, but Praha stays out of reach. */
function maxBoundsAround([west, south, east, north]) {
  const sw = maplibregl.MercatorCoordinate.fromLngLat([west, south]);
  const ne = maplibregl.MercatorCoordinate.fromLngLat([east, north]);
  const half = 1.1 * Math.max(ne.x - sw.x, sw.y - ne.y);
  const centerX = (sw.x + ne.x) / 2;
  const centerY = (sw.y + ne.y) / 2;
  const min = new maplibregl.MercatorCoordinate(centerX - half, centerY + half).toLngLat();
  const max = new maplibregl.MercatorCoordinate(centerX + half, centerY - half).toLngLat();
  return [[min.lng, min.lat], [max.lng, max.lat]];
}

/** How parcels look: base map, colours by druh pozemku on/off, one isolated group (list of codes) or none. */
const look = { basemap: 'osm', colors: true, isolated: null };

const SELECTED = ['boolean', ['feature-state', 'selected'], false];
const HOVER = ['boolean', ['feature-state', 'hover'], false];
const LAND_TYPE = ['get', 'druh_pozemku_kod'];

function fillColor() {
  const accent = BASEMAPS[look.basemap].parcelColor;
  if (!look.colors) {
    return accent;
  }
  return ['match', LAND_TYPE, ...LAND_TYPE_COLORS.flatMap((group) => [group.codes, group.color]), accent];
}

function fillOpacity() {
  // Colours are a little weaker on the photo, which is busy on its own.
  const colored = look.basemap === 'orto' ? 0.35 : 0.45;
  let base = look.colors ? ['match', LAND_TYPE, COLORED_CODES, colored, 0.05] : 0.05;
  if (look.isolated !== null) {
    base = ['match', LAND_TYPE, look.isolated, 0.6, 0.03];
  }
  return ['case', SELECTED, 0.7, HOVER, look.colors ? 0.65 : 0.22, base];
}

/** Applies `look` to a running map (base map switch, legend). */
function applyLook(map) {
  const { parcelColor, boundaryColor } = BASEMAPS[look.basemap];
  for (const name of Object.keys(BASEMAPS)) {
    map.setLayoutProperty(name, 'visibility', name === look.basemap ? 'visible' : 'none');
  }
  map.setPaintProperty('parcels-fill', 'fill-color', fillColor());
  map.setPaintProperty('parcels-fill', 'fill-opacity', fillOpacity());
  map.setPaintProperty('parcels-line', 'line-color', parcelColor);
  map.setPaintProperty('ku-line', 'line-color', boundaryColor);
  map.setPaintProperty('okres-line', 'line-color', boundaryColor);
}

function whenLoaded(map, fn) {
  if (map.isStyleLoaded()) {
    fn();
  } else {
    map.once('load', fn);
  }
}

function buildStyle(tileJson) {
  const { parcelColor, boundaryColor } = BASEMAPS[look.basemap];
  const ku = ['==', ['get', 'type'], 'ku'];

  return {
    version: 8,
    glyphs: GLYPHS,
    sources: {
      osm: BASEMAPS.osm.source,
      orto: BASEMAPS.orto.source,
      parcels: {
        type: 'vector',
        // Absolute (tiles are fetched in a web worker), but not through new URL(): it would encode {z} as %7Bz%7D.
        tiles: tileJson.tiles.map((url) => (url.startsWith('/') ? window.location.origin + url : url)),
        minzoom: tileJson.minzoom,
        maxzoom: tileJson.maxzoom,
        bounds: tileJson.bounds,
        attribution: tileJson.attribution,
      },
      boundaries: { type: 'geojson', data: '/api/boundaries.geojson' },
    },
    layers: [
      { id: 'osm', type: 'raster', source: 'osm' },
      { id: 'orto', type: 'raster', source: 'orto', layout: { visibility: 'none' } },
      {
        id: 'parcels-fill',
        type: 'fill',
        source: 'parcels',
        'source-layer': 'parcels',
        paint: { 'fill-color': fillColor(), 'fill-opacity': fillOpacity() },
      },
      {
        id: 'parcels-line',
        type: 'line',
        source: 'parcels',
        'source-layer': 'parcels',
        paint: {
          'line-color': parcelColor,
          'line-width': ['interpolate', ['linear'], ['zoom'],
            13, ['case', SELECTED, 2, HOVER, 1.5, 0.3],
            16, ['case', SELECTED, 3, HOVER, 2, 0.8],
            19, ['case', SELECTED, 4, HOVER, 3, 1.6]],
          'line-opacity': ['interpolate', ['linear'], ['zoom'], 13, 0.6, 15, 0.9],
        },
      },
      {
        id: 'ku-line',
        type: 'line',
        source: 'boundaries',
        filter: ku,
        paint: { 'line-color': boundaryColor, 'line-width': 1.2, 'line-dasharray': [3, 2] },
      },
      {
        id: 'okres-line',
        type: 'line',
        source: 'boundaries',
        filter: ['==', ['get', 'type'], 'okres'],
        paint: { 'line-color': boundaryColor, 'line-width': 3.5, 'line-opacity': 0.85 },
      },
      {
        id: 'parcels-label',
        type: 'symbol',
        source: 'parcels',
        'source-layer': 'parcels',
        minzoom: PARCEL_LABEL_MIN_ZOOM,
        layout: { 'text-field': ['get', 'label'], 'text-font': FONT, 'text-size': 11, 'text-padding': 2 },
        paint: { 'text-color': '#212529', 'text-halo-color': 'rgb(255 255 255 / 90%)', 'text-halo-width': 1.2 },
      },
      {
        id: 'ku-label',
        type: 'symbol',
        source: 'boundaries',
        filter: ['==', ['get', 'type'], 'ku_label'],
        maxzoom: KU_LABEL_MAX_ZOOM,
        layout: { 'text-field': ['get', 'nazev'], 'text-font': FONT, 'text-size': 12, 'text-max-width': 8 },
        paint: { 'text-color': '#212529', 'text-halo-color': 'rgb(255 255 255 / 90%)', 'text-halo-width': 1.5 },
      },
    ],
  };
}

function showFatal(message) {
  const box = $('#fatal');
  box.textContent = message;
  box.hidden = false;
}

async function main() {
  let tileJson;
  try {
    tileJson = await getJson('/tiles/parcels.json');
  } catch (error) {
    showFatal(error.status === 503
      ? 'Data zatím nejsou naimportovaná. Postup je v README (bin/download.php, bin/import.sh).'
      : 'Mapu se nepodařilo načíst. Běží backend (docker compose up)?');
    return;
  }

  const map = new maplibregl.Map({
    container: 'map',
    style: buildStyle(tileJson),
    bounds: tileJson.bounds,
    fitBoundsOptions: { padding: 24 },
    maxBounds: maxBoundsAround(tileJson.bounds),
    maxZoom: MAX_ZOOM,
    attributionControl: { compact: true },
  });
  map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'bottom-right');
  map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');

  const panel = createPanel(map);
  setUpBasemaps(map);
  const updateZoomDependent = setUpZoomDependent(map, tileJson.minzoom);
  setUpLegend(map, updateZoomDependent);
  setUpSearch(panel);

  map.on('load', () => {
    setUpParcelInteraction(map, panel);
    window.addEventListener('hashchange', () => panel.route());
    panel.route();
  });
}

/** Detail panel, driven by the URL hash (#parcel=<id>): shareable link, Back button works. */
function createPanel(map) {
  const panelElement = $('#panel');
  const content = $('#panel-content');
  const mobile = window.matchMedia('(max-width: 767px)');
  let selectedId = null;
  let request = null;
  // How the next route() moves the map; set by open(), reset to "fit" for links and the Back button.
  let next = { fit: true, point: null };

  const featureOf = (id) => ({ source: 'parcels', sourceLayer: 'parcels', id });

  function select(id) {
    if (selectedId !== null) {
      map.setFeatureState(featureOf(selectedId), { selected: false });
    }
    selectedId = id;
    if (id !== null) {
      map.setFeatureState(featureOf(id), { selected: true });
    }
  }

  function title(text) {
    return el('h2', { id: 'panel-title', tabindex: '-1' }, text);
  }

  function renderSkeleton(id) {
    content.replaceChildren(el('div', { class: 'skeleton', 'aria-busy': 'true' },
      el('h2', { id: 'panel-title', class: 'visually-hidden' }, `Načítám parcelu ${id}`),
      el('span', { class: 'skeleton-title' }), el('span'), el('span'), el('span'), el('span')));
  }

  function renderError(message) {
    content.replaceChildren(title('Detail parcely'), el('p', { class: 'panel-error' }, message));
  }

  function renderDetail(parcel) {
    const row = (term, ...value) => [el('dt', {}, term), el('dd', {}, ...value)];
    const buildings = parcel.stavby.length > 0
      ? el('ul', { class: 'buildings' }, ...parcel.stavby.map((building) => el('li', {}, buildingText(building))))
      : 'žádná';
    const link = (href, text, className = 'button') => el('a', { class: className, href, target: '_blank', rel: 'noopener' }, text);

    content.replaceChildren(
      el('header', { class: 'panel-header' },
        title(parcel.label),
        el('p', { class: 'panel-subtitle' }, `k. ú. ${parcel.ku.nazev}`)),
      el('dl', { class: 'facts' },
        ...row('Katastrální území', `${parcel.ku.nazev} (${parcel.ku.kod})`),
        ...row('Obec', parcel.obec.nazev),
        ...row('Výměra', formatArea(parcel.vymera_m2)),
        ...row('Druh pozemku', parcel.druh_pozemku?.nazev ?? '–'),
        ...row('Způsob využití', parcel.zpusob_vyuziti?.nazev ?? '–'),
        ...row('Stavby', buildings)),
      el('div', { class: 'links' },
        link(parcel.links.nahlizeni_kn, 'Vlastníci v Nahlížení do KN'),
        link(parcel.links.vdp, 'Detail v RÚIAN (VDP)', 'button button-secondary')),
      el('p', { class: 'panel-footer' }, `Údaje k ${formatDate(parcel.data_k)} · RÚIAN © ČÚZK`),
    );
  }

  /** Room for the panel, so the fitted parcel is not hidden behind it. */
  function padding() {
    const margin = 32;
    return mobile.matches
      ? { top: 80, right: margin, bottom: panelElement.offsetHeight + margin, left: margin }
      : { top: 80, right: panelElement.offsetWidth + 2 * margin, bottom: margin, left: margin };
  }

  async function show(id, { fit, point }) {
    request?.abort();
    const { signal } = (request = new AbortController());
    select(id);
    renderSkeleton(id);
    panelElement.hidden = false;

    try {
      const parcel = await getJson(`/api/parcels/${id}`, signal);
      if (signal.aborted) {
        return;
      }
      renderDetail(parcel);
      if (fit) {
        const [west, south, east, north] = parcel.bbox;
        map.fitBounds([[west, south], [east, north]], { padding: padding(), maxZoom: 18, duration: 800 });
      } else if (point !== null && mobile.matches) {
        // The bottom sheet covers the lower half of a phone: keep the tapped place visible above it.
        map.easeTo({ center: point, padding: padding(), duration: 300 });
      }
    } catch (error) {
      if (error.name === 'AbortError') {
        return;
      }
      renderError(error.status === 404
        ? `Parcela ${id} v okrese Jičín neexistuje.`
        : 'Detail parcely se nepodařilo načíst. Zkuste to znovu.');
    }
    $('#panel-title')?.focus({ preventScroll: true });
  }

  function hide() {
    request?.abort();
    select(null);
    if (!panelElement.hidden) {
      panelElement.hidden = true;
      map.getCanvas().focus();
    }
  }

  const panel = {
    /**
     * Opens a parcel. fit = zoom the map to it (search, link); after a click on the map it stays where it is,
     * point = the clicked place.
     */
    open(id, { fit = true, point = null } = {}) {
      if (parcelIdFromHash() === id && !panelElement.hidden) {
        return;
      }
      next = { fit, point };
      window.location.hash = `parcel=${id}`;
    },
    close() {
      // pushState: a URL without "#", and Back reopens the parcel.
      window.history.pushState(null, '', window.location.pathname + window.location.search);
      panel.route();
    },
    route() {
      const id = parcelIdFromHash();
      const how = next;
      next = { fit: true, point: null };
      if (id === null) {
        hide();
      } else {
        show(id, how);
      }
    },
    isOpen: () => !panelElement.hidden,
  };

  panelElement.querySelector('.panel-close').addEventListener('click', () => panel.close());
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && panel.isOpen()) {
      panel.close();
    }
  });

  return panel;
}

function setUpParcelInteraction(map, panel) {
  let hoveredId = null;
  const setHover = (id) => {
    if (hoveredId !== null) {
      map.setFeatureState({ source: 'parcels', sourceLayer: 'parcels', id: hoveredId }, { hover: false });
    }
    hoveredId = id;
    if (id !== null) {
      map.setFeatureState({ source: 'parcels', sourceLayer: 'parcels', id }, { hover: true });
    }
  };

  map.on('mousemove', 'parcels-fill', (event) => {
    const id = event.features[0]?.id ?? null;
    if (id !== hoveredId) {
      setHover(id);
    }
    map.getCanvas().style.cursor = 'pointer';
  });
  map.on('mouseleave', 'parcels-fill', () => {
    setHover(null);
    map.getCanvas().style.cursor = '';
  });
  map.on('click', 'parcels-fill', (event) => {
    const id = event.features[0]?.id;
    if (id !== undefined) {
      panel.open(id, { fit: false, point: event.lngLat });
    }
  });
}

function setUpBasemaps(map) {
  const buttons = [...document.querySelectorAll('#basemaps button')];

  function use(name) {
    for (const button of buttons) {
      button.setAttribute('aria-pressed', String(button.dataset.basemap === name));
    }
    look.basemap = name;
    whenLoaded(map, () => applyLook(map));
    try {
      window.localStorage.setItem(STORAGE_KEY, name);
    } catch {
      // Storage blocked (private mode): the choice is just not remembered.
    }
  }

  for (const button of buttons) {
    button.addEventListener('click', () => use(button.dataset.basemap));
  }

  let saved = null;
  try {
    saved = window.localStorage.getItem(STORAGE_KEY);
  } catch {
    // See above.
  }
  if (saved !== null && saved in BASEMAPS && saved !== 'osm') {
    use(saved);
  }
}

/** Below the minimum zoom of the parcels a hint, from it on the legend. */
function setUpZoomDependent(map, minZoom) {
  const hint = $('#zoom-hint');
  const legend = $('#legend');
  const update = () => {
    const parcelsVisible = map.getZoom() >= minZoom;
    hint.hidden = parcelsVisible;
    legend.hidden = !parcelsVisible || legend.dataset.ready !== 'true';
  };
  map.on('zoom', update);
  update();
  return update;
}

/**
 * Legend of the colours by druh pozemku: one row per colour (kinds sharing a colour are listed together),
 * names from the code list via the API. A click isolates one row, the checkbox turns the colours off.
 */
async function setUpLegend(map, onReady) {
  const legend = $('#legend');
  const toggle = $('#legend-colors');
  legend.open = !window.matchMedia('(max-width: 767px)').matches;

  let types;
  try {
    ({ results: types } = await getJson('/api/druhy-pozemku'));
  } catch {
    return; // The map works without a legend.
  }

  const rows = [];
  for (const type of types) {
    const group = LAND_TYPE_COLORS.find((candidate) => candidate.codes.includes(type.kod));
    let row = group === undefined ? undefined : rows.find((candidate) => candidate.group === group);
    if (row === undefined) {
      row = { group, codes: group?.codes ?? [type.kod], names: [], count: 0 };
      rows.push(row);
    }
    if (!row.names.includes(type.nazev)) {
      row.names.push(type.nazev);
    }
    row.count += type.pocet;
  }
  rows.sort((a, b) => b.count - a.count);

  const buttons = rows.map((row) => {
    const swatch = el('span', { class: row.group ? 'legend-swatch' : 'legend-swatch legend-swatch-none', 'aria-hidden': 'true' });
    if (row.group) {
      swatch.style.background = row.group.color;
    }
    const button = el('button', { type: 'button', 'aria-pressed': 'false', title: 'Zvýraznit jen tento druh' },
      swatch, el('span', { class: 'legend-name' }, row.names.join(', ')), el('span', { class: 'legend-count' }, NUMBER.format(row.count)));
    button.addEventListener('click', () => {
      const wasActive = button.getAttribute('aria-pressed') === 'true';
      for (const other of buttons) {
        other.setAttribute('aria-pressed', 'false');
      }
      button.setAttribute('aria-pressed', String(!wasActive));
      look.isolated = wasActive ? null : row.codes;
      whenLoaded(map, () => applyLook(map));
    });
    return button;
  });
  $('#legend-items').replaceChildren(...buttons.map((button) => el('li', {}, button)));

  toggle.addEventListener('change', () => {
    look.colors = toggle.checked;
    legend.classList.toggle('legend-off', !toggle.checked);
    whenLoaded(map, () => applyLook(map));
  });

  legend.dataset.ready = 'true';
  onReady();
}

function setUpSearch(panel) {
  const form = $('#search');
  const kuInput = $('#search-ku');
  const numberInput = $('#search-number');
  const options = $('#ku-options');
  const status = $('#search-status');
  const results = $('#search-results');
  let suggestRequest = null;

  const setStatus = (message) => {
    status.textContent = message;
    status.hidden = message === '';
  };

  const suggest = debounce(async () => {
    const query = kuInput.value.trim();
    if (query.length < 2) {
      return;
    }
    suggestRequest?.abort();
    suggestRequest = new AbortController();
    try {
      const { results: areas } = await getJson(`/api/ku?q=${encodeURIComponent(query)}`, suggestRequest.signal);
      options.replaceChildren(...areas.map((area) => {
        const option = el('option', { value: area.nazev });
        if (area.obec.nazev !== area.nazev) {
          option.label = `${area.nazev} (obec ${area.obec.nazev})`;
        }
        return option;
      }));
    } catch (error) {
      if (error.name !== 'AbortError') {
        options.replaceChildren();
      }
    }
  }, SEARCH_DEBOUNCE_MS);

  kuInput.addEventListener('input', suggest);

  function showResults(found) {
    results.replaceChildren(...found.map((parcel) => {
      const button = el('button', { type: 'button' }, parcel.label, ' ', el('span', { class: 'result-ku' }, `· ${parcel.ku.nazev}`));
      button.addEventListener('click', () => {
        results.hidden = true;
        setStatus('');
        panel.open(parcel.id);
      });
      return el('li', {}, button);
    }));
    results.hidden = false;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const ku = kuInput.value.trim();
    const number = numberInput.value.trim();
    results.hidden = true;
    if (ku === '' || number === '') {
      setStatus('Zadejte katastrální území i číslo parcely.');
      (ku === '' ? kuInput : numberInput).focus();
      return;
    }

    if (!PARCEL_NUMBER.test(number)) {
      setStatus('Číslo parcely zadejte jako 123, 123/4 nebo st. 123/4.');
      numberInput.focus();
      return;
    }

    setStatus('Hledám…');
    try {
      const { results: found } = await getJson(`/api/search?ku=${encodeURIComponent(ku)}&cislo=${encodeURIComponent(number)}`);
      if (found.length === 0) {
        setStatus(`V k. ú. „${ku}“ se parcela ${number} nenašla. Vyberte k. ú. z nabídky a zkontrolujte číslo.`);
      } else if (found.length === 1) {
        setStatus('');
        panel.open(found[0].id);
      } else {
        setStatus(found.length === 10 ? 'Prvních 10 parcel:' : `Nalezené parcely (${found.length}):`);
        showResults(found);
      }
    } catch (error) {
      setStatus(error.status === 400
        ? 'Číslo parcely zadejte jako 123, 123/4 nebo st. 123/4.'
        : 'Hledání selhalo, zkuste to znovu.');
    }
  });

  // Enter in the area field moves on to the number (the form is submitted from there).
  kuInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && numberInput.value.trim() === '') {
      event.preventDefault();
      numberInput.focus();
    }
  });
}

main();

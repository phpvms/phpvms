#!/usr/bin/env node
/**
 * Builds the static SVG maps in resources/maps from Natural Earth Admin 0
 * Countries GeoJSON (public domain).
 *
 *   node bin/maps/build.mjs <ne_50m_admin_0_countries.geojson> <ne_110m_admin_0_countries.geojson> <output-dir>
 *
 * Plain Node, no npm imports. The projection maths here are mirrored by
 * App\Features\Maps\MapProjection; keep the two in step.
 */
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { join } from "node:path";

const [fiftyPath, worldPath, outDir] = process.argv.slice(2);
if (!fiftyPath || !worldPath || !outDir) {
  console.error("usage: node bin/maps/build.mjs <50m.geojson> <110m.geojson> <output-dir>");
  process.exit(1);
}

const SIZE = 1000; // the longer side of every viewBox, in SVG units
const MIN_EXTENT = 1; // degrees; keeps tiny countries from zooming to a dot
const PAD = 0.03; // fraction of the country extent added around it
const TOLERANCE = { world: 0.7, continent: 0.5, country: 0.4 };
const MIN_RING_AREA = { world: 1.5, continent: 1.0, country: 0.6 };

/** Continent view bounds. lonShift 360 moves negative longitudes east of the antimeridian. */
const CONTINENTS = {
  africa: { name: "Africa", lonMin: -20, lonMax: 55, latMin: -37, latMax: 38, lonShift: 0 },
  asia: {
    name: "Asia",
    lonMin: 25,
    lonMax: 190,
    latMin: -11,
    latMax: 78,
    lonShift: 360,
    lonWrap: -160,
  },
  europe: { name: "Europe", lonMin: -25, lonMax: 45, latMin: 34, latMax: 72, lonShift: 0 },
  "north-america": {
    name: "North America",
    lonMin: -168,
    lonMax: -12,
    latMin: 7,
    latMax: 84,
    lonShift: 0,
  },
  "south-america": {
    name: "South America",
    lonMin: -82,
    lonMax: -34,
    latMin: -56,
    latMax: 13,
    lonShift: 0,
  },
  oceania: {
    name: "Oceania",
    lonMin: 110,
    lonMax: 235,
    latMin: -50,
    latMax: 15,
    lonShift: 360,
    lonWrap: -125,
  },
};

/**
 * Countries split into regions, each its own map. The first region is the
 * country's main map (its id is the ISO code); the rest become `{id}` maps with
 * `parent` set. A polygon belongs to the first region whose box holds the centre
 * of its bounds; polygons in no region are left out. A box with lonMin > lonMax
 * wraps over the antimeridian (Alaska's Aleutians).
 */
const COUNTRY_REGIONS = {
  us: [
    {
      id: "us",
      name: "United States of America",
      lonMin: -125.5,
      lonMax: -66,
      latMin: 24,
      latMax: 50,
    },
    { id: "us-ak", name: "Alaska", lonMin: 172, lonMax: -129, latMin: 51, latMax: 72 },
    { id: "us-hi", name: "Hawaii", lonMin: -161, lonMax: -154, latMin: 18, latMax: 23 },
  ],
};

const WORLD = {
  name: "World",
  lonMin: -180,
  lonMax: 180,
  latMin: -90,
  latMax: 85,
  lonShift: 0,
  lonWrap: 0,
};

/** Extra lowercase names to match free-text country input, keyed by ISO 3166-1 alpha-2. */
const EXTRA_ALIASES = {
  us: ["usa", "u.s.", "u.s.a.", "united states of america", "america"],
  gb: [
    "uk",
    "u.k.",
    "great britain",
    "britain",
    "england",
    "scotland",
    "wales",
    "northern ireland",
  ],
  vn: ["viet nam"],
  ru: ["russian federation"],
  kr: ["republic of korea", "korea, republic of", "korea (south)", "korea, south"],
  kp: [
    "democratic peoples republic of korea",
    "korea, democratic peoples republic of",
    "korea (north)",
    "korea, north",
    "dprk",
  ],
  cz: ["czech republic"],
  mm: ["burma"],
  ci: ["ivory coast", "cote d'ivoire"],
  sz: ["swaziland", "kingdom of eswatini"],
  mk: ["macedonia", "republic of north macedonia"],
  tr: ["turkiye", "republic of turkey"],
  ir: ["iran, islamic republic of", "islamic republic of iran"],
  sy: ["syrian arab republic"],
  la: ["lao pdr", "lao peoples democratic republic"],
  bo: ["bolivia, plurinational state of"],
  ve: ["venezuela, bolivarian republic of"],
  tz: ["united republic of tanzania", "tanzania, united republic of"],
  md: ["republic of moldova", "moldova, republic of"],
  bn: ["brunei darussalam"],
  cv: ["cabo verde"],
  cd: [
    "democratic republic of the congo",
    "congo (kinshasa)",
    "congo, democratic republic of the",
    "dr congo",
    "drc",
  ],
  cg: ["republic of the congo", "congo (brazzaville)", "congo, republic of the"],
  tw: ["republic of china", "chinese taipei"],
  ps: ["palestinian territories", "state of palestine", "palestinian territory"],
  ae: ["uae"],
  bs: ["the bahamas", "bahamas, the"],
  gm: ["the gambia", "gambia, the"],
  nl: ["the netherlands", "holland"],
  mo: ["macao"],
  hk: ["hong kong sar"],
  tl: ["east timor"],
  fm: ["federated states of micronesia", "micronesia, federated states of"],
  va: ["holy see", "vatican city"],
  rs: ["republic of serbia"],
};

const slug = (value) =>
  value
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");

const fold = (value) =>
  value
    .replace(/[\u2018\u2019]/g, "'")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/[^\p{L}\p{N}\s.,()'-]/gu, "")
    .replace(/\s+/g, " ")
    .trim();

const round1 = (value) => Math.round(value * 10) / 10;

/**
 * Reads a GeoJSON file into country records with ISO codes resolved.
 * ISO_A2 is "-99" for some countries; ISO_A2_EH then WB_A2 fill in unless the
 * code already belongs to another country (Indian Ocean Ter., Ashmore and
 * Cartier would both claim "AU"). Records with no usable code get iso = null.
 */
function readCountries(path) {
  const collection = JSON.parse(readFileSync(path, "utf8"));
  const valid = (code) => typeof code === "string" && /^[A-Z]{2}$/.test(code);
  const taken = new Set(collection.features.map((f) => f.properties.ISO_A2).filter(valid));

  return collection.features.map((feature) => {
    const p = feature.properties;
    let iso = valid(p.ISO_A2) ? p.ISO_A2 : null;
    if (iso === null) {
      for (const candidate of [p.ISO_A2_EH, p.WB_A2]) {
        if (valid(candidate) && !taken.has(candidate)) {
          iso = candidate;
          taken.add(candidate);
          break;
        }
      }
    }
    const polygons =
      feature.geometry.type === "Polygon"
        ? [feature.geometry.coordinates]
        : feature.geometry.coordinates;

    return {
      iso: iso ? iso.toLowerCase() : null,
      props: p,
      continent: slug(p.CONTINENT ?? ""),
      polygons,
    };
  });
}

/** Longitude span of the given polygons; > 180 means they cross the antimeridian. */
function lonSpan(polygons) {
  let min = Infinity;
  let max = -Infinity;
  for (const polygon of polygons) {
    for (const [lon] of polygon[0]) {
      min = Math.min(min, lon);
      max = Math.max(max, lon);
    }
  }

  return max - min;
}

/**
 * Moves a ring east of the antimeridian when the whole ring lies west of
 * `wrap`. Whole rings only: a ring that straddles the prime meridian (UK,
 * Spain) must not be torn in two.
 */
function shiftRing(ring, shift, wrap) {
  if (!shift || Math.max(...ring.map((p) => p[0])) >= wrap) {
    return ring;
  }

  return ring.map(([lon, lat]) => [lon + shift, lat]);
}

function ringBounds(ring) {
  let lonMin = Infinity;
  let lonMax = -Infinity;
  let latMin = Infinity;
  let latMax = -Infinity;
  for (const [lon, lat] of ring) {
    lonMin = Math.min(lonMin, lon);
    lonMax = Math.max(lonMax, lon);
    latMin = Math.min(latMin, lat);
    latMax = Math.max(latMax, lat);
  }

  return { lonMin, lonMax, latMin, latMax };
}

/** The first region whose box holds the centre of the polygon's bounds. */
function regionOf(regions, polygon) {
  const b = ringBounds(polygon[0]);
  const lon = (b.lonMin + b.lonMax) / 2;
  const lat = (b.latMin + b.latMax) / 2;

  return regions.find((r) => {
    const inLon =
      r.lonMin <= r.lonMax
        ? lon >= r.lonMin && lon <= r.lonMax
        : lon >= r.lonMin || lon <= r.lonMax;

    return inLon && lat >= r.latMin && lat <= r.latMax;
  });
}

const intersects = (a, b) =>
  a.lonMin <= b.lonMax && a.lonMax >= b.lonMin && a.latMin <= b.latMax && a.latMax >= b.latMin;

/** Sutherland-Hodgman clip of a ring against a lon/lat rectangle. */
function clipRing(ring, rect) {
  const edges = [
    [(p) => p[0] >= rect.lonMin, (a, b) => lerp(a, b, (rect.lonMin - a[0]) / (b[0] - a[0]))],
    [(p) => p[0] <= rect.lonMax, (a, b) => lerp(a, b, (rect.lonMax - a[0]) / (b[0] - a[0]))],
    [(p) => p[1] >= rect.latMin, (a, b) => lerp(a, b, (rect.latMin - a[1]) / (b[1] - a[1]))],
    [(p) => p[1] <= rect.latMax, (a, b) => lerp(a, b, (rect.latMax - a[1]) / (b[1] - a[1]))],
  ];
  let output = ring;
  for (const [inside, cross] of edges) {
    const input = output;
    output = [];
    for (let i = 0; i < input.length; i++) {
      const current = input[i];
      const previous = input[(i + input.length - 1) % input.length];
      if (inside(current)) {
        if (!inside(previous)) {
          output.push(cross(previous, current));
        }
        output.push(current);
      } else if (inside(previous)) {
        output.push(cross(previous, current));
      }
    }
    if (output.length === 0) {
      return [];
    }
  }

  return output;
}

const lerp = (a, b, t) => [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];

function distanceToSegment(p, a, b) {
  const dx = b[0] - a[0];
  const dy = b[1] - a[1];
  const lengthSq = dx * dx + dy * dy;
  let t = lengthSq === 0 ? 0 : ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / lengthSq;
  t = Math.max(0, Math.min(1, t));

  return Math.hypot(p[0] - (a[0] + t * dx), p[1] - (a[1] + t * dy));
}

/** Douglas-Peucker on an open polyline; iterative to survive long rings. */
function simplify(points, tolerance) {
  if (points.length <= 3) {
    return points;
  }
  const keep = new Uint8Array(points.length);
  keep[0] = 1;
  keep[points.length - 1] = 1;
  const stack = [[0, points.length - 1]];
  while (stack.length > 0) {
    const [start, end] = stack.pop();
    let worst = -1;
    let worstIndex = -1;
    for (let i = start + 1; i < end; i++) {
      const d = distanceToSegment(points[i], points[start], points[end]);
      if (d > worst) {
        worst = d;
        worstIndex = i;
      }
    }
    if (worst > tolerance) {
      keep[worstIndex] = 1;
      stack.push([start, worstIndex], [worstIndex, end]);
    }
  }

  return points.filter((_, i) => keep[i]);
}

function ringArea(points) {
  let sum = 0;
  for (let i = 0; i < points.length; i++) {
    const [x1, y1] = points[i];
    const [x2, y2] = points[(i + 1) % points.length];
    sum += x1 * y2 - x2 * y1;
  }

  return Math.abs(sum) / 2;
}

const num = (value) => {
  const text = String(round1(value));

  return text === "-0" ? "0" : text.replace(/^(-?)0\./, "$1.");
};

/** Emits an SVG path: absolute move, then relative line deltas between the rounded points. */
function ringPath(points) {
  const rounded = points.map(([x, y]) => [round1(x), round1(y)]);
  const deduped = rounded.filter(
    (p, i) => i === 0 || p[0] !== rounded[i - 1][0] || p[1] !== rounded[i - 1][1],
  );
  if (deduped.length > 1) {
    const first = deduped[0];
    const last = deduped[deduped.length - 1];
    if (first[0] === last[0] && first[1] === last[1]) {
      deduped.pop();
    }
  }
  if (deduped.length < 3) {
    return null;
  }
  let d = `M${num(deduped[0][0])} ${num(deduped[0][1])}l`;
  let previous = deduped[0];
  const parts = [];
  for (let i = 1; i < deduped.length; i++) {
    const dx = round1(deduped[i][0] - previous[0]);
    const dy = round1(deduped[i][1] - previous[1]);
    parts.push(`${num(dx)}${dy < 0 ? "" : " "}${num(dy)}`);
    previous = deduped[i];
  }
  d += parts.join(" ").replace(/ -/g, "-") + "z";

  return d;
}

/**
 * Derives the projection for a lon/lat window (already in shifted longitudes).
 * x = (lon - lonMin) * cosLat0 * k ; y = (latMax - lat) * k
 */
function projectionFor(window) {
  const cosLat0 = Math.cos((((window.latMin + window.latMax) / 2) * Math.PI) / 180);
  const widthDeg = (window.lonMax - window.lonMin) * cosLat0;
  const heightDeg = window.latMax - window.latMin;
  const k = SIZE / Math.max(widthDeg, heightDeg);

  return {
    lonMin: window.lonMin,
    lonMax: window.lonMax,
    latMin: window.latMin,
    latMax: window.latMax,
    lonShift: window.lonShift,
    lonWrap: window.lonWrap ?? 0,
    cosLat0,
    k,
    width: round1(widthDeg * k),
    height: round1(heightDeg * k),
  };
}

/**
 * Renders the country paths for one map.
 * `focusOf(country)` says whether a country is drawn with `land--focus`;
 * `keepPolygon(country, shifted, original)` optionally limits which polygons of the focus country are kept.
 */
function renderMap(projection, countries, kind, focusOf, keepPolygon = () => true) {
  const rect = {
    lonMin: projection.lonMin - 1,
    lonMax: projection.lonMax + 1,
    latMin: projection.latMin - 1,
    latMax: projection.latMax + 1,
  };
  const project = ([lon, lat]) => [
    (lon - projection.lonMin) * projection.cosLat0 * projection.k,
    (projection.latMax - lat) * projection.k,
  ];
  const plain = [];
  const focus = [];

  for (const country of countries) {
    // Antarctica's ring runs the whole antimeridian; shifting it draws bands across the map.
    if (projection.lonShift !== 0 && country.continent === "antarctica") {
      continue;
    }
    const isFocus = focusOf(country);
    let d = "";
    let kept = 0;
    const rings = [];
    for (const polygon of country.polygons) {
      const shifted = polygon.map((ring) =>
        shiftRing(ring, projection.lonShift, projection.lonWrap),
      );
      if (!intersects(ringBounds(shifted[0]), rect)) {
        continue;
      }
      if (isFocus && !keepPolygon(country, shifted, polygon)) {
        continue;
      }
      for (const ring of shifted) {
        const clipped = clipRing(ring.slice(0, -1), rect);
        if (clipped.length < 3) {
          continue;
        }
        const projected = clipped.map(project);
        rings.push({ projected, area: ringArea(projected), outer: ring === shifted[0] });
      }
    }
    const largest = Math.max(0, ...rings.map((r) => r.area));
    for (const ring of rings) {
      if (ring.area < MIN_RING_AREA[kind] && ring.area < largest) {
        continue;
      }
      const closed = [...ring.projected, ring.projected[0]];
      const simple = simplify(closed, TOLERANCE[kind]).slice(0, -1);
      const path = ringPath(simple);
      if (path) {
        d += path;
        kept++;
      }
    }
    if (kept === 0) {
      continue;
    }
    const iso = country.iso ? ` data-iso="${country.iso}"` : "";
    const path = `<path class="${isFocus ? "land land--focus" : "land"}"${iso} d="${d}"/>`;
    (isFocus ? focus : plain).push(path);
  }

  return (
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${projection.width} ${projection.height}">` +
    [...plain, ...focus].join("") +
    "</svg>\n"
  );
}

function aliasesFor(country) {
  const p = country.props;
  const names = [
    p.NAME,
    p.NAME_LONG,
    p.ADMIN,
    p.FORMAL_EN,
    p.NAME_EN,
    ...(EXTRA_ALIASES[country.iso] ?? []),
  ];
  const set = new Set();
  for (const name of names) {
    if (typeof name === "string" && name.trim() !== "") {
      set.add(fold(name));
    }
  }
  return [...set].sort();
}

// ---------------------------------------------------------------------------

const fifty = readCountries(fiftyPath);
const hundred = readCountries(worldPath);
const index = {};
const files = {};
const skipped = [];

// World (1:110m)
{
  const projection = projectionFor(WORLD);
  files["world.svg"] = renderMap(projection, hundred, "world", () => false);
  index.world = {
    type: "world",
    name: "World",
    iso2: null,
    continent: null,
    ...fields(projection),
  };
}

// Continents (1:50m)
for (const [id, bounds] of Object.entries(CONTINENTS)) {
  const projection = projectionFor(bounds);
  files[`continents/${id}.svg`] = renderMap(
    projection,
    fifty,
    "continent",
    (c) => c.continent === id,
  );
  index[`continent:${id}`] = {
    type: "continent",
    name: bounds.name,
    iso2: null,
    continent: id,
    ...fields(projection),
  };
}

// Countries (1:50m), each shown with its neighbours greyed. A country with
// regions becomes one map per region.
for (const country of fifty) {
  if (country.iso === null) {
    skipped.push(`${country.props.NAME} (no unique ISO code)`);
    continue;
  }
  const regions = COUNTRY_REGIONS[country.iso];
  const units = regions
    ? regions.map((region, i) => ({
        id: region.id,
        name: region.name,
        polygons: country.polygons.filter((polygon) => regionOf(regions, polygon) === region),
        parent: i === 0 ? null : regions[0].id,
        regionIds: i === 0 ? regions.slice(1).map((r) => r.id) : [],
      }))
    : [
        {
          id: country.iso,
          name: country.props.NAME,
          polygons: country.polygons,
          parent: null,
          regionIds: [],
        },
      ];

  for (const unit of units) {
    if (unit.polygons.length === 0) {
      throw new Error(`region ${unit.id} has no polygons`);
    }
    const members = new Set(unit.polygons);
    const lonShift = lonSpan(unit.polygons) > 180 && country.continent !== "antarctica" ? 360 : 0;

    // Keep the polygons near the largest one, so far-flung territories
    // (French Guiana, Svalbard) do not shrink the mainland. A region is
    // already curated by its box, so it keeps every polygon.
    const polygons = unit.polygons.map((polygon) =>
      polygon.map((ring) => shiftRing(ring, lonShift, 0)),
    );
    const boxes = polygons.map((polygon) => ringBounds(polygon[0]));
    const area = (b) => (b.lonMax - b.lonMin) * (b.latMax - b.latMin);
    const main = boxes.reduce((a, b) => (area(b) > area(a) ? b : a));
    const reach = {
      lonMin: main.lonMin - (main.lonMax - main.lonMin) * 0.5,
      lonMax: main.lonMax + (main.lonMax - main.lonMin) * 0.5,
      latMin: main.latMin - (main.latMax - main.latMin) * 0.5,
      latMax: main.latMax + (main.latMax - main.latMin) * 0.5,
    };
    const near = regions ? boxes : boxes.filter((b) => intersects(b, reach));
    const box = {
      lonMin: Math.min(...near.map((b) => b.lonMin)),
      lonMax: Math.max(...near.map((b) => b.lonMax)),
      latMin: Math.min(...near.map((b) => b.latMin)),
      latMax: Math.max(...near.map((b) => b.latMax)),
    };

    const extent = (lo, hi) => Math.max(hi - lo, MIN_EXTENT);
    const lonPad = extent(box.lonMin, box.lonMax) * PAD;
    const latPad = extent(box.latMin, box.latMax) * PAD;
    const lonMid = (box.lonMin + box.lonMax) / 2;
    const latMid = (box.latMin + box.latMax) / 2;
    const window = {
      lonMin: lonMid - extent(box.lonMin, box.lonMax) / 2 - lonPad,
      lonMax: lonMid + extent(box.lonMin, box.lonMax) / 2 + lonPad,
      latMin: Math.max(-90, latMid - extent(box.latMin, box.latMax) / 2 - latPad),
      latMax: Math.min(90, latMid + extent(box.latMin, box.latMax) / 2 + latPad),
      lonShift,
      lonWrap: 0,
    };
    const projection = projectionFor(window);
    const keep = (_, shifted, original) =>
      members.has(original) && (regions !== undefined || intersects(ringBounds(shifted[0]), reach));

    files[`countries/${unit.id}.svg`] = renderMap(
      projection,
      fifty,
      "country",
      (c) => c === country,
      keep,
    );
    index[`country:${unit.id}`] = {
      type: "country",
      name: unit.name,
      iso2: country.iso,
      continent: country.continent,
      ...(unit.parent !== null ? { parent: unit.parent } : {}),
      ...(unit.regionIds.length > 0 ? { regions: unit.regionIds } : {}),
      ...fields(projection),
      aliases: unit.parent === null ? aliasesFor(country) : [],
    };
  }
}

function fields(p) {
  return {
    width: p.width,
    height: p.height,
    lonMin: p.lonMin,
    lonMax: p.lonMax,
    latMin: p.latMin,
    latMax: p.latMax,
    k: p.k,
    cosLat0: p.cosLat0,
    lonShift: p.lonShift,
    lonWrap: p.lonWrap,
  };
}

mkdirSync(join(outDir, "continents"), { recursive: true });
mkdirSync(join(outDir, "countries"), { recursive: true });
for (const [name, svg] of Object.entries(files)) {
  writeFileSync(join(outDir, name), svg);
}
writeFileSync(join(outDir, "index.json"), JSON.stringify(index, null, 1) + "\n");

console.log(`wrote ${Object.keys(files).length} maps to ${outDir}`);
for (const line of skipped) {
  console.log(`skipped country map: ${line}`);
}

/**
 * Admin dashboard charts and layout edit controls.
 *
 * Loaded lazily from `admin/app.js`. Each chart widget renders a container
 * with a JSON payload; `bootstrap()` scans the DOM and renders whatever is
 * present.
 */

import * as d3 from "d3";

const W = 800;
const H = 240;

function dashboardCanvas() {
  return document.querySelector(".dashboard-canvas");
}

function setDashboardEditing(isEditing) {
  const canvas = dashboardCanvas();
  const component = canvas ? window.Alpine?.$data(canvas) : null;

  component?.setEditable(isEditing);
  canvas?.classList.toggle("dashboard-layout-editing", isEditing);

  if (isEditing) {
    canvas?.querySelectorAll(".grid-stack").forEach((gridElement) => {
      if (gridElement.dataset.phpvmsResizeBound) return;

      gridElement.gridstack?.on("resizestop", () => window.dispatchEvent(new Event("resize")));
      gridElement.dataset.phpvmsResizeBound = "true";
    });
  }

  document
    .querySelectorAll("[data-dashboard-layout-edit]")
    .forEach((action) => action.classList.toggle("hidden", isEditing));
  document
    .querySelectorAll("[data-dashboard-layout-save]")
    .forEach((action) => action.classList.toggle("hidden", !isEditing));
  document
    .querySelectorAll("[data-dashboard-layout-add]")
    .forEach((action) => action.classList.toggle("hidden", !isEditing));
  document
    .querySelectorAll("[data-dashboard-layout-reset]")
    .forEach((action) => action.classList.toggle("hidden", !isEditing));
}

export function editDashboardLayout() {
  setDashboardEditing(true);
}

/**
 * Remove a widget from the grid client-side and delete its row in the
 * background. No confirmation, no reload, no notification.
 *
 * The GridStack instance is always read back off the DOM
 * (`gridElement.gridstack`), never from Alpine state — Alpine deep-proxies
 * its data, and a proxied instance fails GridStack's `node.grid === this`
 * identity check (see the same rule in grid.js).
 */
export function removeDashboardWidget(id) {
  const canvas = dashboardCanvas();
  const itemEl = canvas?.querySelector(`.grid-stack-item[gs-id="${id}"]`);
  const gridElement = itemEl?.closest(".grid-stack");
  const grid = gridElement?.gridstack;
  if (!itemEl || !grid) return;

  grid.removeWidget(itemEl);

  const page = canvas.closest("[wire\\:id]");
  const wireId = page?.getAttribute("wire:id");
  const wire = wireId ? window.Livewire?.find(wireId) : null;
  wire?.deleteDashboardWidget(id);
}
window.removeDashboardWidget = removeDashboardWidget;

export function serializeLayout(canvas = dashboardCanvas()) {
  if (!canvas) return [];

  return Array.from(canvas.querySelectorAll(".grid-stack")).flatMap((gridElement) => {
    const section = gridElement.dataset.section;
    const nodes = gridElement.gridstack?.engine?.nodes ?? [];

    return nodes.map((node) => ({
      id: Number(node.el.getAttribute("gs-id")),
      section,
      x: node.x,
      y: node.y,
      w: node.w,
      h: node.h,
    }));
  });
}

export async function saveDashboardLayouts() {
  const canvas = dashboardCanvas();
  const page = canvas?.closest("[wire\\:id]");
  const wireId = page?.getAttribute("wire:id");
  const wire = wireId ? window.Livewire?.find(wireId) : null;

  if (wire) {
    await wire.persistLayout(serializeLayout(canvas));
  }

  setDashboardEditing(false);
}

/**
 * Chart colours are read from the console's theme tokens (--primary-600,
 * --ok/--warn/--bad/--info/--mute) instead of a hardcoded palette, so
 * charts retint with the theme picker's brand colour and dark mode.
 *
 * resolveColor() forces the browser to resolve a `var()` reference to a
 * concrete rgb() via a hidden probe element (getComputedStyle().color).
 * This only works for *literal* custom properties: every --primary-N shade
 * except --primary-600 is itself set by the theme picker as a
 * `color-mix(in oklab, ...)` string, and Chrome's computed-value
 * serialization for that returns an `oklab(...)`/`color(srgb ...)`
 * string — which d3-color can't parse (it silently falls back to black).
 * --primary-600 is always the literal brand hex, so it's safe; lighter/
 * darker shades are mixed in JS with d3.interpolateRgb below instead of
 * asking CSS to pre-mix them.
 */
let colorProbe = null;
let colorCache = new Map();
function resolveColor(cssVar) {
  if (colorCache.has(cssVar)) return colorCache.get(cssVar);
  if (!colorProbe) {
    colorProbe = document.createElement("div");
    colorProbe.style.cssText = "position:fixed;top:-9999px;left:-9999px;visibility:hidden;";
    document.body.appendChild(colorProbe);
  }
  colorProbe.style.color = cssVar;
  const resolved = getComputedStyle(colorProbe).color;
  colorCache.set(cssVar, resolved);
  return resolved;
}

function isDarkMode() {
  return document.documentElement.classList.contains("dark");
}

/**
 * 8-step sequential ramp (light wash -> accent), same shape as the old
 * hardcoded indigo array so renderers can keep indexing into it. Built at
 * render time so it tracks the current brand colour and appearance.
 */
function sequentialShades() {
  const accent = resolveColor("var(--primary-600)");
  const dark = isDarkMode();
  // Light end: mostly white (light mode) or mostly the dark surface (dark
  // mode) with a wash of accent, matching the ~12%/16% mixes elsewhere in
  // the theme. The final shade is the accent itself so the selected theme
  // colour is present in every sequential chart.
  const lightEnd = dark
    ? d3.interpolateRgb("black", accent)(0.16)
    : d3.interpolateRgb("white", accent)(0.12);
  const interpolate = d3.interpolateRgb(lightEnd, accent);
  return d3.range(8).map((i) => interpolate(i / 7));
}

/** Accent + console state colours, for categorical series (pie/doughnut). */
function categoricalColors() {
  return [
    resolveColor("var(--primary-600)"),
    resolveColor("var(--info)"),
    resolveColor("var(--warn)"),
    resolveColor("var(--bad)"),
    resolveColor("var(--ok)"),
    resolveColor("var(--mute)"),
    "#8b5cf6",
    "#ec4899",
  ];
}

function svg(el, width, height) {
  el.replaceChildren();
  const s = d3
    .select(el)
    .append("svg")
    .attr("viewBox", `0 0 ${width} ${height}`)
    .attr("preserveAspectRatio", "xMidYMid meet")
    .attr("role", "img")
    .style("width", "100%")
    .style("height", "100%");
  return s;
}

function chartSize(el, fallbackHeight = H) {
  return {
    width: el.clientWidth || W,
    height: el.clientHeight || fallbackHeight,
  };
}

function noData(el) {
  const { width, height } = chartSize(el, 120);
  const s = svg(el, width, height);
  s.append("text")
    .attr("x", width / 2)
    .attr("y", height / 2)
    .attr("text-anchor", "middle")
    .attr("fill", "currentColor")
    .attr("font-size", 12)
    .text("No data");
}

function axisText(sel, size = 10) {
  return sel.attr("fill", "currentColor").attr("font-size", size).attr("font-family", "inherit");
}

/**
 * Wrap the plot area of a Cartesian chart in a clip path so bars/lines
 * never bleed outside the plot box. Axes stay fixed; the returned group
 * holds the (unzoomed) content.
 */
function chartContainer(s, g, width, height) {
  const clipId = `chart-clip-${uid++}`;
  s.append("clipPath")
    .attr("id", clipId)
    .append("rect")
    .attr("width", width)
    .attr("height", height);
  return g.append("g").attr("clip-path", `url(#${clipId})`);
}

/** Vertical bar chart: { labels: string[], values: number[] } */
function renderBar(el, data) {
  const labels = data.labels ?? [];
  const values = data.values ?? [];
  if (!labels.length || values.every((v) => !v)) return noData(el);

  const margin = { top: 16, right: 8, bottom: 24, left: 34 };
  const size = chartSize(el);
  const width = size.width - margin.left - margin.right;
  const height = size.height - margin.top - margin.bottom;
  const s = svg(el, size.width, size.height);
  const g = s.append("g").attr("transform", `translate(${margin.left},${margin.top})`);
  const plot = chartContainer(s, g, width, height);

  const shades = sequentialShades();
  const x = d3.scaleBand().domain(labels).range([0, width]).padding(0.25);
  const maxValue = Math.max(...values) || 1;
  const y = d3
    .scaleLinear()
    .domain([0, maxValue * 1.1])
    .range([height, 0]);
  const color = d3.scaleLinear().domain([0, maxValue]).range([shades[1], shades[7]]);

  g.append("g")
    .call(d3.axisLeft(y).ticks(4).tickSizeOuter(0))
    .call((a) => a.selectAll("text").call(axisText))
    .call((a) => a.select(".domain").remove())
    .attr("color", "currentColor")
    .attr("opacity", 0.6);

  const bars = plot
    .selectAll("rect")
    .data(values)
    .join("rect")
    .attr("x", (_, i) => x(labels[i]))
    .attr("y", y)
    .attr("width", x.bandwidth())
    .attr("height", (v) => Math.max(0, height - y(v)))
    .attr("rx", 2)
    .attr("fill", (v) => color(v));

  bars.append("title").text((v, i) => `${labels[i]}: ${Number(v).toLocaleString()}`);

  g.append("g")
    .attr("transform", `translate(0,${height})`)
    .call(d3.axisBottom(x).ticks(Math.min(labels.length, 12)).tickSizeOuter(0))
    .call((a) => a.selectAll("text").call(axisText))
    .call((a) => a.select(".domain").remove())
    .attr("color", "currentColor")
    .attr("opacity", 0.6);
}

/** Line/area chart: { labels: string[], values: number[] } */
function renderLine(el, data) {
  const labels = data.labels ?? [];
  const values = data.values ?? [];
  if (!labels.length) return noData(el);

  const margin = { top: 16, right: 8, bottom: 24, left: 40 };
  const size = chartSize(el);
  const width = size.width - margin.left - margin.right;
  const height = size.height - margin.top - margin.bottom;
  const s = svg(el, size.width, size.height);
  const g = s.append("g").attr("transform", `translate(${margin.left},${margin.top})`);
  const plot = chartContainer(s, g, width, height);

  const shades = sequentialShades();
  const x = d3.scalePoint().domain(labels).range([0, width]);
  const min = Math.min(0, ...values);
  const max = Math.max(...values, 1);
  const y = d3.scaleLinear().domain([min, max]).range([height, 0]);

  const area = d3
    .area()
    .x((_, i) => x(labels[i]))
    .y0(y(0))
    .y1((v) => y(v))
    .curve(d3.curveMonotoneX);
  const line = d3
    .line()
    .x((_, i) => x(labels[i]))
    .y((v) => y(v))
    .curve(d3.curveMonotoneX);

  plot.append("path").datum(values).attr("d", area).attr("fill", shades[3]).attr("opacity", 0.25);

  plot
    .append("path")
    .datum(values)
    .attr("d", line)
    .attr("fill", "none")
    .attr("stroke", shades[7])
    .attr("stroke-width", 2);

  g.append("g")
    .call(d3.axisLeft(y).ticks(4).tickSizeOuter(0))
    .call((a) => a.selectAll("text").call(axisText))
    .call((a) => a.select(".domain").remove())
    .attr("color", "currentColor")
    .attr("opacity", 0.6);

  g.append("g")
    .attr("transform", `translate(0,${height})`)
    .call(d3.axisBottom(x).ticks(Math.min(labels.length, 10)).tickSizeOuter(0))
    .call((a) => a.selectAll("text").call(axisText))
    .call((a) => a.select(".domain").remove())
    .attr("color", "currentColor")
    .attr("opacity", 0.6);

  plot
    .selectAll("circle")
    .data(values)
    .join("circle")
    .attr("cx", (_, i) => x(labels[i]))
    .attr("cy", (v) => y(v))
    .attr("r", 2.5)
    .attr("fill", shades[7])
    .append("title")
    .text((v, i) => `${labels[i]}: ${Number(v).toLocaleString()}`);
}

/** Doughnut: { labels: string[], values: number[] } with legend */
function renderDoughnut(el, data) {
  const labels = data.labels ?? [];
  const values = data.values ?? [];
  if (!labels.length || values.every((v) => !v)) return noData(el);

  const { width, height } = chartSize(el, 260);
  const radius = Math.max(12, Math.min(width / 3, height / 2) - 24);
  const s = svg(el, width, height);
  const g = s.append("g").attr("transform", `translate(${width / 2 - radius - 40},${height / 2})`);

  const pie = d3
    .pie()
    .sort(null)
    .value((d) => d[1]);
  const arc = d3
    .arc()
    .innerRadius(radius * 0.58)
    .outerRadius(radius);
  const color = d3.scaleOrdinal().domain(labels).range(categoricalColors());

  g.selectAll("path")
    .data(pie(values.map((v, i) => [labels[i], v])))
    .join("path")
    .attr("d", arc)
    .attr("fill", (d) => color(d.data[0]))
    .append("title")
    .text((d) => `${d.data[0]}: ${Number(d.data[1]).toLocaleString()}`);

  const total = values.reduce((a, b) => a + b, 0);
  g.append("text")
    .attr("text-anchor", "middle")
    .attr("dy", "-0.2em")
    .attr("font-size", 22)
    .attr("font-weight", 600)
    .attr("fill", "currentColor")
    .text(total.toLocaleString());
  g.append("text")
    .attr("text-anchor", "middle")
    .attr("dy", "1.4em")
    .attr("font-size", 10)
    .attr("fill", "currentColor")
    .attr("opacity", 0.6)
    .text("total");

  const legend = s
    .append("g")
    .attr(
      "transform",
      `translate(${width / 2 + radius - 20},${height / 2 - (labels.length * 18) / 2})`,
    );
  legend
    .selectAll("g")
    .data(labels)
    .join("g")
    .attr("transform", (_, i) => `translate(0,${i * 18})`)
    .each(function (label, i) {
      const row = d3.select(this);
      row
        .append("rect")
        .attr("width", 10)
        .attr("height", 10)
        .attr("rx", 2)
        .attr("fill", color(label));
      row
        .append("text")
        .attr("x", 16)
        .attr("y", 9)
        .attr("font-size", 10)
        .attr("fill", "currentColor")
        .text(`${label} (${values[i].toLocaleString()})`);
    });
}

/** Horizontal bar: { labels: string[], values: number[] } */
function renderHBar(el, data) {
  const labels = data.labels ?? [];
  const values = data.values ?? [];
  if (!labels.length || values.every((v) => !v)) return noData(el);

  const margin = { top: 8, right: 48, bottom: 8, left: 74 };
  const size = chartSize(el, Math.max(140, labels.length * 24 + 20));
  const height = size.height - margin.top - margin.bottom;
  const width = size.width - margin.left - margin.right;
  const s = svg(el, size.width, size.height);
  const g = s.append("g").attr("transform", `translate(${margin.left},${margin.top})`);
  const plot = chartContainer(s, g, width, height);

  const shades = sequentialShades();
  const y = d3.scaleBand().domain(labels).range([0, height]).padding(0.3);
  const maxValue = Math.max(...values) || 1;
  const x = d3
    .scaleLinear()
    .domain([0, maxValue * 1.05])
    .range([0, width]);
  const color = d3.scaleLinear().domain([0, maxValue]).range([shades[2], shades[7]]);

  g.append("g")
    .call(d3.axisLeft(y).tickSizeOuter(0))
    .call((a) => a.selectAll("text").call(axisText, 9))
    .call((a) => a.select(".domain").remove())
    .attr("color", "currentColor");

  const hrefs = data.hrefs ?? [];

  plot
    .selectAll("rect")
    .data(values)
    .join("rect")
    .attr("y", (_, i) => y(labels[i]))
    .attr("x", 0)
    .attr("width", (v) => x(v))
    .attr("height", y.bandwidth())
    .attr("rx", 2)
    .attr("fill", (v) => color(v))
    .style("cursor", hrefs.length ? "pointer" : null)
    // D3 v7 listeners receive (event, datum) — the index is not passed, so
    // read it from the datum via indexOf instead of trusting a second arg.
    // Make bars keyboard-activatable when they link somewhere.
    .attr("tabindex", hrefs.length ? 0 : null)
    .attr("role", hrefs.length ? "link" : null)
    .attr("aria-label", (v, i) => (hrefs[i] ? `${labels[i]}: ${Number(v).toLocaleString()}` : null))
    .on("click", (_, v) => {
      const i = values.indexOf(v);
      if (hrefs[i]) window.location.href = hrefs[i];
    })
    .on("keydown", (event, v) => {
      if (event.key !== "Enter" && event.key !== " ") return;
      const i = values.indexOf(v);
      if (!hrefs[i]) return;
      event.preventDefault();
      window.location.href = hrefs[i];
    })
    .append("title")
    .text((v, i) => `${labels[i]}: ${Number(v).toLocaleString()}`);

  plot
    .selectAll("text.value")
    .data(values)
    .join("text")
    .attr("class", "value")
    .attr("x", (v) => x(v) + 6)
    .attr("y", (_, i) => y(labels[i]) + y.bandwidth() / 2)
    .attr("dy", "0.35em")
    .attr("font-size", 9)
    .attr("fill", "currentColor")
    .text((v) => Number(v).toLocaleString());

  // Row labels double as links when hrefs are provided.
  if (hrefs.length) {
    g.selectAll(".tick text")
      .style("cursor", "pointer")
      .style("text-decoration", "underline")
      .style("text-decoration-style", "dotted")
      .style("text-underline-offset", "2px")
      // Axis tick datum is the label string (axisLeft domain), not the index.
      .attr("tabindex", 0)
      .attr("role", "link")
      .on("click", (_, label) => {
        const i = labels.indexOf(label);
        if (hrefs[i]) window.location.href = hrefs[i];
      })
      .on("keydown", (event, label) => {
        if (event.key !== "Enter" && event.key !== " ") return;
        const i = labels.indexOf(label);
        if (!hrefs[i]) return;
        event.preventDefault();
        window.location.href = hrefs[i];
      });
  }
}

/**
 * Calendar heatmap: last N days x 24 hours.
 * data: { days: [{ date: "2026-08-04", label: "Tue", values: number[24] }], max: number }
 */
function renderCalendar(el, data) {
  const days = data.days ?? [];
  if (!days.length) return noData(el);

  const gutterL = 34;
  const gutterT = 24;
  const legendHeight = 18;
  const minimumHeight = gutterT + days.length * 24 + legendHeight;
  const { width, height } = chartSize(el, minimumHeight);
  const cellWidth = (width - gutterL) / 24;
  const cellHeight = (height - gutterT - legendHeight) / days.length;
  const s = svg(el, width, height);
  const g = s.append("g").attr("transform", `translate(${gutterL},${gutterT})`);
  const plot = chartContainer(s, g, width - gutterL, height - gutterT - legendHeight);

  const shades = sequentialShades();
  const color = d3
    .scaleSequential(d3.interpolateRgb(shades[1], shades[7]))
    .domain([0, data.max || 1]);

  // hour labels (every 3h)
  g.append("g")
    .selectAll("text")
    .data(d3.range(0, 24, 3))
    .join("text")
    .attr("x", (h) => h * cellWidth + cellWidth / 2)
    .attr("y", -8)
    .attr("text-anchor", "middle")
    .attr("font-size", 9)
    .attr("fill", "currentColor")
    .attr("opacity", 0.6)
    .text((h) => `${String(h).padStart(2, "0")}:00`);

  // day labels
  g.append("g")
    .selectAll("text")
    .data(days)
    .join("text")
    .attr("x", -8)
    .attr("y", (_, i) => i * cellHeight + cellHeight / 2)
    .attr("text-anchor", "end")
    .attr("dominant-baseline", "middle")
    .attr("font-size", 9)
    .attr("fill", "currentColor")
    .attr("opacity", 0.7)
    .text((d) => d.label);

  days.forEach((day, di) => {
    plot
      .selectAll(`rect.day-${di}`)
      .data(day.values)
      .join("rect")
      .attr("x", (_, hi) => hi * cellWidth)
      .attr("y", di * cellHeight)
      .attr("width", cellWidth - 2)
      .attr("height", cellHeight - 2)
      .attr("rx", 3)
      .attr("fill", (v) => (v ? color(v) : "transparent"))
      .attr("stroke", "currentColor")
      .attr("stroke-opacity", (v) => (v ? 0 : 0.08))
      .style("cursor", "pointer")
      // D3 v7 click listeners get (event, datum) — datum is the hour's event
      // count, so recover the hour index from its position in day.values.
      // Cells are always links into the pireps list: focusable + Enter/Space.
      .attr("tabindex", 0)
      .attr("role", "link")
      .attr(
        "aria-label",
        (v, hi) =>
          `${day.date} ${String(hi).padStart(2, "0")}:00 — ${v} event${v === 1 ? "" : "s"}`,
      )
      .on("click", (_, v) => {
        const hi = day.values.indexOf(v);
        const hour = String(hi).padStart(2, "0");
        const from = `${day.date} ${hour}:00:00`;
        const to = `${day.date} ${hour}:59:59`;
        window.location.href = `/admin/pireps?departed_from=${encodeURIComponent(from)}&departed_to=${encodeURIComponent(to)}`;
      })
      .on("keydown", (event, v) => {
        if (event.key !== "Enter" && event.key !== " ") return;
        event.preventDefault();
        const hi = day.values.indexOf(v);
        const hour = String(hi).padStart(2, "0");
        const from = `${day.date} ${hour}:00:00`;
        const to = `${day.date} ${hour}:59:59`;
        window.location.href = `/admin/pireps?departed_from=${encodeURIComponent(from)}&departed_to=${encodeURIComponent(to)}`;
      })
      .append("title")
      .text(
        (v, hi) =>
          `${day.date} ${String(hi).padStart(2, "0")}:00 — ${v} event${v === 1 ? "" : "s"}`,
      );
  });

  // legend
  const legendId = `cal-legend-${uid++}`;
  const legend = s.append("g").attr("transform", `translate(${gutterL},${height - 12})`);
  legend
    .append("defs")
    .append("linearGradient")
    .attr("id", legendId)
    .selectAll("stop")
    .data([0, 1])
    .join("stop")
    .attr("offset", (d) => d * 100 + "%")
    .attr("stop-color", (d) => color(d * (data.max || 1)));
  legend
    .append("rect")
    .attr("width", 120)
    .attr("height", 6)
    .attr("rx", 3)
    .attr("fill", `url(#${legendId})`);
  legend
    .append("text")
    .attr("x", 128)
    .attr("y", 6)
    .attr("font-size", 9)
    .attr("fill", "currentColor")
    .attr("opacity", 0.6)
    .text(`0 — ${data.max || 0} events/hr`);
}

const RENDERERS = {
  bar: renderBar,
  line: renderLine,
  doughnut: renderDoughnut,
  hbar: renderHBar,
  calendar: renderCalendar,
};

let uid = 0;

export function bootstrap() {
  // Resolved colours are cached per pass only — they must be recomputed
  // whenever the theme changes (see retintOnThemeChange below).
  colorCache.clear();
  document.querySelectorAll("[data-dashboard-chart]").forEach((el) => {
    const type = el.dataset.dashboardChart;
    const renderer = RENDERERS[type];
    if (!renderer) return;
    const payload = el.dataset.chartPayload;
    if (!payload) return;
    // Re-render when the payload changes (e.g. report page filters drive a
    // new Livewire render of the widget); skip only when nothing changed.
    if (el.dataset.rendered === payload) return;
    let data;
    try {
      data = JSON.parse(payload);
    } catch (err) {
      console.error(`[dashboard] invalid JSON for chart "${type}"`, err);
      return;
    }
    try {
      renderer(el, data);
      el.dataset.rendered = payload;
    } catch (err) {
      console.error(`[dashboard] failed to render "${type}"`, err);
    }
  });
}

export function init() {
  // Coalesce observer callbacks to one scan per animation frame — bootstrap()
  // mutates the observed subtree (SVG nodes), so without this every render
  // re-triggers the observer and the scan runs repeatedly.
  let framePending = false;
  const run = () => {
    if (framePending) return;
    framePending = true;
    requestAnimationFrame(() => {
      framePending = false;
      bootstrap();
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run);
  } else {
    run();
  }

  // Filament lazy-mounts dashboard widgets (they arrive as
  // `fi-loading-section` placeholders and hydrate after the initial page
  // render, often on scroll), and report-page filters re-render widgets
  // with a new payload, so re-scan whenever the DOM or a payload changes.
  const observer = new MutationObserver(run);
  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ["data-chart-payload"],
  });

  // Chart colours are resolved from theme tokens (see resolveColor above),
  // so charts need a forced repaint — bypassing the "payload unchanged"
  // skip in bootstrap() — whenever the theme changes: dark/light mode
  // (Filament's own `theme-changed` window event, also used by
  // maps/base_map.js) or the brand colour, which the theme picker applies
  // as inline custom properties on <html> with no dedicated event, so a
  // narrow observer on <html>'s own style/class attributes catches it.
  let retintTimer = null;
  const retint = () => {
    clearTimeout(retintTimer);
    retintTimer = setTimeout(() => {
      document.querySelectorAll("[data-dashboard-chart]").forEach((el) => {
        delete el.dataset.rendered;
      });
      bootstrap();
    }, 50);
  };
  window.addEventListener("theme-changed", retint);
  window.addEventListener("resize", retint);
  new MutationObserver(retint).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ["style", "class"],
  });

  window.addEventListener("dashboard-layout:edit", editDashboardLayout);
  window.addEventListener("dashboard-layout:save", saveDashboardLayouts);
  window.addEventListener("dashboard-layout:reset", () => {
    window.setTimeout(() => window.location.reload(), 100);
  });

  // After adding a widget, the backend sets this flag and reloads the page
  // so the new widget lands with edit mode still on. Always clear the key
  // immediately, even if the canvas or grid never appears, so a stale flag
  // can't strand a later page load in edit mode.
  //
  // The restore itself has to wait for a booted GridStack: this chunk is
  // imported dynamically and can resolve before Alpine runs `bootGrids()`,
  // and everything edit mode does to the grid is optional-chained — running
  // early would paint the edit chrome over a still-static grid with no retry.
  if (window.sessionStorage.getItem("phpvms:dashboard-editing") === "1") {
    window.sessionStorage.removeItem("phpvms:dashboard-editing");

    if (dashboardCanvas()?.querySelector(".grid-stack")?.gridstack) {
      setDashboardEditing(true);
    } else {
      window.addEventListener("dashboard-grid:ready", () => setDashboardEditing(true), {
        once: true,
      });
    }
  }
}

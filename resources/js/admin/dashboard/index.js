/**
 * Admin dashboard charts (D3).
 *
 * Loaded lazily from `admin/app.js` only when a page contains
 * `[data-dashboard-chart]` elements. Each widget blade renders a container
 * plus a `<script type="application/json" data-dashboard-chart-data="...">`
 * payload; `bootstrap()` scans the DOM and renders whatever is present.
 */

import * as d3 from "d3";

const W = 800;
const H = 240;

const SEQUENTIAL = [
  "#e0e7ff",
  "#c7d2fe",
  "#a5b4fc",
  "#818cf8",
  "#6366f1",
  "#4f46e5",
  "#4338ca",
  "#3730a3",
];
const CATEGORICAL = [
  "#4f46e5",
  "#0ea5e9",
  "#f59e0b",
  "#ef4444",
  "#10b981",
  "#8b5cf6",
  "#64748b",
  "#ec4899",
];

function svg(el, width, height) {
  el.replaceChildren();
  const s = d3
    .select(el)
    .append("svg")
    .attr("viewBox", `0 0 ${width} ${height}`)
    .attr("preserveAspectRatio", "xMidYMid meet")
    .attr("role", "img")
    .style("width", "100%")
    .style("height", "auto");
  return s;
}

function noData(el) {
  const s = svg(el, W, 120);
  s.append("text")
    .attr("x", W / 2)
    .attr("y", 60)
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
  const width = W - margin.left - margin.right;
  const height = H - margin.top - margin.bottom;
  const s = svg(el, W, H);
  const g = s.append("g").attr("transform", `translate(${margin.left},${margin.top})`);
  const plot = chartContainer(s, g, width, height);

  const x = d3.scaleBand().domain(labels).range([0, width]).padding(0.25);
  const max = Math.max(...values) * 1.1 || 1;
  const y = d3.scaleLinear().domain([0, max]).range([height, 0]);
  const color = d3.scaleLinear().domain([0, max]).range([SEQUENTIAL[1], SEQUENTIAL[5]]);

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
  const width = W - margin.left - margin.right;
  const height = H - margin.top - margin.bottom;
  const s = svg(el, W, H);
  const g = s.append("g").attr("transform", `translate(${margin.left},${margin.top})`);
  const plot = chartContainer(s, g, width, height);

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

  plot
    .append("path")
    .datum(values)
    .attr("d", area)
    .attr("fill", SEQUENTIAL[3])
    .attr("opacity", 0.25);

  plot
    .append("path")
    .datum(values)
    .attr("d", line)
    .attr("fill", "none")
    .attr("stroke", SEQUENTIAL[5])
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
    .attr("fill", SEQUENTIAL[6])
    .append("title")
    .text((v, i) => `${labels[i]}: ${Number(v).toLocaleString()}`);
}

/** Doughnut: { labels: string[], values: number[] } with legend */
function renderDoughnut(el, data) {
  const labels = data.labels ?? [];
  const values = data.values ?? [];
  if (!labels.length || values.every((v) => !v)) return noData(el);

  const height = 260;
  const width = W;
  const radius = Math.min(width / 3, height / 2) - 30;
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
  const color = d3.scaleOrdinal().domain(labels).range(CATEGORICAL);

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
  const height = Math.max(140, labels.length * 24 + 20);
  const width = W - margin.left - margin.right;
  const s = svg(el, W, height + margin.top + margin.bottom);
  const g = s.append("g").attr("transform", `translate(${margin.left},${margin.top})`);
  const plot = chartContainer(s, g, width, height);

  const y = d3.scaleBand().domain(labels).range([0, height]).padding(0.3);
  const max = Math.max(...values) * 1.05 || 1;
  const x = d3.scaleLinear().domain([0, max]).range([0, width]);
  const color = d3.scaleLinear().domain([0, max]).range([SEQUENTIAL[2], SEQUENTIAL[6]]);

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

  const cell = 24;
  const gutterL = 34;
  const gutterT = 24;
  const height = gutterT + days.length * cell + 8;
  const width = W;
  const s = svg(el, width, height);
  const g = s.append("g").attr("transform", `translate(${gutterL},${gutterT})`);
  const plot = chartContainer(s, g, width - gutterL, height - gutterT - 8);

  const color = d3
    .scaleSequential(d3.interpolateRgb(SEQUENTIAL[1], SEQUENTIAL[6]))
    .domain([0, data.max || 1]);

  // hour labels (every 3h)
  g.append("g")
    .selectAll("text")
    .data(d3.range(0, 24, 3))
    .join("text")
    .attr("x", (h) => h * cell + cell / 2)
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
    .attr("y", (_, i) => i * cell + cell / 2)
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
      .attr("x", (_, hi) => hi * cell)
      .attr("y", di * cell)
      .attr("width", cell - 2)
      .attr("height", cell - 2)
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
  const legend = s.append("g").attr("transform", `translate(${gutterL},${height - 6})`);
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
}

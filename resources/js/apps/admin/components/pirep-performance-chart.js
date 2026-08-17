/**
 * PIREP Performance chart — Chart.js v4.
 * Registered as a Filament AlpineComponent, lazy-loaded via x-load /
 * x-load-src so Chart.js is only fetched on PIREP detail pages.
 *
 * Usage from blade (Alpine):
 *
 *   <div x-load
 *        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('pirep-performance-chart') }}"
 *        x-data="pirepPerformanceChart(@js($performance))">
 *     <canvas x-ref="canvas"></canvas>
 *   </div>
 */

import Chart from "chart.js/auto";
import annotationPlugin from "chartjs-plugin-annotation";
import "chartjs-adapter-date-fns";

Chart.register(annotationPlugin);

/**
 * Read a console theme token (e.g. `--primary-600`, `--ok`) as a colour
 * Chart.js can parse, falling back to the given default when the variable
 * isn't set (SSR/tests) or resolves empty.
 *
 * The normalise step is load-bearing. Most tokens here (--ok/--warn/--bad/
 * --info/--ink-3) are literal hex in theme.css, but --primary-600 comes from
 * Filament, which emits its palettes in oklch (Color::generatePalette returns
 * `oklch(0.55 0.15 277)`). Chart.js's colour parser doesn't understand oklch
 * and yields black. Painting the value into a 1x1 canvas hands the conversion
 * to the browser, so any colour space CSS accepts arrives here as rgb().
 */
function cssVar(name, fallback) {
  if (typeof document === "undefined") return fallback;

  // A probe element, not getPropertyValue(). Filament declares the palette as
  // an indirection -- `--primary-600: var(--color-600)` with `--color-600`
  // holding the real oklch -- so getPropertyValue() hands back the literal
  // text "var(--color-600)", which no colour parser resolves. Assigning to an
  // element's `color` makes the cascade resolve the chain for us.
  if (colorProbe === undefined) {
    colorProbe = document.createElement("span");
    colorProbe.style.cssText = "position:fixed;top:-9999px;left:-9999px;visibility:hidden";
    document.body.appendChild(colorProbe);
  }

  colorProbe.style.color = "";
  colorProbe.style.color = `var(${name})`;
  const value = getComputedStyle(colorProbe).color;

  return value ? toRgb(value, fallback) : fallback;
}

let colorProbe;
let colorCanvas;
function toRgb(value, fallback) {
  if (colorCanvas === undefined) {
    colorCanvas =
      document.createElement("canvas").getContext("2d", { willReadFrequently: true }) ?? null;
  }
  if (!colorCanvas) return value;

  // Reset first: an unparseable value leaves fillStyle at its previous
  // setting rather than throwing, which would silently reuse another series'
  // colour instead of falling back.
  colorCanvas.fillStyle = "#000";
  colorCanvas.fillStyle = value;
  colorCanvas.fillRect(0, 0, 1, 1);

  const [r, g, b, a] = colorCanvas.getImageData(0, 0, 1, 1).data;
  if (a === 0 && value !== "transparent") return fallback;

  return `rgb(${r}, ${g}, ${b})`;
}

/**
 * Chart type comes from the theme's own font tokens rather than a hardcoded
 * family — the panel's typeface is set in theme.css (--font-sans/--font-mono)
 * and no longer by the panel's ->font(), so naming a family here would pin
 * the charts to whatever was current when this was written.
 */
const FONT_MONO = () => cssFont("--font-mono", "ui-monospace, monospace");

function cssFont(name, fallback) {
  if (typeof document === "undefined") return fallback;
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

// Phase shading keyed off ACARS sample `status` (PirepPhase enum value).
// Codes that don't appear here render unshaded — keeps unknown / SCH from
// painting the whole chart gray. Labels come from the server payload
// (`phase.label`) so translations stay in PHP land (PirepPhase::getLabel).
// Low-alpha backgrounds so the data line stays visually dominant.
const PHASE_COLORS = {
  // Ground / pre-flight
  BST: "rgba(148, 163, 184, 0.08)", // slate — boarding
  RDT: "rgba(148, 163, 184, 0.08)", // slate — ready start
  PBT: "rgba(148, 163, 184, 0.10)", // slate — pushback
  OFB: "rgba(148, 163, 184, 0.10)", // slate — departed gate
  TXI: "rgba(168, 162, 158, 0.10)", // stone — taxi
  DIR: "rgba(148, 163, 184, 0.08)", // slate — ready deice
  DIC: "rgba(148, 163, 184, 0.08)", // slate — deicing

  // Departure
  TOF: "rgba(20, 184, 166, 0.14)", // teal — takeoff (emphasized)
  ICL: "rgba(20, 184, 166, 0.10)", // teal — initial climb
  TKO: "rgba(20, 184, 166, 0.08)", // teal — airborne

  // Cruise
  ENR: "rgba(6, 126, 193, 0.06)", // blue — enroute / cruise

  // Approach / arrival
  APR: "rgba(245, 158, 11, 0.08)", // amber — approach
  TEN: "rgba(245, 158, 11, 0.08)", // amber — approach (legacy)
  FIN: "rgba(245, 158, 11, 0.10)", // amber — on final
  LDG: "rgba(239, 68, 68, 0.10)", // red — landing (emphasized)
  LAN: "rgba(239, 68, 68, 0.08)", // red — landed
  ONB: "rgba(148, 163, 184, 0.08)", // slate — on block
  ARR: "rgba(148, 163, 184, 0.08)", // slate — arrived

  // Non-normal
  GRT: "rgba(239, 68, 68, 0.10)", // red — ground return
  DV: "rgba(245, 158, 11, 0.12)", // amber — diverted
  EMG: "rgba(220, 38, 38, 0.16)", // red bold — emergency
  PSD: "rgba(107, 114, 128, 0.06)", // gray — paused
};

const SERIES = {
  altitude: {
    label: "Altitude",
    color: cssVar("--primary-600", "#067ec1"),
    unit: "ft",
    pick: (s) => s.series.altitude.data,
  },
  speed: {
    label: "Ground speed",
    color: cssVar("--info", "#14b8a6"),
    unit: "kt",
    pick: (s) => s.series.speed.gs,
  },
  fuel: {
    label: "Fuel remaining",
    color: cssVar("--warn", "#f59e0b"),
    unit: "lbs",
    pick: (s) => s.series.fuel.data,
  },
  vs: {
    label: "Vertical speed",
    color: cssVar("--bad", "#8b5cf6"),
    unit: "fpm",
    pick: (s) => s.series.vs.data,
  },
};

export default function pirepPerformanceChart(payload) {
  let chartInstance = null;
  let observer = null;

  return {
    payload,
    active: "altitude",

    init() {
      if (!this.payload) return;

      // Watch for canvas removal (e.g. Livewire morphing a parent).
      // Chart.js's rAF loop will throw "save on null" if the canvas
      // disappears mid-draw — stop() prevents that.
      observer = new MutationObserver(() => {
        const canvas = this.$refs.canvas;
        if (!canvas && chartInstance) {
          chartInstance.stop();
          chartInstance = null;
        }
      });
      observer.observe(this.$el.parentElement, {
        childList: true,
        subtree: true,
        attributes: true,
      });

      this.render();
    },

    select(key) {
      if (key === this.active) return;
      this.active = key;
      this.render();
    },

    /**
     * Build chartjs-plugin-annotation `box` entries for each detected flight
     * phase (climb/cruise/descent). Drawn behind the data line via
     * `drawTime: 'beforeDatasetsDraw'` so the series stays visually dominant.
     */
    buildPhaseAnnotations() {
      const phases = this.payload?.phases ?? [];
      const annotations = {};

      phases.forEach((phase, idx) => {
        const color = PHASE_COLORS[phase.code];
        if (!color) return;

        annotations[`phase-${idx}`] = {
          type: "box",
          xMin: phase.start * 1000,
          xMax: phase.end * 1000,
          backgroundColor: color,
          borderWidth: 0,
          drawTime: "beforeDatasetsDraw",
          label: {
            display: idx === 0 || phases[idx - 1]?.code !== phase.code,
            content: phase.label,
            position: { x: "start", y: "start" },
            font: { family: FONT_MONO(), size: 9, weight: "500" },
            color: cssVar("--ink-3", "#6b7280"),
            backgroundColor: "transparent",
            padding: { top: 4, left: 6 },
          },
        };
      });

      return annotations;
    },

    render() {
      const cfg = SERIES[this.active];
      const data = cfg.pick(this.payload).filter(([, v]) => v !== null);

      const canvas = this.$refs.canvas;
      if (!canvas) return;

      const ctx = canvas.getContext("2d");
      if (!ctx) return;

      if (chartInstance) {
        chartInstance.stop();

        const ds = chartInstance.data.datasets[0];
        ds.label = cfg.label;
        ds.borderColor = cfg.color;
        ds.backgroundColor = `${cfg.color}22`;
        ds.data = data.map(([t, v]) => ({ x: t * 1000, y: v }));

        chartInstance.options.scales.y.ticks.callback = (v) =>
          cfg.unit === "ft" ? (v / 1000).toFixed(0) + "k" : v.toLocaleString();
        chartInstance.options.plugins.tooltip.callbacks.label = (ctx) =>
          ` ${ctx.parsed.y.toLocaleString()} ${cfg.unit}`;
        chartInstance.options.plugins.annotation.annotations = this.buildPhaseAnnotations();

        chartInstance.update("none");
        return;
      }

      chartInstance = new Chart(ctx, {
        type: "line",
        data: {
          datasets: [
            {
              label: cfg.label,
              borderColor: cfg.color,
              backgroundColor: `${cfg.color}22`,
              data: data.map(([t, v]) => ({ x: t * 1000, y: v })),
              fill: true,
              tension: 0.25,
              pointRadius: 0,
              pointHoverRadius: 4,
              borderWidth: 2,
            },
          ],
        },
        options: {
          responsive: false,
          animation: false,
          maintainAspectRatio: false,
          interaction: { mode: "index", intersect: false },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => ` ${ctx.parsed.y.toLocaleString()} ${cfg.unit}`,
                title: (items) => new Date(items[0].parsed.x).toISOString().slice(11, 19) + "Z",
              },
            },
            annotation: {
              annotations: this.buildPhaseAnnotations(),
            },
          },
          scales: {
            x: {
              type: "time",
              time: { unit: "minute" },
              grid: { display: false },
              ticks: {
                color: cssVar("--ink-3", "#9ba3af"),
                font: { family: FONT_MONO(), size: 10 },
              },
            },
            y: {
              grid: { color: cssVar("--line", "#eef1f4"), drawTicks: false },
              ticks: {
                color: cssVar("--ink-3", "#9ba3af"),
                font: { family: FONT_MONO(), size: 10 },
                callback: (v) =>
                  cfg.unit === "ft" ? (v / 1000).toFixed(0) + "k" : v.toLocaleString(),
              },
            },
          },
        },
      });

      chartInstance.stop();
    },
  };
}

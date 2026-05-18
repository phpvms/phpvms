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

// Phase shading keyed off ACARS sample `status` (PirepStatus enum value).
// Codes that don't appear here render unshaded — keeps unknown / SCH from
// painting the whole chart gray. Labels come from the server payload
// (`phase.label`) so translations stay in PHP land (PirepStatus::getLabel).
// Low-alpha backgrounds so the data line stays visually dominant.
const PHASE_COLORS = {
  // Ground / pre-flight
  BST: "rgba(148, 163, 184, 0.08)",  // slate — boarding
  RDT: "rgba(148, 163, 184, 0.08)",  // slate — ready start
  PBT: "rgba(148, 163, 184, 0.10)",  // slate — pushback
  OFB: "rgba(148, 163, 184, 0.10)",  // slate — departed gate
  TXI: "rgba(168, 162, 158, 0.10)",  // stone — taxi
  DIR: "rgba(148, 163, 184, 0.08)",  // slate — ready deice
  DIC: "rgba(148, 163, 184, 0.08)",  // slate — deicing

  // Departure
  TOF: "rgba(20, 184, 166, 0.14)",   // teal — takeoff (emphasized)
  ICL: "rgba(20, 184, 166, 0.10)",   // teal — initial climb
  TKO: "rgba(20, 184, 166, 0.08)",   // teal — airborne

  // Cruise
  ENR: "rgba(6, 126, 193, 0.06)",    // blue — enroute / cruise

  // Approach / arrival
  APR: "rgba(245, 158, 11, 0.08)",   // amber — approach
  TEN: "rgba(245, 158, 11, 0.08)",   // amber — approach (legacy)
  FIN: "rgba(245, 158, 11, 0.10)",   // amber — on final
  LDG: "rgba(239, 68, 68, 0.10)",    // red — landing (emphasized)
  LAN: "rgba(239, 68, 68, 0.08)",    // red — landed
  ONB: "rgba(148, 163, 184, 0.08)",  // slate — on block
  ARR: "rgba(148, 163, 184, 0.08)",  // slate — arrived

  // Non-normal
  GRT: "rgba(239, 68, 68, 0.10)",    // red — ground return
  DV:  "rgba(245, 158, 11, 0.12)",   // amber — diverted
  EMG: "rgba(220, 38, 38, 0.16)",    // red bold — emergency
  PSD: "rgba(107, 114, 128, 0.06)",  // gray — paused
};

const SERIES = {
  altitude: {
    label: "Altitude",
    color: "#067ec1",
    unit: "ft",
    pick: (s) => s.series.altitude.data,
  },
  speed: {
    label: "Ground speed",
    color: "#14b8a6",
    unit: "kt",
    pick: (s) => s.series.speed.gs,
  },
  fuel: {
    label: "Fuel remaining",
    color: "#f59e0b",
    unit: "lbs",
    pick: (s) => s.series.fuel.data,
  },
  vs: {
    label: "Vertical speed",
    color: "#8b5cf6",
    unit: "fpm",
    pick: (s) => s.series.vs.data,
  },
};

export default function pirepPerformanceChart(payload) {
  return {
    payload,
    active: "altitude",
    chart: null,

    init() {
      if (!this.payload) return;
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
            font: { family: "Geist Mono", size: 9, weight: "500" },
            color: "#6b7280",
            backgroundColor: "transparent",
            padding: { top: 4, left: 6 },
          },
        };
      });

      return annotations;
    },

    render() {
      const cfg = SERIES[this.active];
      const data = cfg.pick(this.payload).filter(([t, v]) => v !== null);

      if (this.chart) this.chart.destroy();

      this.chart = new Chart(this.$refs.canvas, {
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
          responsive: true,
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
              ticks: { color: "#9ba3af", font: { family: "Geist Mono", size: 10 } },
            },
            y: {
              grid: { color: "#eef1f4", drawTicks: false },
              ticks: {
                color: "#9ba3af",
                font: { family: "Geist Mono", size: 10 },
                callback: (v) =>
                  cfg.unit === "ft" ? (v / 1000).toFixed(0) + "k" : v.toLocaleString(),
              },
            },
          },
        },
      });
    },
  };
};

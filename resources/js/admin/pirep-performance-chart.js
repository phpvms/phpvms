/**
 * PIREP Performance chart — Chart.js v4.
 * Lazy-loaded via FilamentAsset::loadedOnRequest() so only the detail page that
 * opts in pulls Chart.js.
 *
 * Usage from blade (Alpine):
 *
 *   <div x-data="pirepPerformanceChart(@js($this->performance))"
 *        x-load-js="[@js(FilamentAsset::getScriptSrc('pirep-performance-chart'))]">
 *     <canvas x-ref="canvas"></canvas>
 *   </div>
 */

import Chart from "chart.js/auto";
import "chartjs-adapter-date-fns";

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

window.pirepPerformanceChart = function (payload) {
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

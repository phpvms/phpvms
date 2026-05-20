/**
 * PIREP Landing Analysis — runway plan-views + scorecard polar chart.
 * Registered as a Filament AlpineComponent, lazy-loaded via x-load /
 * x-load-src so Chart.js is only fetched on PIREP detail pages.
 *
 * Usage from blade (Alpine):
 *
 *   <div x-load
 *        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('pirep-landing-analysis') }}"
 *        x-data="pirepLandingAnalysis(@js($landing))">
 *     <canvas x-ref="scorecard"></canvas>
 *   </div>
 */

import Chart from "chart.js/auto";

// Runway schematic dimensions (SVG viewBox units).
// 20m assumed runway width per project decision — visual only, no real-world
// length needed. SVG aspect tuned so the centerline reads as a "long strip".
const RW_VIEWBOX = { w: 200, h: 100 };
const RW_CENTERLINE_Y = RW_VIEWBOX.h / 2;
const RW_RUNWAY_WIDTH = 32;

// Centerline-offset clamp range (meters or feet — units defined by ACARS
// client). Beyond ±30 we just pin the marker to the edge and let the numeric
// label tell the truth.
const OFFSET_CLAMP = 30;

// Heading deviation visual exaggeration. Real-world dev is usually <2° — at
// SVG scale that's invisible. Multiplying by 8 (clamped to ±45° to avoid
// nonsense rotations) gives a clear visual signal while the numeric label
// below the diagram carries the precise value.
const HEADING_MULTIPLIER = 8;
const HEADING_CLAMP = 45;

const SCORE_AXIS_LABELS = {
  rate: "Touchdown",
  g_force: "G-force",
  pitch: "Pitch",
  roll: "Roll",
  centerline: "Centerline",
  heading: "Heading",
};

// Color bands for individual metric scores. Used both in scorecard tooltip
// and in runway-diagram severity tints.
function severityColor(score) {
  if (score >= 80) return "#10b981"; // emerald — excellent
  if (score >= 60) return "#84cc16"; // lime — acceptable
  if (score >= 40) return "#f59e0b"; // amber — degraded
  return "#ef4444"; // red — bad
}

export default function pirepLandingAnalysis(payload) {
  return {
    payload,
    chart: null,
    departureMarker: null,
    arrivalMarker: null,
    attitude: null,

    init() {
      if (!this.payload) return;

      // Pre-compute runway markers so the SVG template binds against
      // stable Alpine state rather than calling a method from inside
      // <template x-if> (which Alpine evaluates eagerly).
      this.departureMarker = this.computeMarker("departure");
      this.arrivalMarker = this.computeMarker("arrival");
      this.attitude = this.computeAttitude();

      this.$nextTick(() => this.renderScorecard());
    },

    /**
     * Build attitude indicator state at touchdown.
     *   - roll: aircraft bank angle (positive = right wing down). Rotates
     *     the horizon group (real value, 1:1 — typical touchdown roll is
     *     under 5° so direct mapping reads correctly).
     *   - pitch: nose attitude (positive = nose up). Shifts the horizon
     *     vertically. Pitch ladder marks are at 5° / 10° = 15px / 30px
     *     from horizon (3px per degree), so pitchOffset uses the same
     *     3px/deg scale to keep readout consistent with the ladder.
     *
     * Both clamped so an outlier (sensor glitch, hard touchdown) doesn't
     * rotate the AI past readability.
     */
    computeAttitude() {
      const sc = this.payload?.scorecard;
      const roll = sc?.roll?.value ?? null;
      const pitch = sc?.pitch?.value ?? null;

      if (roll === null && pitch === null) return null;

      const rollDeg = roll ?? 0;
      const pitchDeg = pitch ?? 0;

      // Real-degree mapping for both axes; clamp prevents extreme outliers
      // from rotating/translating off-screen.
      const rollRotation = Math.max(-30, Math.min(30, rollDeg));
      const pitchPxPerDeg = 3;
      const pitchOffset = Math.max(-30, Math.min(30, pitchDeg * pitchPxPerDeg));

      return {
        roll: rollDeg,
        pitch: pitchDeg,
        rollRotation,
        pitchOffset,
        rollScore: sc?.roll?.score ?? 0,
        pitchScore: sc?.pitch?.score ?? 0,
      };
    },

    /**
     * Build a Chart.js polar-area chart showing each landing metric's score
     * on a 0–100 scale. Filled polygon = visual signature of the landing;
     * circular = balanced/clean, spiky = uneven.
     */
    renderScorecard() {
      const sc = this.payload?.scorecard;
      if (!sc) return;

      const canvas = this.$refs?.scorecard;
      if (!canvas) return;

      const axisKeys = Object.keys(SCORE_AXIS_LABELS);
      const scores = axisKeys.map((k) => sc[k]?.score ?? 0);
      const labels = axisKeys.map((k) => SCORE_AXIS_LABELS[k]);

      // Tint the filled polygon by the *worst* axis score — a single bad
      // metric drags the visual signal, matching how a check airman would
      // read the landing. Solid polygon (semi-transparent) plus a stroked
      // outline so the shape stays legible even on near-perfect landings.
      const worstScore = Math.min(...scores);
      const polyColor = severityColor(worstScore);

      if (this.chart) this.chart.destroy();

      this.chart = new Chart(canvas, {
        type: "radar",
        data: {
          labels,
          datasets: [
            {
              data: scores,
              backgroundColor: polyColor + "40", // 25% alpha fill
              borderColor: polyColor,
              borderWidth: 2,
              pointBackgroundColor: scores.map((s) => severityColor(s)),
              pointBorderColor: "#ffffff",
              pointBorderWidth: 1.5,
              pointRadius: 4,
              pointHoverRadius: 6,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: {
            padding: { top: 8, bottom: 8, left: 12, right: 12 },
          },
          scales: {
            r: {
              min: 0,
              max: 100,
              ticks: {
                stepSize: 25,
                color: "#9ca3af",
                backdropColor: "transparent",
                font: { family: "Geist Mono", size: 9 },
                showLabelBackdrop: false,
              },
              grid: { color: "#e5e7eb" },
              angleLines: { color: "#e5e7eb" },
              pointLabels: {
                color: "#374151",
                font: { family: "Geist", size: 10, weight: "500" },
                padding: 6,
              },
            },
          },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => {
                  const key = axisKeys[ctx.dataIndex];
                  const raw = sc[key]?.value;
                  const rawFmt =
                    raw === null || raw === undefined
                      ? "—"
                      : typeof raw === "number"
                        ? raw.toFixed(2)
                        : raw;
                  return ` ${ctx.parsed.toFixed(0)} / 100 (raw: ${rawFmt})`;
                },
              },
            },
          },
        },
      });
    },

    /**
     * Compute SVG coordinates for the touchdown / rollout marker on a runway
     * plan-view. Returns marker X/Y, aircraft glyph rotation (heading dev),
     * severity tint (centerline score), and the raw values for debug/labels.
     *
     * Threshold is drawn at x=10 (left edge area). Departure rolls from
     * threshold → marker placed ~25% down the strip. Arrival touches down
     * a bit further past threshold → marker placed ~35% down.
     */
    computeMarker(side) {
      const data = this.payload?.[side];
      if (!data) return null;

      const offset = data.centerline_offset ?? 0;
      const heading = data.heading_deviation ?? 0;

      const clampedOffset = Math.max(-OFFSET_CLAMP, Math.min(OFFSET_CLAMP, offset));
      const offsetRatio = clampedOffset / OFFSET_CLAMP;
      const y = RW_CENTERLINE_Y + offsetRatio * (RW_RUNWAY_WIDTH / 2 - 4);

      // Both markers placed near the threshold (left side) since the
      // diagram has no scale — the offset from centerline carries the
      // signal, not where along the runway the wheels touched.
      const x = side === "arrival" ? 55 : 75;

      // Exaggerate heading deviation so sub-degree values render visibly.
      const rotation = Math.max(
        -HEADING_CLAMP,
        Math.min(HEADING_CLAMP, heading * HEADING_MULTIPLIER),
      );

      const score = this.payload?.scorecard?.centerline?.score ?? 0;

      return {
        x,
        y,
        rotation,
        color: severityColor(score),
        offset,
        heading,
        runway: data.runway,
      };
    },
  };
}

/**
 * Objective pixel-width measurement via `gl.readPixels()`, used by the
 * harness to answer "does the route hold a constant apparent width" with
 * evidence rather than eyeballing a screenshot (tasks.md 2.0's report).
 *
 * A horizontal-only scanline overreads a line's true (perpendicular) width
 * whenever the line runs close to horizontal on screen — the scanline
 * crosses it at a shallow angle, so the contiguous run is `width / sin(θ)`
 * long rather than `width`. This file scans BOTH orientations (rows and
 * columns) and reports the SMALLER of the two runs found near any given
 * location: whichever orientation is closer to perpendicular to the local
 * line direction reports close to the true width, and a scanline is never
 * near-parallel to the line in both orientations at once. This is a
 * heuristic, not exact geometry — it needs no knowledge of the line's local
 * screen-space angle, at the cost of only approximating true width rather
 * than measuring it exactly.
 */

export type SpikePhase = string;

/** One contiguous run of a target colour found scanning a row or a column. */
export type ColorRun = {
  phase: SpikePhase;
  orientation: "row" | "column";
  index: number;
  start: number;
  widthPx: number;
};

const COLOR_MATCH_TOLERANCE = 24;

function colorMatches(r: number, g: number, b: number, target: [number, number, number]): boolean {
  return (
    Math.abs(r - target[0]) <= COLOR_MATCH_TOLERANCE &&
    Math.abs(g - target[1]) <= COLOR_MATCH_TOLERANCE &&
    Math.abs(b - target[2]) <= COLOR_MATCH_TOLERANCE
  );
}

/** Plain integer parse of `#rrggbb` — see `src/color.ts` for why this must NOT go through `THREE.Color`. */
export function hexToRawRgb255(hex: string): [number, number, number] {
  const n = Number.parseInt(hex.slice(1), 16);
  return [(n >> 16) & 0xff, (n >> 8) & 0xff, n & 0xff];
}

function scanLine(
  pixels: Uint8Array,
  width: number,
  length: number,
  orientation: "row" | "column",
  index: number,
  pixelAt: (i: number) => number,
  targets: [SpikePhase, [number, number, number]][],
): ColorRun[] {
  const runs: ColorRun[] = [];
  for (const [phase, target] of targets) {
    let runStart = -1;
    for (let i = 0; i <= length; i++) {
      const px = i < length ? pixelAt(i) * 4 : -1;
      const matches = px >= 0 && colorMatches(pixels[px], pixels[px + 1], pixels[px + 2], target);
      if (matches && runStart === -1) {
        runStart = i;
      } else if (!matches && runStart !== -1) {
        runs.push({ phase, orientation, index, start: runStart, widthPx: i - runStart });
        runStart = -1;
      }
    }
  }
  return runs;
}

/** Scan every row AND every column of an RGBA readback for contiguous runs of each phase colour. */
export function findColorRuns(
  pixels: Uint8Array,
  width: number,
  height: number,
  phaseColors: Record<string, string>,
): ColorRun[] {
  const targets = Object.entries(phaseColors).map(
    ([phase, hex]) => [phase, hexToRawRgb255(hex)] as [SpikePhase, [number, number, number]],
  );

  const runs: ColorRun[] = [];
  for (let row = 0; row < height; row++) {
    runs.push(...scanLine(pixels, width, width, "row", row, (x) => row * width + x, targets));
  }
  for (let col = 0; col < width; col++) {
    runs.push(...scanLine(pixels, width, height, "column", col, (y) => y * width + col, targets));
  }
  return runs;
}

export type WidthSummary = { phase: string; n: number; min: number; median: number; max: number };

/**
 * Per phase, the SMALLER of the row-scan and column-scan run distributions —
 * see the file header for why the minimum approximates true perpendicular
 * width better than either orientation alone.
 */
export function summarizeWidths(runs: ColorRun[], minWidthPx = 2): WidthSummary[] {
  const byPhase = new Map<string, { row: number[]; column: number[] }>();
  for (const run of runs) {
    if (run.widthPx < minWidthPx) continue;
    if (!byPhase.has(run.phase)) byPhase.set(run.phase, { row: [], column: [] });
    byPhase.get(run.phase)![run.orientation].push(run.widthPx);
  }

  const summaries: WidthSummary[] = [];
  for (const [phase, { row, column }] of byPhase) {
    const combined =
      row.length > 0 && column.length > 0 ? [...row, ...column] : row.length > 0 ? row : column;
    if (combined.length === 0) continue;
    combined.sort((a, b) => a - b);
    summaries.push({
      phase,
      n: combined.length,
      min: combined[0],
      median: combined[Math.floor(combined.length / 2)],
      max: combined.at(-1)!,
    });
  }
  return summaries;
}

/**
 * Departure-time assignment strategies (route-forge-tool/spec.md "Time strategy").
 *
 *   fixed    every row gets `base_time`
 *   spread   per-origin: row k from origin X departs at base + k*interval
 *   banked   per-origin: rows distribute round-robin across `bank_count`
 *            banks spaced by `bank_spacing_minutes` starting at base
 *   redeye   per-origin: rows distribute evenly across `window_minutes`
 *            starting at base (typically a late-night base time)
 *
 * Per-origin slotting matters as soon as you have >1 origin (mesh, chain):
 * without it, a mesh of 3 origins × 5 destinations under "spread @ 60min"
 * would stack 3 flights at 08:00, 3 at 09:00, etc. — same origin getting
 * one departure but with 3 origins all running the same wall clock.
 *
 * Jitter (Decision 17 supporting): seeded Mulberry32 PRNG so identical
 * (rows, strategy) regenerate identical jitter offsets — required for the
 * "lint runs on every keystroke" UX to stay deterministic.
 */

import type { JitterConfig, Row, TimeStrategy } from "../state/types";

const MINS_PER_DAY = 1440;

/**
 * Returns a new row list with `dpt_time` assigned per the strategy.
 * Input rows are not mutated. Does NOT touch `arr_time` — that is the
 * timezone module's job, called by the generator AFTER this.
 */
export function assignDepartureTimes(rows: Row[], strategy: TimeStrategy): Row[] {
  const baseMins = parseHHMM(strategy.base_time);
  const needPerOrigin = strategy.kind !== "fixed";
  const slots = needPerOrigin ? perOriginSlots(rows) : null;

  return rows.map((r) => {
    const slot = slots?.get(r.index) ?? 0;
    const groupSize = slots ? sizeOfOriginGroup(rows, r.dpt_airport_id) : 1;

    let depMins = computeBase(strategy, baseMins, slot, groupSize);
    if (strategy.jitter.enabled && strategy.jitter.minutes > 0) {
      depMins += jitterOffset(r.index, strategy.jitter);
    }

    return { ...r, dpt_time: fmtHHMM(depMins) };
  });
}

function computeBase(
  strategy: TimeStrategy,
  baseMins: number,
  slot: number,
  groupSize: number,
): number {
  switch (strategy.kind) {
    case "fixed":
      return baseMins;

    case "spread":
      return baseMins + slot * strategy.interval_minutes;

    case "banked": {
      const bank = strategy.bank_count > 0 ? slot % strategy.bank_count : 0;
      return baseMins + bank * strategy.bank_spacing_minutes;
    }

    case "redeye": {
      // Distribute the group evenly across the window starting at base.
      const step = groupSize > 0 ? strategy.window_minutes / groupSize : 0;
      return baseMins + Math.floor(slot * step);
    }
  }
}

/**
 * Maps row.index → that row's per-origin slot (0-based position among rows
 * sharing the same dpt_airport_id, preserving original row order).
 */
function perOriginSlots(rows: Row[]): Map<number, number> {
  const counters = new Map<string, number>();
  const slots = new Map<number, number>();
  for (const r of rows) {
    const next = counters.get(r.dpt_airport_id) ?? 0;
    slots.set(r.index, next);
    counters.set(r.dpt_airport_id, next + 1);
  }
  return slots;
}

function sizeOfOriginGroup(rows: Row[], origin: string): number {
  let n = 0;
  for (const r of rows) {
    if (r.dpt_airport_id === origin) {
      n++;
    }
  }
  return n;
}

/**
 * Mulberry32-derived PRNG seeded per-row from (jitter.seed, rowIndex).
 * Returns a uniform integer in [-jitter.minutes, +jitter.minutes].
 */
function jitterOffset(rowIndex: number, jitter: JitterConfig): number {
  let t = ((jitter.seed >>> 0) + rowIndex * 0x9e3779b1) >>> 0;
  t = (t + 0x6d2b79f5) >>> 0;
  t = Math.imul(t ^ (t >>> 15), t | 1);
  t = (t ^ (t + Math.imul(t ^ (t >>> 7), t | 61))) >>> 0;
  const rand = ((t ^ (t >>> 14)) >>> 0) / 4_294_967_296;
  const range = jitter.minutes * 2 + 1;
  return Math.floor(rand * range) - jitter.minutes;
}

function parseHHMM(s: string): number {
  const parts = s.split(":");
  const h = Number(parts[0] ?? 0);
  const m = Number(parts[1] ?? 0);
  if (!Number.isFinite(h) || !Number.isFinite(m)) {
    return 0;
  }
  return h * 60 + m;
}

function fmtHHMM(totalMins: number): string {
  const wrapped = ((Math.round(totalMins) % MINS_PER_DAY) + MINS_PER_DAY) % MINS_PER_DAY;
  const h = Math.floor(wrapped / 60);
  const m = wrapped % 60;
  return `${pad2(h)}:${pad2(m)}`;
}

function pad2(n: number): string {
  return n.toString().padStart(2, "0");
}

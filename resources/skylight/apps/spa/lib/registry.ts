/**
 * Slot registry — the headless core (Fumadocs lesson): DATA + pure resolvers,
 * ZERO Vue imports, so the extension contract stays serializable and portable.
 *
 * A `PvSlot` reads this registry, filters by `slot`, renders each entry's
 * `component` (resolved to a real Vue component by a ComponentResolver map,
 * kept OUT of this file) in ascending `order`, and resolves `@`-prefixed prop
 * refs against the page's DTO props.
 */

/** One registered slot entry. `component` is a NAME resolved by a resolver map. */
export interface SlotEntry {
  /** Slot name this entry fills, e.g. "dashboard.sidebar". */
  slot: string;
  /** Component name, looked up in the ComponentResolver (e.g. "AcmeSlotWidget"). */
  component: string;
  /** Ascending render order within the slot. */
  order: number;
  /** Props to pass; string values starting with `@` are DTO-prop refs. */
  props?: Record<string, unknown>;
  /**
   * Optional runtime ESM URL for a server-provided slot component. When present,
   * the app registers `component` → async import(module) in the resolver.
   */
  module?: string;
}

/**
 * A resolver maps a component NAME to a value (a Vue component). Typed as
 * `unknown` here so this module never imports Vue — the concrete map lives in
 * `apps/spa/components/widgets` and is provided at the app root.
 */
export type ComponentResolver = Record<string, unknown>;

/**
 * The first-party slot registry (data only). Empty by default — the old
 * `dashboard.sidebar` weather entry was retired when weather became a
 * `phpvms/phpvms-dashboard` addon dashboard widget. Addons contribute slot
 * entries at runtime via the server-merged registry.
 */
export const registry: SlotEntry[] = [];

/** Entries for a slot, sorted by ascending order (pure). */
export function entriesForSlot(reg: SlotEntry[], slot: string): SlotEntry[] {
  return reg.filter((e) => e.slot === slot).sort((a, b) => a.order - b.order);
}

/**
 * Resolve one prop value: a string starting with `@` is looked up in
 * `contextProps` (the DTO), otherwise returned as-is. Pure.
 */
export function resolveValue(value: unknown, contextProps: Record<string, unknown>): unknown {
  if (typeof value === "string" && value.startsWith("@")) {
    return contextProps[value.slice(1)];
  }
  return value;
}

/** Resolve all props for an entry against the DTO. Pure. */
export function resolveEntryProps(
  entry: SlotEntry,
  contextProps: Record<string, unknown>,
): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [key, raw] of Object.entries(entry.props ?? {})) {
    out[key] = resolveValue(raw, contextProps);
  }
  return out;
}

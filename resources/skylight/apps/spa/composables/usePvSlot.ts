import { inject, provide, type InjectionKey } from 'vue'
import type { SlotEntry, ComponentResolver } from '@/lib/registry'

/**
 * Slot-context injection keys. `PvApp` provides all three at the app root so
 * every `PvSlot` (and widget) can `inject` them with no prop-drilling.
 */
export const RegistryKey: InjectionKey<SlotEntry[]> = Symbol('pv.registry')
export const ResolverKey: InjectionKey<ComponentResolver> = Symbol('pv.resolver')
export const PagePropsKey: InjectionKey<Record<string, unknown>> = Symbol('pv.pageProps')

/** Provide slot context (called by PvApp). */
export function providePvContext(
  registry: SlotEntry[],
  resolver: ComponentResolver,
  pageProps: Record<string, unknown>,
): void {
  provide(RegistryKey, registry)
  provide(ResolverKey, resolver)
  provide(PagePropsKey, pageProps)
}

/** Consume slot context (called by PvSlot). Fails visible via empty defaults. */
export function usePvContext() {
  return {
    registry: inject(RegistryKey, [] as SlotEntry[]),
    resolver: inject(ResolverKey, {} as ComponentResolver),
    pageProps: inject(PagePropsKey, {} as Record<string, unknown>),
  }
}

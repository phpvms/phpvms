<script setup lang="ts">
import { defineAsyncComponent, defineComponent, h, type Component } from "vue";
import { usePage } from "@inertiajs/vue3";
import PvLayout from "./PvLayout.vue";
import NavRail from "@/components/chrome/NavRail.vue";
import TopBar from "@/components/chrome/TopBar.vue";
import FlashToasts from "@/components/chrome/FlashToasts.vue";
import { registry, type SlotEntry, type ComponentResolver } from "@/lib/registry";
import { widgetComponents } from "@/components/widgets";
import { dashboardWidgets } from "@/components/widgets/dashboard";
import { mergeServerWidgets, type WidgetDef } from "@/lib/widgets/catalog";
import { providePvContext } from "@/composables/usePvSlot";

/**
 * Small inline fail-visible box for a slot module that fails/404s to import.
 * A disabled or unreachable ESM slot must render this bordered role="alert"
 * diagnostic, never a blank gap.
 */
function slotErrorComponent(name: string): Component {
  return defineComponent({
    name: "SlotResolveError",
    setup() {
      return () =>
        h(
          "div",
          {
            role: "alert",
            "data-pv-slot-failed": name,
            style: {
              background: "var(--pv-slot-error-bg)",
              border: "1px solid var(--pv-slot-error-border)",
              borderRadius: "var(--pv-radius-sm)",
              color: "var(--pv-slot-error-text)",
              fontFamily: "var(--pv-font-mono)",
              fontSize: "12px",
              padding: "6px 8px",
            },
          },
          [h("strong", "Extension failed to load: "), h("code", name)],
        );
    },
  });
}

/**
 * The Inertia PERSISTENT layout: wraps every page, survives navigation. Owns the
 * Workspace chrome (nav rail + top bar) and provides the slot registry, the
 * component resolver, and the current page DTO so any PvSlot/widget can inject
 * them without prop-drilling. NavRail + TopBar self-source from shared props.
 *
 * Also the server-extension entry point: it merges `page.props.skylight`
 * (widgets + slots produced by the extension-hub middleware) into the catalog
 * and slot registry BEFORE any child page reads them. Always guarded — the prop
 * is absent on pages without the middleware.
 *
 * Pages opt in with `Page.layout = PvApp`.
 */
const page = usePage();

const skylight = page.props.skylight as { widgets?: WidgetDef[]; slots?: SlotEntry[] } | undefined;

// Merge server widgets into the catalog before any page/composable reads it.
mergeServerWidgets(skylight?.widgets ?? []);

// Merge server slot entries into the first-party registry.
const serverSlots: SlotEntry[] = skylight?.slots ?? [];
const mergedRegistry: SlotEntry[] = [...registry, ...serverSlots];

// Merge resolver: bundled widget components + async components for any server
// slot entry that ships a runtime ESM `module` URL.
const asyncSlotComponents: ComponentResolver = {};
for (const entry of serverSlots) {
  if (entry.module) {
    const url = entry.module;
    const name = entry.component;
    asyncSlotComponents[name] = defineAsyncComponent({
      loader: () => import(/* @vite-ignore */ url),
      errorComponent: slotErrorComponent(name),
      timeout: 10000,
      onError(_error, _retry, fail) {
        // A disabled/404 slot module will never resolve — fail fast so the
        // errorComponent renders the visible box instead of a blank slot.
        fail();
      },
    });
  }
}
// Merge order: bundled slot components (currently none) + the bundled dashboard
// widget map (just RouteWidget now) + async addon modules. Async entries win
// last so a server module overrides a same-named bundled component intentionally.
const mergedResolver: ComponentResolver = {
  ...widgetComponents,
  ...dashboardWidgets,
  ...asyncSlotComponents,
};

// Provide the LIVE reactive DTO so @-ref resolution in slots survives navigation.
providePvContext(mergedRegistry, mergedResolver, page.props as Record<string, unknown>);
</script>

<template>
  <PvLayout>
    <template #navigation>
      <NavRail />
    </template>
    <template #header>
      <TopBar />
    </template>
    <template #main>
      <slot />
    </template>
  </PvLayout>
  <FlashToasts />
</template>

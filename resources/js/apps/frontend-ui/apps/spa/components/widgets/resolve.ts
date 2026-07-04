import { defineAsyncComponent, defineComponent, h, type Component } from "vue";
import type { WidgetDef } from "@/lib/widgets/catalog";
import { dashboardWidgets } from "./dashboard";
import BladeWidget from "./BladeWidget.vue";

/**
 * Two-tier widget resolver. Turns a serializable WidgetDef into the concrete
 * Vue component + props to render inside a WidgetFrame:
 *
 *  1. `kind: 'blade'`      → BladeWidget host shell (endpoint + mode).
 *  2. `kind: 'vue'` (default):
 *       a. `component` in the bundled `dashboardWidgets` map → that component.
 *       b. `module` runtime ESM URL → an async component (import at runtime).
 *  3. anything unresolved → a fail-visible inline error box (never a blank).
 */

/** Inline, fail-visible diagnostic — mirrors PvSlot's unresolved-slot box. */
export function widgetErrorComponent(def: WidgetDef): Component {
  const label = def.title || def.id;
  return defineComponent({
    name: "WidgetResolveError",
    setup() {
      return () =>
        h(
          "div",
          {
            role: "alert",
            "data-pv-widget-failed": def.id,
            style: {
              background: "var(--pv-slot-error-bg)",
              border: "1px solid var(--pv-slot-error-border)",
              borderRadius: "var(--pv-radius-sm)",
              color: "var(--pv-slot-error-text)",
              fontFamily: "var(--pv-font-mono)",
              fontSize: "12px",
              padding: "8px 10px",
            },
          },
          [
            h("strong", "Widget failed to load: "),
            h("code", label),
            " — this widget could not be displayed.",
          ],
        );
    },
  });
}

export interface ResolvedWidget {
  component: Component;
  props: Record<string, unknown>;
}

export function resolveWidget(def: WidgetDef): ResolvedWidget {
  if (def.kind === "blade") {
    return {
      component: BladeWidget,
      props: { endpoint: def.endpoint, mode: def.mode ?? "island" },
    };
  }

  // vue kind (default)
  if (def.component && dashboardWidgets[def.component]) {
    return { component: dashboardWidgets[def.component], props: def.props ?? {} };
  }

  if (def.module) {
    const url = def.module;
    return {
      // Fail-visible: a failed/unreachable module import must render the same
      // bordered role="alert" box (never a blank widget). Give the async
      // component both an errorComponent and a timeout so a hung import also
      // surfaces the diagnostic rather than spinning forever.
      component: defineAsyncComponent({
        loader: () => import(/* @vite-ignore */ url),
        errorComponent: widgetErrorComponent(def),
        timeout: 10000,
        onError(error, _retry, fail) {
          // Do not retry — a disabled/404 module will never resolve; fail fast
          // so the errorComponent renders immediately.
          fail();
        },
      }),
      props: def.props ?? {},
    };
  }

  return { component: widgetErrorComponent(def), props: {} };
}

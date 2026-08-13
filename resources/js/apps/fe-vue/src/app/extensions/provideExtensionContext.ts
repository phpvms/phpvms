import { defineAsyncComponent, defineComponent, h, type Component } from "vue";
import { usePage } from "@inertiajs/vue3";
import { registry, type ComponentResolver, type SlotEntry } from "@/shared/lib/registry";
import { providePvContext } from "@/shared/lib/usePvSlot";
import { dashboardWidgets, widgetComponents } from "@/widgets/dashboard";
import { mergeServerWidgets, type WidgetDef } from "@/widgets/dashboard/catalog";
import { registerAppWidgets } from "../register-widgets";

interface ExtensionProps {
  widgets?: WidgetDef[];
  slots?: SlotEntry[];
}

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

export function provideExtensionContext() {
  const page = usePage();
  const extensions = page.props.skylight as ExtensionProps | undefined;
  const serverSlots = extensions?.slots ?? [];
  const asyncComponents: ComponentResolver = {};

  registerAppWidgets();
  mergeServerWidgets(extensions?.widgets ?? []);

  for (const entry of serverSlots) {
    if (!entry.module) continue;

    const url = entry.module;
    const name = entry.component;
    asyncComponents[name] = defineAsyncComponent({
      loader: () => import(/* @vite-ignore */ url),
      errorComponent: slotErrorComponent(name),
      timeout: 10000,
      onError(_error, _retry, fail) {
        fail();
      },
    });
  }

  providePvContext(
    [...registry, ...serverSlots],
    { ...widgetComponents, ...dashboardWidgets, ...asyncComponents },
    page.props as Record<string, unknown>,
  );
}

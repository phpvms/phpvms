import { computed, ref, type Ref } from "vue";
import { useStorage } from "@vueuse/core";
import { getCatalog, widgetById, type WidgetDef, type WidgetZone } from "@/lib/widgets/catalog";

/**
 * Per-user dashboard LAYOUT (what is placed + where) on top of the widget
 * catalog. Ordered widget-id arrays per zone, persisted to localStorage now
 * (a `user setting` via Inertia later). Add/remove come from the catalog menu;
 * reorder + cross-zone move come from the draggable v-model on each zone array.
 */
export interface DashboardLayout {
  grid: string[];
  sidebar: string[];
}

const STORAGE_KEY = "skylight.dashboard.layout.v1";

function defaultLayout(): DashboardLayout {
  const layout: DashboardLayout = { grid: [], sidebar: [] };
  for (const w of getCatalog()) {
    if (w.defaultOn) layout[w.defaultZone].push(w.id);
  }
  return layout;
}

// Module singletons — the whole app shares one layout + edit state. Lazily
// initialized on first use so the catalog is read AFTER PvApp merges server
// widgets (its setup runs before the Dashboard page that calls this).
let layoutRef: Ref<DashboardLayout> | null = null;
const editing = ref(false);

// Drop ids that are no longer in the catalog (e.g. a removed addon).
function prune(layout: Ref<DashboardLayout>) {
  for (const zone of ["grid", "sidebar"] as WidgetZone[]) {
    layout.value[zone] = layout.value[zone].filter((id) => widgetById(id));
  }
}

function getLayout(): Ref<DashboardLayout> {
  if (!layoutRef) {
    layoutRef = useStorage<DashboardLayout>(STORAGE_KEY, defaultLayout(), localStorage, {
      mergeDefaults: false,
    });
    prune(layoutRef);
  }
  return layoutRef;
}

export function useDashboardLayout() {
  const layout = getLayout();
  const placed = computed(() => new Set([...layout.value.grid, ...layout.value.sidebar]));

  /** Catalog widgets not currently placed (the Add-widget menu). */
  const availableToAdd = computed<WidgetDef[]>(() =>
    getCatalog().filter((w) => !placed.value.has(w.id)),
  );

  function addWidget(id: string) {
    const def = widgetById(id);
    if (!def || placed.value.has(id)) return;
    layout.value[def.defaultZone].push(id);
  }

  function removeWidget(id: string) {
    for (const zone of ["grid", "sidebar"] as WidgetZone[]) {
      layout.value[zone] = layout.value[zone].filter((w) => w !== id);
    }
  }

  function resetLayout() {
    layout.value = defaultLayout();
  }

  function toggleEdit() {
    editing.value = !editing.value;
  }

  return { layout, editing, availableToAdd, addWidget, removeWidget, resetLayout, toggleEdit };
}

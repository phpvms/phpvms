import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

// Mock the bundled resolver map so the test doesn't load the real widgets
// (some pull in maplibre-gl, which can't init in jsdom). Async factory imports
// vue internally, avoiding the hoisting trap. resolveWidget imports the SAME
// mocked module, so identity comparisons below still hold.
vi.mock('@/components/widgets/dashboard', async () => {
  const { defineComponent, h } = await import('vue')
  const Bundled = defineComponent({
    name: 'Bundled',
    setup: () => () => h('div', { class: 'bundled' }, 'bundled'),
  })
  return { dashboardWidgets: { HoursWidget: Bundled } }
})

import { resolveWidget, widgetErrorComponent } from '@/components/widgets/resolve'
import { dashboardWidgets } from '@/components/widgets/dashboard'
import BladeWidget from '@/components/widgets/BladeWidget.vue'
import type { WidgetDef } from '@/lib/widgets/catalog'

const base = { title: 'X', icon: '', defaultZone: 'grid' as const }

describe('resolveWidget', () => {
  it('blade kind → BladeWidget with endpoint + default island mode', () => {
    const def: WidgetDef = { ...base, id: 'b', kind: 'blade', endpoint: '/ext/x' }
    const r = resolveWidget(def)
    expect(r.component).toBe(BladeWidget)
    expect(r.props).toEqual({ endpoint: '/ext/x', mode: 'island' })
  })

  it('blade kind honors explicit iframe mode', () => {
    const def: WidgetDef = { ...base, id: 'b', kind: 'blade', endpoint: '/ext/x', mode: 'iframe' }
    expect(resolveWidget(def).props).toEqual({ endpoint: '/ext/x', mode: 'iframe' })
  })

  it('vue kind with bundled component name → mapped component + props', () => {
    const def: WidgetDef = { ...base, id: 'h', component: 'HoursWidget', props: { a: 1 } }
    const r = resolveWidget(def)
    expect(r.component).toBe(dashboardWidgets.HoursWidget)
    expect(r.props).toEqual({ a: 1 })
  })

  it('vue kind with module URL → async component', () => {
    const def: WidgetDef = { ...base, id: 'm', module: '/ext/foo/widget.js' }
    const r = resolveWidget(def)
    // defineAsyncComponent returns a component object with a setup/loader.
    expect(r.component).toBeTypeOf('object')
    expect(r.component).not.toBe(BladeWidget)
    expect(r.component).not.toBe(dashboardWidgets.HoursWidget)
    expect(r.props).toEqual({})
  })

  it('unknown → fail-visible error component (never blank)', () => {
    const def: WidgetDef = { ...base, id: 'ghost', title: 'Ghost' }
    const r = resolveWidget(def)
    const w = mount(r.component, { props: r.props })
    const alert = w.find('[data-pv-widget-failed="ghost"]')
    expect(alert.exists()).toBe(true)
    expect(alert.attributes('role')).toBe('alert')
    expect(w.text()).toContain('Ghost')
  })

  it('widgetErrorComponent falls back to id when no title', () => {
    const w = mount(widgetErrorComponent({ ...base, id: 'only-id', title: '' }))
    expect(w.text()).toContain('only-id')
  })
})

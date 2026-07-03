import { describe, it, expect, beforeEach } from 'vitest'
import {
  getCatalog,
  widgetById,
  mergeServerWidgets,
  type WidgetDef,
} from '@/lib/widgets/catalog'

const base = { title: 'X', icon: '', defaultZone: 'grid' as const }

beforeEach(() => mergeServerWidgets([])) // reset module state between tests

describe('catalog server merge', () => {
  it('with no server widgets, returns only first-party CATALOG', () => {
    const ids = getCatalog().map((w) => w.id)
    expect(ids).toContain('route')
    expect(ids).toContain('weather')
    // A couple of first-party ids, none of them server-provided.
    expect(getCatalog().every((w) => w.kind !== 'blade')).toBe(true)
  })

  it('appends server widgets after first-party ones', () => {
    const first = getCatalog().length
    const extra: WidgetDef = { ...base, id: 'srv-x', kind: 'blade', endpoint: '/ext/x' }
    mergeServerWidgets([extra])
    const cat = getCatalog()
    expect(cat.length).toBe(first + 1)
    expect(cat[cat.length - 1].id).toBe('srv-x')
    expect(widgetById('srv-x')).toEqual(extra)
  })

  it('dedupes incoming batch by id (last wins)', () => {
    mergeServerWidgets([
      { ...base, id: 'dup', title: 'First' },
      { ...base, id: 'dup', title: 'Second' },
    ])
    expect(widgetById('dup')?.title).toBe('Second')
    expect(getCatalog().filter((w) => w.id === 'dup')).toHaveLength(1)
  })

  it('server entry wins over a first-party entry with the same id', () => {
    const override: WidgetDef = { ...base, id: 'weather', title: 'Server Weather' }
    mergeServerWidgets([override])
    const matches = getCatalog().filter((w) => w.id === 'weather')
    expect(matches).toHaveLength(1)
    expect(matches[0].title).toBe('Server Weather')
    expect(widgetById('weather')?.title).toBe('Server Weather')
  })

  it('guards undefined / empty input', () => {
    const n = getCatalog().length
    mergeServerWidgets(undefined)
    expect(getCatalog().length).toBe(n)
    mergeServerWidgets([])
    expect(getCatalog().length).toBe(n)
  })
})

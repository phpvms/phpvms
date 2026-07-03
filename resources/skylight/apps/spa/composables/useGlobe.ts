import { onMounted, onBeforeUnmount, shallowRef, type Ref } from 'vue'
import maplibregl from 'maplibre-gl'
import { feature } from 'topojson-client'
import landUrl from '@/assets/land-110m.json?url'
import { greatCircle, bearing, routeCenter, type LngLat } from '@/lib/geo'

export interface GlobeRoute {
  from: LngLat
  to?: LngLat | null
  fromLabel?: string
  toLabel?: string
}

/** Read a CSS custom property off :root (tokens resolve the active deck). */
function token(name: string): string {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}

function marker(cls: string, html?: string): HTMLDivElement {
  const el = document.createElement('div')
  el.className = cls
  if (html) el.innerHTML = html
  return el
}

/**
 * Mount a tile-free MapLibre globe into `container` and draw a blue
 * great-circle route with DOM markers. Colors come from `--pv-globe-*` /
 * `--pv-accent` tokens; the basemap is self-hosted Natural Earth land (topojson).
 *
 * Returns a ref to the map so callers can react if needed. Fully cleaned up on
 * unmount.
 */
export function useGlobe(container: Ref<HTMLElement | null>, route: GlobeRoute) {
  const map = shallowRef<maplibregl.Map | null>(null)

  onMounted(async () => {
    const el = container.value
    if (!el) return

    const hasDest = !!route.to
    const center = hasDest ? routeCenter(route.from, route.to as LngLat) : route.from

    const m = new maplibregl.Map({
      container: el,
      style: {
        version: 8,
        // MapLibre needs a glyphs URL only if we render text layers; we use DOM
        // markers instead, so none is required.
        sources: {},
        layers: [
          { id: 'ocean', type: 'background', paint: { 'background-color': token('--pv-globe-sea') } },
        ],
      },
      center,
      zoom: hasDest ? 1.55 : 3,
      attributionControl: { compact: true },
      dragRotate: true,
    })
    map.value = m

    m.on('load', async () => {
      try {
        m.setProjection({ type: 'globe' })
      } catch {
        /* projection unsupported — falls back to flat, still renders */
      }

      // Self-hosted basemap: Natural Earth land (topojson → geojson).
      try {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const topo: any = await fetch(landUrl).then((r) => r.json())
        const land = feature(topo, topo.objects.land) as GeoJSON.GeoJSON
        m.addSource('land', { type: 'geojson', data: land })
        m.addLayer({ id: 'land-fill', type: 'fill', source: 'land', paint: { 'fill-color': token('--pv-globe-land') } })
        m.addLayer({ id: 'land-coast', type: 'line', source: 'land', paint: { 'line-color': token('--pv-globe-coast'), 'line-width': 0.8 } })
      } catch {
        /* basemap failed — ocean sphere + route still render (fail-visible) */
      }

      // Origin marker + label
      new maplibregl.Marker({ element: marker('mk-ring') }).setLngLat(route.from).addTo(m)
      if (route.fromLabel) {
        new maplibregl.Marker({ element: marker('mk-apt', route.fromLabel), anchor: 'top' })
          .setLngLat(route.from)
          .addTo(m)
      }

      if (hasDest) {
        const to = route.to as LngLat
        // Blue great-circle
        m.addSource('route', {
          type: 'geojson',
          data: { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: greatCircle(route.from, to) } },
        })
        m.addLayer({
          id: 'route',
          type: 'line',
          source: 'route',
          layout: { 'line-cap': 'round', 'line-join': 'round' },
          paint: { 'line-color': token('--pv-accent'), 'line-width': 2.4, 'line-blur': 0.3 },
        })

        // Plane glyph at origin, rotated along initial track
        const plane = marker(
          'mk-plane',
          '<svg width="18" height="18" viewBox="-9 -9 18 18"><path d="M0 -8 L2.2 2.6 L8 5.4 L2.2 5.4 L0 10.5 L-2.2 5.4 L-8 5.4 L-2.2 2.6 Z" fill="currentColor"/></svg>',
        )
        plane.style.transform = `rotate(${bearing(route.from, to)}deg)`
        new maplibregl.Marker({ element: plane }).setLngLat(route.from).addTo(m)

        new maplibregl.Marker({ element: marker('mk-ring') }).setLngLat(to).addTo(m)
        if (route.toLabel) {
          new maplibregl.Marker({ element: marker('mk-apt', route.toLabel), anchor: 'bottom' })
            .setLngLat(to)
            .addTo(m)
        }
      }
    })
  })

  onBeforeUnmount(() => {
    map.value?.remove()
    map.value = null
  })

  return { map }
}

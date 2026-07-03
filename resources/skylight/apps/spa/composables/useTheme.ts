import { useDark, useToggle } from '@vueuse/core'

/**
 * Light/dark deck toggle. Adds/removes `.dark` on <html> (matching the
 * `@custom-variant dark` in app.css), persisted to localStorage and honoring the
 * OS preference on first load.
 */
export function useTheme() {
  const isDark = useDark({
    selector: 'html',
    attribute: 'class',
    valueDark: 'dark',
    valueLight: '',
    storageKey: 'skylight.theme',
  })
  return { isDark, toggleDark: useToggle(isDark) }
}

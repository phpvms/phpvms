/**
 * Tiny translation helper for the RouteForge TS bundle.
 *
 * Reads from `window.routeforgeConfig.translations`, a nested object
 * populated server-side by `App\Filament\Pages\RouteForge::buildTranslationsPayload()`
 * via `trans('filament.routeforge')`. The same key tree therefore lives in
 * `resources/lang/<locale>/filament.php` under the `routeforge` key, and
 * RouteForge auto-picks up new translations + new locales without any TS
 * changes.
 *
 * Usage:
 *
 *   t('preview.generate')                    → 'Generate'
 *   t('lint.l6_origin_equals_dest', {        → 'Origin equals destination (KSFO).'
 *       airport: 'KSFO',
 *   })
 *
 * Placeholders use Laravel's `:name` syntax so the same string can be shared
 * between PHP (`__()`) and TS (`t()`). The helper substitutes every
 * `:name` occurrence with `String(params[name])`; missing params are left
 * verbatim so the omission is visible during development.
 *
 * Missing keys fall back to the dot-path itself — visible enough to spot
 * during dev, harmless in prod.
 */

export type TranslationParams = Record<string, string | number>;

export function t(key: string, params?: TranslationParams): string {
  const translations = window.routeforgeConfig?.translations;
  let node: unknown = translations;
  for (const segment of key.split(".")) {
    if (node === null || typeof node !== "object") {
      return key;
    }
    node = (node as Record<string, unknown>)[segment];
    if (node === undefined) {
      return key;
    }
  }
  if (typeof node !== "string") {
    return key;
  }
  if (params === undefined) {
    return node;
  }
  let result = node;
  for (const [name, value] of Object.entries(params)) {
    result = result.split(`:${name}`).join(String(value));
  }
  return result;
}

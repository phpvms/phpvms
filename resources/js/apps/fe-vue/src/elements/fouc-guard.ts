const STYLE_ID = "pv-elements-fouc-guard";

/**
 * Hide any registered-but-not-yet-upgraded tag so a half-painted page does not
 * flash raw, unstyled content before the elements bundle finishes evaluating
 * (`web-component-widget-floor` D3, task 2.3).
 *
 * Injected by THIS bundle rather than shipped in `app.css`: a document that
 * loads only the elements bundle (the framework-neutral, no-Vue-app case this
 * change exists for) never loads the SPA's stylesheet, so the guard has to
 * travel with the elements themselves to apply everywhere they do.
 */
export function installFoucGuard(tags: readonly string[]): void {
  if (document.getElementById(STYLE_ID) || tags.length === 0) {
    return;
  }

  const selector = tags.map((tag) => `${tag}:not(:defined)`).join(", ");
  const style = document.createElement("style");
  style.id = STYLE_ID;
  style.textContent = `${selector} { visibility: hidden; }`;
  document.head.appendChild(style);
}

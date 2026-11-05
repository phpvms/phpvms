/**
 * Small DOM helpers for the live-map island. The markup it needs is declared as
 * `<template data-tpl="…">` in components/livemap/templates.blade.php, so the classes are built
 * with the theme and the island only fills text with `textContent`.
 */

/**
 * Clone a template declared inside the island root.
 *
 * @param {HTMLElement} root
 * @param {string} name
 * @returns {HTMLElement}
 */
export function cloneTemplate(root, name) {
  const template = root.querySelector(`template[data-tpl="${name}"]`);

  if (!(template instanceof HTMLTemplateElement) || !template.content.firstElementChild) {
    throw new Error(`live-map: missing template "${name}"`);
  }

  return /** @type {HTMLElement} */ (template.content.firstElementChild.cloneNode(true));
}

/**
 * Set the text of the `[data-field]` child.
 *
 * @param {HTMLElement} el
 * @param {string} field
 * @param {string} text
 */
export function setField(el, field, text) {
  const target = el.querySelector(`[data-field="${field}"]`);

  if (target) {
    target.textContent = text;
  }
}

/**
 * An ACARS altitude in feet as a flight level at 18,000 ft and above, else in feet.
 * Below 100 ft (on the ground) and when missing, an em dash.
 *
 * @param {number | null | undefined} feet
 * @returns {string}
 */
export function formatAltitude(feet) {
  const value = Number(feet);

  if (!Number.isFinite(value) || value < 100) {
    return "—";
  }

  if (value >= 18000) {
    return `FL${String(Math.round(value / 100)).padStart(3, "0")}`;
  }

  return `${Math.round(value).toLocaleString("en-US")} ft`;
}

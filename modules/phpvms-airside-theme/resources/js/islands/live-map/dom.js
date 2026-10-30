/**
 * Small DOM helpers for the live-map island. The markup it needs is declared as
 * `<template data-tpl="…">` in livemap/index.blade.php, so the classes are built
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

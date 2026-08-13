/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

import rivets from "rivets";

/**
 * Generic formatter to prepend
 *
 * @param value
 * @param prepend
 *
 * @returns {*}
 */
rivets.formatters.prepend = function (value, prepend) {
  return prepend + value;
};

/**
 * Format minutes into HHh MMm
 *
 * @param value
 *
 * @returns {string}
 */
rivets.formatters.time_hm = function (value) {
  const hours = Math.floor(value / 60);
  const mins = value % 60;
  return `${hours}h ${mins}m`;
};

/**
 *
 * @param value
 * @param len
 *
 * @returns {boolean}
 */
rivets.formatters.gt = (value, len) => value.length > len;

/**
 *
 * @param value
 * @param len
 *
 * @returns {boolean}
 */
rivets.formatters.lt = (value, len) => value.length < len;

/**
 *
 * @param value
 * @param len
 *
 * @returns {boolean}
 */
rivets.formatters.eq = (value, len) => value.length > len;

/**
 * Use a default value if value is null or blank
 *
 * @param value Value to use
 * @param def   Default value to use if value is null
 */
rivets.formatters.fallback = (value, def) => value || def;

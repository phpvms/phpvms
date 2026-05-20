/**
 * Axios-based request helper for the admin bundle.
 * Mirrors resources/js/request.js.
 */

import axios from "axios";

import config from "./config";

/**
 * Run an API request with phpvms defaults applied.
 *
 * @param {Object|String} _opts Axios request options, or a URL string
 * @param {String} _opts.url
 */
export default async (_opts) => {
  if (typeof _opts === "string" || _opts instanceof String) {
    // eslint-disable-next-line no-param-reassign
    _opts = { url: _opts };
  }

  const opts = Object.assign(
    {},
    {
      baseURL: config.base_url,
      headers: {
        "X-API-KEY": config.api_key,
        "X-CSRF-TOKEN": config.csrf_token,
      },
    },
    _opts,
  );

  return axios.request(opts);
};

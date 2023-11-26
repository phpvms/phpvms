/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

import axios from "axios";

import config from "./config";

/**
 * Run an API request, with some common options
 *
 * @param {Object|String} _opts Axios request options, or pass a URL
 * @param {String} _opts.url
 */
export default async (_opts) => {
  if (typeof _opts === "string" || _opts instanceof String) {
    // eslint-disable-next-line no-param-reassign
    _opts = {
      url: _opts,
    };
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
    _opts
  );

  // console.log(opts);

  return axios.request(opts);
};

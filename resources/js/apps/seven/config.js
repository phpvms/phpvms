/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

const base_url = document.head.querySelector('meta[name="base-url"]');
const token = document.head.querySelector('meta[name="csrf-token"]');
const api_key = document.head.querySelector('meta[name="api-key"]');

export default {
  api_key: api_key.content || "",
  base_url: base_url.content || "",
  csrf_token: token.content || "",
};

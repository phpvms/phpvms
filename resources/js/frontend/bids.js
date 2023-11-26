/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

import request from "../request";

/**
 * Add a bid to a flight
 *
 * @param {String} flight_id
 * @param {?int} aircraft_id
 *
 * @returns {Promise<*>}
 */
export async function addBid(flight_id, aircraft_id = null) {
  const params = {
    method: "POST",
    url: "/api/user/bids",
    data: {
      _method: "POST",
      flight_id,
      aircraft_id,
    },
  };

  return request(params);
}

/**
 * Remove a bid from a given flight
 *
 * @param {String} flight_id
 *
 * @returns {Promise<*>}
 */
export async function removeBid(flight_id) {
  const params = {
    method: "POST",
    url: "/api/user/bids",
    data: {
      _method: "DELETE",
      flight_id,
    },
  };

  return request(params);
}

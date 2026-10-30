const MONTHS = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];

/**
 * The estimated departure SimBrief is asked for: now + 45 minutes, rounded to
 * 5 minutes (UTC), plus the scheduled block time split into hours and minutes.
 * The same arithmetic as skylight's SimBriefPlanningOptions.
 *
 * @param {string | null | undefined} blockTime "H:MM" or empty
 * @param {Date} [now]
 */
export function departureTiming(blockTime, now = new Date()) {
  const departure = new Date(now.getTime());
  departure.setMinutes(departure.getMinutes() + 45);

  let minutes = departure.getUTCMinutes();
  if (minutes < 55) minutes = Math.ceil(minutes / 5) * 5;
  if (minutes > 55) minutes = Math.floor(minutes / 5) * 5;

  const hour = String(departure.getUTCHours()).padStart(2, "0");
  const minute = String(minutes).padStart(2, "0");
  const date = `${String(departure.getUTCDate()).padStart(2, "0")}${MONTHS[departure.getUTCMonth()]}${departure.getUTCFullYear()}`;
  const [hours = "0", blockMinutes = "0"] = String(blockTime ?? "0:0").split(":");

  return {
    etd: `${hour}:${minute}`,
    date,
    deph: hour,
    depm: minute,
    steh: String(Number(hours)),
    stem: String(Number(blockMinutes)),
  };
}

export interface FlightFilters {
  airlineId: string | null;
  flightNumber: string | null;
  flightType: string | null;
  routeCode: string | null;
  depIcao: string | null;
  arrIcao: string | null;
  distanceGreaterThan: string | null;
  distanceLessThan: string | null;
  timeGreaterThan: string | null;
  timeLessThan: string | null;
  subfleetId: string | null;
  typeRatingId: string | null;
  icaoType: string | null;
  search: string | null;
  orderBy: string | null;
  sortedBy: string | null;
  limit: string | null;
}

export interface FlightFilterOptions {
  airlines: Record<string, string>;
  flightTypes: Record<string, string>;
  subfleets: Record<string, string>;
  typeRatings: Array<{ id: number; name: string; type: string }>;
  icaoTypes: string[];
}

export interface FlightPage {
  current: number;
  last: number;
  total: number;
}

/** One label-over-value fact in a flight card's stat strip. */
export interface FlightStat {
  label: string;
  value: string;
}

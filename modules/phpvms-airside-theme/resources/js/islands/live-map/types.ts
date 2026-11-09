/** The parts of the live-map API payloads (`MapLiveFlightData`, `MapPirepDetailData`) the island reads. */

export interface AirportRef {
  icao?: string | null;
  name?: string | null;
}

export interface LiveFlight {
  pirepId: string;
  ident?: string | null;
  phase?: string | null;
  status?: string | null;
  progress?: number | null;
  position: {
    lat: number;
    lon: number;
    altitude?: number | null;
    heading?: number | null;
  };
  dptAirport?: AirportRef | null;
  arrAirport?: AirportRef | null;
  pilot?: { name?: string | null; ident?: string | null } | null;
  aircraft?: { name?: string | null; registration?: string | null } | null;
}

export interface FlightDetail {
  pirepId: string;
  ident?: string | null;
  callsign?: string | null;
  phase?: string | null;
  status?: string | null;
  blockOffTime?: string | null;
  scheduledArrivalAt?: string | null;
  distance?: string | number | null;
  plannedDistance?: string | number | null;
  cruiseLevel?: number | null;
  flightTime?: number | null;
  plannedFlightTime?: number | null;
  airline?: { name?: string | null; icao?: string | null; logo?: string | null } | null;
  airports?: { dpt?: AirportRef | null; arr?: AirportRef | null } | null;
  pilot?: { name: string; ident?: string | null } | null;
  aircraft?: { name?: string | null; registration?: string | null } | null;
  flown?: {
    points?: {
      lat: number;
      lon: number;
      altitude?: number | null;
      phase?: string | null;
    }[];
  } | null;
}

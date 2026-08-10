export type HeaderAirline = App.Http.Data.AirlineIdentityData;

export interface HeaderUser {
  id: number | string;
  name: string;
  avatar: string | null;
  ident: string | null;
  callsign: string | null;
  airline: HeaderAirline | null;
}

export type ActiveSector = App.Http.Data.ActiveSectorData;
export type DutyState = App.Http.Data.DutyStateData;
export type WeatherStation = App.Http.Data.WeatherStationData;
export type PilotChrome = App.Http.Data.PilotChromeData;

export interface MetarResponse {
  icao: string;
  metar: string | null;
  observedAt: string | null;
  isStale: boolean;
}

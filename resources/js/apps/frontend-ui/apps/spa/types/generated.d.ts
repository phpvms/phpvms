declare namespace App {
namespace Http {
namespace Data {
export type ActivityEventData = {
id: string,
type: string,
title: string,
subtitle: string | null,
timestamp: string,
icon: string,
};
export type ActivityFeedData = {
flyingNow: number,
events: App.Http.Data.ActivityEventData[],
};
export type AircraftRefData = {
id: number,
registration: string | null,
name: string | null,
};
export type AirlineRefData = {
icao: string,
name: string,
};
export type AirportPointData = {
id: string,
icao: string,
name: string | null,
lat: number | null,
lon: number | null,
};
export type AirportRefData = {
icao: string,
name: string,
};
export type AwardData = {
name: string,
description: string | null,
image: string | null,
};
export type BalanceData = {
amount: number,
formatted: string,
};
export type BidData = {
id: number,
flightId: string,
aircraftId: number | null,
};
export type BidRowData = {
bid: App.Http.Data.BidData,
flight: App.Http.Data.FlightData | null,
};
export type DashboardData = {
id: number,
name: string,
flights: number,
flightTimeMinutes: string,
onLeave: boolean,
balance: App.Http.Data.BalanceData | null,
currentAirport: string | null,
lastPirep: App.Http.Data.LastPirepData | null,
rank: App.Http.Data.RankProgressData | null,
route: App.Http.Data.RouteData,
};
export type FlightData = {
id: string,
callsign: string,
dpt: string | null,
arr: string | null,
distanceNm: number | null,
blockTime: string | null,
type: string | null,
};
export type FlightListItemData = {
id: string,
callsign: string,
dpt: string | null,
arr: string | null,
distanceNm: number | null,
blockTime: string | null,
type: string | null,
airline: App.Http.Data.AirlineRefData | null,
bidId: number | null,
};
export type LastPirepData = {
id: string,
ident: string,
flight_number: string | null,
airline_id: number | null,
state: App.Http.Data.PirepStateData,
flight_time: number | null,
submitted_at: string | null,
created_at: string | null,
dpt_airport: App.Http.Data.AirportPointData | null,
arr_airport: App.Http.Data.AirportPointData | null,
aircraft: App.Http.Data.AircraftRefData | null,
comments: App.Http.Data.PirepCommentData[],
};
export type PirepCommentData = {
id: number,
comment: string | null,
created_at: string | null,
};
export type PirepData = {
id: string,
ident: string,
aircraft: string | null,
airline: string | null,
dpt: string,
arr: string,
dptName: string | null,
arrName: string | null,
state: string,
stateColor: string,
status: string | null,
source: string,
sourceName: string | null,
flightType: string | null,
route: string | null,
notes: string | null,
flightTime: string | null,
plannedFlightTime: string | null,
distance: string | null,
plannedDistance: string | null,
score: number | null,
landingRate: number | null,
fuelUsed: string | null,
blockFuel: string | null,
cruise: string | null,
pilotName: string | null,
pilotRank: string | null,
submittedAt: string | null,
fields: App.Http.Data.PirepFieldData[],
fares: App.Http.Data.PirepFareData[],
logs: App.Http.Data.PirepLogData[],
};
export type PirepFareData = {
name: string,
code: string | null,
count: number,
};
export type PirepFieldData = {
name: string,
value: string | null,
};
export type PirepListItemData = {
id: string,
ident: string,
aircraft: string | null,
dpt: string,
arr: string,
dptName: string | null,
arrName: string | null,
flightTime: string | null,
distance: string | null,
score: number | null,
landingRate: number | null,
state: string,
stateColor: string,
submittedAt: string | null,
};
export type PirepLogData = {
time: string | null,
message: string,
};
export type PirepStateData = {
value: number,
label: string,
color: string,
};
export type ProfileData = {
id: number,
name: string,
avatar: string | null,
airline: App.Http.Data.AirlineRefData | null,
rank: App.Http.Data.RankData | null,
homeAirport: App.Http.Data.AirportRefData | null,
currentAirport: App.Http.Data.AirportRefData | null,
flights: number,
flightTimeMinutes: string,
memberSince: string | null,
state: App.Http.Data.StateBadgeData,
awards: App.Http.Data.AwardData[],
typeRatings: App.Http.Data.TypeRatingData[],
fields: App.Http.Data.UserFieldData[],
acars: boolean,
};
export type RankData = {
name: string,
};
export type RankProgressData = {
from: string,
to: string | null,
pct: number,
};
export type RouteData = {
from: App.Http.Data.RoutePointData | null,
to: App.Http.Data.RoutePointData | null,
};
export type RoutePointData = {
icao: string,
name: string | null,
lat: number,
lon: number,
};
export type StateBadgeData = {
label: string,
color: string,
};
export type TypeRatingData = {
name: string,
type: string,
};
export type UserFieldData = {
name: string,
value: string | null,
};
}
}
}
declare namespace Modules {
namespace SampleVueWidget {
namespace Http {
namespace Data {
export type SamplePingData = {
addon: string,
message: string,
time: string,
};
}
}
}
}

declare namespace App {
namespace Http {
namespace Data {
export type ActiveSectorData = {
pirepId: string,
ident: string,
departureIcao: string,
arrivalIcao: string,
state: string,
};
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
export type AirlineIdentityData = {
name: string,
icao: string,
iata: string | null,
logo: string | null,
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
flight: App.Http.Data.FlightListItemData | null,
aircraft: App.Http.Data.EligibleAircraftData | null,
state: string,
expiresAt: string | null,
canGenerateSimBrief: boolean,
canRemove: boolean,
};
export type BidSelectionData = {
bid: App.Http.Data.BidData,
flight: App.Http.Data.FlightDetailData,
aircraft: App.Http.Data.EligibleAircraftData | null,
policy: App.Http.Data.FlightDispatchPolicyData,
state: string,
expiresAt: string | null,
aircraftReserved: boolean,
};
export type DashboardData = {
id: number,
name: string,
flights: number,
flightTimeMinutes: string,
transferTimeMinutes: string,
state: App.Http.Data.StateBadgeData,
onLeave: boolean,
balance: App.Http.Data.BalanceData | null,
currentAirport: string | null,
lastPirep: App.Http.Data.LastPirepData | null,
rank: App.Http.Data.RankProgressData | null,
pilotScore: number | null,
onTimePercentage: number | null,
averageLandingRate: number | null,
route: App.Http.Data.RouteData,
};
export type DutyStateData = {
state: string,
label: string,
color: string,
};
export type EligibleAircraftData = {
id: number,
registration: string,
icaoType: string,
name: string | null,
subfleetId: number,
subfleetName: string,
airport: App.Http.Data.AirportRefData | null,
state: string,
status: string,
};
export type EligibleSubfleetData = {
id: number,
airlineIcao: string | null,
airlineName: string | null,
icaoType: string | null,
displayName: string,
eligibleAircraftCount: number,
disabled: boolean,
availabilityLabel: string | null,
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
export type FlightDetailData = {
summary: App.Http.Data.FlightListItemData,
departure: App.Http.Data.AirportPointData | null,
arrival: App.Http.Data.AirportPointData | null,
alternate: App.Http.Data.AirportPointData | null,
departureWeather: App.Http.Data.WeatherStationData | null,
arrivalWeather: App.Http.Data.WeatherStationData | null,
alternateWeather: App.Http.Data.WeatherStationData | null,
scheduledDeparture: string | null,
scheduledArrival: string | null,
route: string | null,
cruiseLevel: number | null,
dispatchUrl: string,
simbriefPlanningUrl: string,
};
export type FlightDispatchPolicyData = {
aircraftRequired: boolean,
chooseLaterAllowed: boolean,
allowMultipleBids: boolean,
pilotBidLimitReached: boolean,
disableFlightOnBid: boolean,
expireHours: number,
restrictToCompany: boolean,
discoveryCurrentAirportOnly: boolean,
requireCurrentAirport: boolean,
restrictAircraftToRank: boolean,
restrictAircraftToTypeRating: boolean,
aircraftAtDepartureOnly: boolean,
companyAircraftOnly: boolean,
simbriefAvailable: boolean,
simbriefRequiresBid: boolean,
simbriefBlocksAircraft: boolean,
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
scheduledDeparture: string | null,
scheduledArrival: string | null,
routeCode: string | null,
availability: string,
availabilityReason: string | null,
primaryAction: string,
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
export type PilotChromeData = {
activeSector: App.Http.Data.ActiveSectorData | null,
duty: App.Http.Data.DutyStateData,
station: App.Http.Data.WeatherStationData | null,
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
currentHours: number,
targetHours: number | null,
hoursRemaining: number | null,
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
export type SimBriefAttemptData = {
staticId: string,
flightId: string,
aircraftId: number,
expiresAt: string,
state: string,
};
export type SimBriefBriefingData = {
id: string,
flight: App.Http.Data.FlightDetailData,
bid: App.Http.Data.BidData | null,
aircraft: App.Http.Data.EligibleAircraftData,
route: string,
atcPlan: string,
textOfp: string,
weather: Record<string, string>,
downloads: {
name: string,
url: string,
}[],
images: {
name: string,
url: string,
}[],
prefileLinks: Record<string, string>,
editorUrl: string | null,
canCancel: boolean,
canRegenerate: boolean,
};
export type SimBriefPlanningData = {
attempt: App.Http.Data.SimBriefAttemptData,
flight: App.Http.Data.FlightDetailData,
aircraft: App.Http.Data.EligibleAircraftData,
providerFields: Record<string, string | number | null>,
requiresExplicitGeneration: boolean,
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
export type WeatherStationData = {
icao: string,
timezone: string | null,
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

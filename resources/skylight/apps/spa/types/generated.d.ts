declare namespace App {
  namespace Http {
    namespace Data {
      export type BidData = {
        id: number;
        flightId: string;
        aircraftId: number | null;
      };
      export type BidRowData = {
        bid: App.Http.Data.BidData;
        flight: App.Http.Data.FlightData | null;
      };
      export type FlightData = {
        id: string;
        callsign: string;
        dpt: string | null;
        arr: string | null;
        distanceNm: number | null;
        blockTime: string | null;
        type: string | null;
      };
      export type PirepData = {
        id: string;
        ident: string;
        aircraft: string | null;
        airline: string | null;
        dpt: string;
        arr: string;
        dptName: string | null;
        arrName: string | null;
        state: string;
        stateColor: string;
        status: string | null;
        source: string;
        sourceName: string | null;
        flightType: string | null;
        route: string | null;
        notes: string | null;
        flightTime: string | null;
        plannedFlightTime: string | null;
        distance: string | null;
        plannedDistance: string | null;
        score: number | null;
        landingRate: number | null;
        fuelUsed: string | null;
        blockFuel: string | null;
        cruise: string | null;
        pilotName: string | null;
        pilotRank: string | null;
        submittedAt: string | null;
        fields: App.Http.Data.PirepFieldData[];
        fares: App.Http.Data.PirepFareData[];
        logs: App.Http.Data.PirepLogData[];
      };
      export type PirepFareData = {
        name: string;
        code: string | null;
        count: number;
      };
      export type PirepFieldData = {
        name: string;
        value: string | null;
      };
      export type PirepListItemData = {
        id: string;
        ident: string;
        aircraft: string | null;
        dpt: string;
        arr: string;
        dptName: string | null;
        arrName: string | null;
        flightTime: string | null;
        distance: string | null;
        score: number | null;
        landingRate: number | null;
        state: string;
        stateColor: string;
        submittedAt: string | null;
      };
      export type PirepLogData = {
        time: string | null;
        message: string;
      };
    }
  }
}
declare namespace Modules {
  namespace SampleVueWidget {
    namespace Http {
      namespace Data {
        export type SamplePingData = {
          addon: string;
          message: string;
          time: string;
        };
      }
    }
  }
}

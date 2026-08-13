export interface DispatchPayload {
  flight: App.Http.Data.FlightDetailData;
  policy: App.Http.Data.FlightDispatchPolicyData;
  subfleets: App.Http.Data.EligibleSubfleetData[];
  selection: App.Http.Data.BidSelectionData | null;
}

export interface EligibleAircraftResponse {
  aircraft: App.Http.Data.EligibleAircraftData[];
}

export interface BidFailure {
  type: string;
  message: string;
}

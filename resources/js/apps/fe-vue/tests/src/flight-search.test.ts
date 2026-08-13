import { mount } from "@vue/test-utils";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { defineComponent, h } from "vue";
import type { FlightFilterOptions, FlightFilters } from "@/components/flights/types";
import Flights from "@/pages/Flights.vue";

const inertia = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock("@inertiajs/vue3", async () => {
  const { defineComponent, h } = await import("vue");
  return {
    Link: defineComponent({
      props: { href: { type: String, required: true } },
      setup:
        (props, { slots }) =>
        () =>
          h("a", { href: props.href }, slots.default?.()),
    }),
    router: { get: inertia.get },
  };
});

const filters: FlightFilters = {
  airlineId: "1",
  flightNumber: "104",
  flightType: "J",
  routeCode: "A",
  depIcao: "KDFW",
  arrIcao: "KORD",
  distanceGreaterThan: "500",
  distanceLessThan: "900",
  timeGreaterThan: "60",
  timeLessThan: "180",
  subfleetId: "4",
  typeRatingId: "5",
  icaoType: "A320",
  search: "morning",
  orderBy: "dpt_time",
  sortedBy: "asc",
  limit: "50",
};

const options: FlightFilterOptions = {
  airlines: { "1": "phpVMS Air" },
  flightTypes: { J: "Passenger" },
  subfleets: { "4": "A320" },
  typeRatings: [{ id: 5, name: "Airbus", type: "A320" }],
  icaoTypes: ["A320"],
};

const filterStub = defineComponent({
  props: { filters: { type: Object, required: true } },
  emits: ["submit", "reset"],
  setup:
    (props, { emit }) =>
    () =>
      h("button", { class: "apply", onClick: () => emit("submit", props.filters) }, "Apply"),
});

beforeEach(() => inertia.get.mockReset());

describe("flight search state", () => {
  it("preserves every accepted filter during Inertia changes and pagination", async () => {
    const wrapper = mount(Flights, {
      props: {
        flights: [],
        policy: {
          aircraftRequired: false,
          chooseLaterAllowed: true,
          allowMultipleBids: true,
          pilotBidLimitReached: false,
          disableFlightOnBid: false,
          expireHours: 0,
          restrictToCompany: false,
          discoveryCurrentAirportOnly: false,
          requireCurrentAirport: false,
          restrictAircraftToRank: false,
          restrictAircraftToTypeRating: false,
          aircraftAtDepartureOnly: false,
          companyAircraftOnly: false,
          simbriefEnabled: false,
          simbriefRequiresBid: false,
          simbriefBlocksAircraft: false,
        },
        page: { current: 2, last: 3, total: 120 },
        filters,
        filterOptions: options,
      },
      global: {
        stubs: {
          DispatchFilters: filterStub,
          FlightManifest: true,
          AssignmentDrawer: true,
        },
      },
    });

    await wrapper.get(".apply").trigger("click");
    expect(inertia.get).toHaveBeenCalledWith(
      "/flights",
      {
        airline_id: "1",
        flight_number: "104",
        flight_type: "J",
        route_code: "A",
        dep_icao: "KDFW",
        arr_icao: "KORD",
        dgt: "500",
        dlt: "900",
        tgt: "60",
        tlt: "180",
        subfleet_id: "4",
        type_rating_id: "5",
        icao_type: "A320",
        search: "morning",
        orderBy: "dpt_time",
        sortedBy: "asc",
        limit: "50",
      },
      expect.objectContaining({ preserveScroll: true }),
    );

    const hrefs = wrapper.findAll(".pager a").map((link) => link.attributes("href"));
    expect(hrefs).toHaveLength(2);
    for (const href of hrefs) {
      expect(href).toBeDefined();
      if (!href) throw new Error("pagination href missing");
      const query = new URL(href, "https://phpvms.test").searchParams;
      expect(
        Object.values({
          airline_id: "1",
          flight_number: "104",
          flight_type: "J",
          route_code: "A",
          dep_icao: "KDFW",
          arr_icao: "KORD",
          dgt: "500",
          dlt: "900",
          tgt: "60",
          tlt: "180",
          subfleet_id: "4",
          type_rating_id: "5",
          icao_type: "A320",
          search: "morning",
          orderBy: "dpt_time",
          sortedBy: "asc",
          limit: "50",
        }).every((value) => [...query.values()].includes(value)),
      ).toBe(true);
      expect(query.has("page")).toBe(true);
    }
  });
});

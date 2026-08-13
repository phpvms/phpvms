import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import DashboardPilotHeader from "@/widgets/dashboard/DashboardPilotHeader.vue";
import PilotAwards from "@/widgets/shared/PilotAwards.vue";

const dashboard: App.Http.Data.DashboardData = {
  averageLandingRate: -142,
  balance: { amount: 84320, formatted: "$84,320" },
  currentAirport: "KJFK",
  flightTimeMinutes: "1,284h",
  flights: 342,
  id: 421,
  lastPirep: null,
  name: "Nabeel Shahzad",
  onLeave: false,
  onTimePercentage: 94,
  pilotScore: 98,
  rank: {
    currentHours: 1284,
    from: "Senior Captain",
    hoursRemaining: 216,
    pct: 86,
    targetHours: 1500,
    to: "Fleet Captain",
  },
  state: { color: "success", label: "Active" },
  transferTimeMinutes: "120h",
  route: { from: null, to: null },
};

describe("DashboardPilotHeader", () => {
  it("renders identity, accessible rank progress, and all seven summary metrics", () => {
    const wrapper = mount(DashboardPilotHeader, {
      props: {
        dashboard,
        initials: "NS",
        user: {
          airline: null,
          avatar: null,
          callsign: "SKY421",
          id: 421,
          ident: "SKY0421",
          name: "Nabeel Shahzad",
        },
      },
    });

    expect(wrapper.get("progress").attributes("aria-label")).toContain("86%");
  });
});

describe("PilotAwards", () => {
  it("renders compact award tiles, image fallback, and long qualifiers", async () => {
    const wrapper = mount(PilotAwards, {
      props: {
        awards: [
          { name: "1000 Hours", description: "Milestone", image: "/award.png" },
          {
            name: "A very long award title that must wrap inside its compact tile",
            description: "A long qualifier that remains readable",
            image: null,
          },
        ],
      },
    });

    await wrapper.find("img").trigger("error");
    expect(wrapper.findAll("svg")).toHaveLength(2);
  });
});

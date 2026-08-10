import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import DashboardPilotHeader from "@/widgets/dashboard/DashboardPilotHeader.vue";
import workspaceSource from "@/widgets/dashboard/DashboardWorkspace.vue?raw";
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

    expect(wrapper.get("h1").text()).toBe("Nabeel Shahzad");
    expect(wrapper.text()).toContain("Active");
    expect(wrapper.get(".status").attributes("data-c")).toBe("success");
    expect(wrapper.get("progress").attributes("aria-label")).toContain("86%");
    expect(wrapper.findAll(".metric")).toHaveLength(7);
    expect(wrapper.text()).toContain("$84,320");
    expect(wrapper.text()).toContain("−142 fpm");
  });

  it("preserves zero rank hours and uses em dashes for unavailable values", () => {
    const wrapper = mount(DashboardPilotHeader, {
      props: {
        dashboard: {
          ...dashboard,
          averageLandingRate: null,
          balance: null,
          currentAirport: null,
          onTimePercentage: null,
          pilotScore: null,
          rank: {
            currentHours: 0,
            from: "Cadet",
            hoursRemaining: 100,
            pct: 0,
            targetHours: 100,
            to: "First Officer",
          },
        },
        initials: "NS",
        user: null,
      },
    });

    expect(wrapper.text()).toContain("0 / 100 hrs");
    expect(wrapper.findAll("dd").filter((metric) => metric.text() === "—")).toHaveLength(4);
  });

  it("describes a highest-rank pilot without a target progress bar", () => {
    const wrapper = mount(DashboardPilotHeader, {
      props: {
        dashboard: {
          ...dashboard,
          rank: {
            currentHours: 2000,
            from: "Chief Captain",
            hoursRemaining: null,
            pct: 100,
            targetHours: null,
            to: null,
          },
        },
        initials: "NS",
        user: null,
      },
    });

    expect(wrapper.find("progress").exists()).toBe(false);
    expect(wrapper.text()).toContain("2,000 / — hrs");
    expect(wrapper.text()).toContain("Highest rank reached");
  });

  it("renders large stored values without replacing them", () => {
    const wrapper = mount(DashboardPilotHeader, {
      props: {
        dashboard: {
          ...dashboard,
          balance: { amount: 9999999999.99, formatted: "$9,999,999,999.99" },
          flightTimeMinutes: "999999h 59m",
          flights: 999999,
          onTimePercentage: 100,
          pilotScore: 100,
          transferTimeMinutes: "99999h 59m",
        },
        initials: "NS",
        user: null,
      },
    });

    expect(wrapper.text()).toContain("999,999");
    expect(wrapper.text()).toContain("999999h 59m");
    expect(wrapper.text()).toContain("$9,999,999,999.99");
    expect(wrapper.text()).toContain("100%");
  });

  it("keeps the fixed summary ahead of dashboard customization", () => {
    expect(workspaceSource.indexOf("<DashboardPilotHeader")).toBeLessThan(
      workspaceSource.indexOf("<DashboardToolbar"),
    );
    expect(workspaceSource.indexOf("<DashboardToolbar")).toBeLessThan(
      workspaceSource.indexOf("<DashboardBoard"),
    );
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

    expect(wrapper.text()).toContain("2 earned");
    expect(wrapper.findAll(".award")).toHaveLength(2);
    expect(wrapper.find("img").attributes("src")).toBe("/award.png");
    expect(wrapper.findAll("svg")).toHaveLength(1);
    await wrapper.find("img").trigger("error");
    expect(wrapper.findAll("svg")).toHaveLength(2);
    expect(wrapper.text()).toContain("A long qualifier that remains readable");
  });

  it("shows the zero-awards count and empty state", () => {
    const wrapper = mount(PilotAwards, { props: { awards: [] } });

    expect(wrapper.text()).toContain("0 earned");
    expect(wrapper.text()).toContain("No awards earned yet.");
    expect(wrapper.find(".grid").exists()).toBe(false);
  });
});

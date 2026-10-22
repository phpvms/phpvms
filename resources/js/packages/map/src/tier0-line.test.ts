import { describe, expect, it } from "vitest";
import { groupByPhaseRuns } from "./tier0-line.ts";

const p = (lon: number, phase?: string) => ({ lon, lat: 0, altitude: 0, phase });

describe("groupByPhaseRuns", () => {
  it("returns nothing for fewer than two points", () => {
    expect(groupByPhaseRuns([])).toEqual([]);
    expect(groupByPhaseRuns([p(0, "cruise")])).toEqual([]);
  });

  it("a single-phase track is one run", () => {
    const points = [p(0, "cruise"), p(1, "cruise"), p(2, "cruise")];
    const runs = groupByPhaseRuns(points);
    expect(runs).toHaveLength(1);
    expect(runs[0].phase).toBe("cruise");
    expect(runs[0].points).toEqual(points);
  });

  it("splits at each phase change, sharing the joining point between runs", () => {
    const climb1 = p(0, "climb");
    const climb2 = p(1, "climb");
    const cruise1 = p(2, "cruise");
    const cruise2 = p(3, "cruise");
    const descent1 = p(4, "descent");
    const descent2 = p(5, "descent");

    const runs = groupByPhaseRuns([climb1, climb2, cruise1, cruise2, descent1, descent2]);
    expect(runs.map((r) => r.phase)).toEqual(["climb", "cruise", "descent"]);
    // The run boundary points are shared, so consecutive LineStrings meet without a gap.
    expect(runs[0].points).toEqual([climb1, climb2, cruise1]);
    expect(runs[1].points).toEqual([cruise1, cruise2, descent1]);
    expect(runs[2].points).toEqual([descent1, descent2]);
  });

  it("drops a trailing single-point run — a LineString needs at least two points", () => {
    // The route ends mid-transition: the last point is a lone new phase with no continuation.
    const cruise1 = p(0, "cruise");
    const cruise2 = p(1, "cruise");
    const descent1 = p(2, "descent");
    const runs = groupByPhaseRuns([cruise1, cruise2, descent1]);
    expect(runs.map((r) => r.phase)).toEqual(["cruise"]);
  });

  it("points with an undefined phase get their own run rather than being dropped", () => {
    const known1 = p(0, "cruise");
    const known2 = p(1, "cruise");
    const unknown1 = p(2, undefined);
    const unknown2 = p(3, undefined);
    const runs = groupByPhaseRuns([known1, known2, unknown1, unknown2]);
    expect(runs.map((r) => r.phase)).toEqual(["cruise", undefined]);
  });
});

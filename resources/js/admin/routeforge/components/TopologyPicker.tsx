/**
 * Topology selector.
 *
 * Topology is a UX shaping concern (per Decision 5): the user picks one of
 * five conceptual shapes, and this component translates that into the unified
 * (mode, create_returns) the generator actually consumes. The mapping:
 *
 *   hub_spokes     → cartesian, no returns       (single origin, many dests)
 *   spokes_hub     → cartesian, no returns       (many origins, single dest)
 *   hub_and_spokes → cartesian, WITH returns     (auto-paired outbound/return)
 *   mesh           → cartesian, no returns       (every origin × every dest)
 *   tour          → tour mode                  (sequential origins[i] → [i+1])
 *
 * Setting create_returns is a generator-affecting change; the dirty-warning
 * modal (task 6.3.15, Chunk C) catches the case where rows already exist.
 */

import { useState } from "preact/hooks";

import { t } from "../lib/i18n";
import { form } from "../state/store";
import type { Topology } from "../state/types";
import { Field, INPUT_CLASS } from "./Field";
import { HelpModal, type HelpModalItem } from "./HelpModal";

const TOPOLOGY_ORDER: Topology[] = ["hub_spokes", "spokes_hub", "hub_and_spokes", "mesh", "tour"];

function deriveMode(topology: Topology): "cartesian" | "tour" {
  return topology === "tour" ? "tour" : "cartesian";
}

function deriveCreateReturns(topology: Topology, current: boolean): boolean {
  // hub_and_spokes is the only topology that forces returns on; everything
  // else respects whatever the user previously chose (so toggling between
  // hub_spokes and mesh doesn't silently flip a user-set returns flag).
  if (topology === "hub_and_spokes") {
    return true;
  }
  if (topology === "tour") {
    // Tour v1 does not produce return legs (see generator.ts buildTourPairs).
    return false;
  }
  return current;
}

export function TopologyPicker() {
  const f = form.value;
  const [helpOpen, setHelpOpen] = useState<boolean>(false);

  function handleChange(e: Event): void {
    const next = (e.currentTarget as HTMLSelectElement).value as Topology;
    // Read the freshest form value at apply time so concurrent edits (e.g.,
    // an airport picker mutating origins between render and event) survive
    // this update instead of being clobbered by the captured `f` snapshot.
    const current = form.value;
    form.value = {
      ...current,
      topology: next,
      mode: deriveMode(next),
      create_returns: deriveCreateReturns(next, current.create_returns),
    };
  }

  const helpItems: HelpModalItem[] = TOPOLOGY_ORDER.map((topo) => ({
    key: topo,
    label: t(`topology_options.${topo}`),
    description: t(`topology_helper.${topo}`),
  }));

  return (
    <>
      <Field
        label={t("form.topology")}
        htmlFor="rf-topology"
        hint={t(`topology_helper.${f.topology}`)}
        required
        onHelpClick={() => setHelpOpen(true)}
        helpAriaLabel="About topologies"
      >
        <select id="rf-topology" class={INPUT_CLASS} value={f.topology} onChange={handleChange}>
          {TOPOLOGY_ORDER.map((topo) => (
            <option key={topo} value={topo}>
              {t(`topology_options.${topo}`)}
            </option>
          ))}
        </select>
      </Field>
      <HelpModal
        open={helpOpen}
        title={t("form.topology")}
        subtitle="How origins and destinations combine into rows."
        items={helpItems}
        currentKey={f.topology}
        onClose={() => setHelpOpen(false)}
      />
    </>
  );
}

import type { BadgeProps } from "@nuxt/ui/components/Badge.vue";

/**
 * Translate the semantic colour token the PIREP DTOs emit
 * (`PirepListItemData::stateColor()`) into a Nuxt UI badge colour. The two
 * vocabularies almost line up — `danger`/`gray` are the ones that differ.
 */
export function stateBadgeColor(stateColor: string): BadgeProps["color"] {
  switch (stateColor) {
    case "success":
      return "success";
    case "warning":
      return "warning";
    case "danger":
      return "error";
    case "info":
      return "info";
    default:
      return "neutral";
  }
}

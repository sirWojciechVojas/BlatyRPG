<template>
  <span
    class="combat-movement-bar"
    :style="{ '--combat-movement-color': color }"
    role="meter"
    :aria-label="label"
    aria-valuemin="0"
    :aria-valuemax="safeRange"
    :aria-valuenow="safePoints"
    :title="label"
  >
    <span class="combat-movement-bar__fill" :style="{ width: `${percent}%` }" />
    <b>{{ prefix }} {{ safePoints }} / {{ safeRange }}</b>
  </span>
</template>

<script>
import { movementPercent } from "@/lib/vtt/combatPresentation";

export default {
  name: "CombatMovementBar",
  props: {
    points: { type: [Number, String], default: 0 },
    range: { type: [Number, String], default: 0 },
    prefix: { type: String, default: "PR" },
    label: { type: String, required: true },
    color: { type: String, default: "#4caf72" },
  },
  computed: {
    safePoints() {
      return Math.max(0, Number(this.points) || 0);
    },
    safeRange() {
      return Math.max(0, Number(this.range) || 0);
    },
    percent() {
      return movementPercent(this.safePoints, this.safeRange);
    },
  },
};
</script>

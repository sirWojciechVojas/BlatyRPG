<template>
  <span
    v-if="phase"
    class="campaign-calendar__moon"
    :class="`campaign-calendar__moon--${phase.key}`"
    role="img"
    :aria-label="`${name}: ${phase.name}`"
    :title="`${name}: ${phase.name}`"
  />
</template>

<script>
export default {
  name: "CalendarMoonIcon",
  props: {
    phase: { type: Object, default: null },
    name: { type: String, required: true },
  },
};
</script>

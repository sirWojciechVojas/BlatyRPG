<template>
  <div
    class="audio-level-meter"
    :class="{
      'audio-level-meter--unavailable': !available,
      'audio-level-meter--active': !available && active,
    }"
  >
    <span class="audio-level-meter__label">{{ label }}</span>
    <span
      class="audio-level-meter__track"
      :role="available ? 'meter' : 'status'"
      :aria-valuemin="available ? 0 : null"
      :aria-valuemax="available ? 100 : null"
      :aria-valuenow="available ? percentage : null"
      :aria-label="available ? label : unavailableLabel"
      :title="available ? `${label}: ${percentage}%` : unavailableLabel"
    >
      <i :style="available ? { transform: `scaleX(${normalized})` } : null"></i>
    </span>
    <output>{{ available ? `${percentage}%` : active ? "▶" : "—" }}</output>
  </div>
</template>

<script>
export default {
  name: "AudioLevelMeter",
  props: {
    value: { type: Number, default: 0 },
    label: { type: String, required: true },
    available: { type: Boolean, default: true },
    active: { type: Boolean, default: false },
    unavailableLabel: { type: String, default: "" },
  },
  computed: {
    normalized() {
      return Math.max(0, Math.min(1, Number(this.value) || 0));
    },
    percentage() {
      return Math.round(this.normalized * 100);
    },
  },
};
</script>

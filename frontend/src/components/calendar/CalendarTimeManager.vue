<template>
  <section
    class="campaign-calendar__time-manager campaign-calendar__time-manager--embedded"
    aria-label="Ustawienie aktualnego dnia kampanii"
  >
    <div class="campaign-calendar__selected-world-date" aria-live="polite">
      <span>Wybrany dzień</span>
      <strong>{{ selectedDate.formatted }}</strong>
    </div>
    <button
      type="button"
      class="is-brass"
      :disabled="busy || isCurrent"
      @click="$emit('set')"
    >
      {{ isCurrent ? "Aktualny dzień" : "Ustaw jako aktualny" }}
    </button>
  </section>
</template>

<script>
export default {
  name: "CalendarTimeManager",
  props: {
    selectedDate: { type: Object, required: true },
    worldState: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["set"],
  computed: {
    isCurrent() {
      return (
        Number(this.selectedDate.year) === Number(this.worldState.year) &&
        Number(this.selectedDate.dayOfYear) ===
          Number(this.worldState.dayOfYear)
      );
    },
  },
};
</script>

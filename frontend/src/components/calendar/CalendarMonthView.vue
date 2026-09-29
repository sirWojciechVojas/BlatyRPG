<template>
  <section
    v-if="month"
    class="campaign-calendar__month"
    :aria-label="`${month.name}, ${year}`"
  >
    <h3>{{ month.name }} · {{ year }} {{ definition.era.suffix }}</h3>
    <button
      v-for="special in adjacent.before"
      :key="`before-${special.key}`"
      type="button"
      class="campaign-calendar__special-band"
      :class="{ 'is-selected': selectedDay === special.dayOfYear }"
      @click="selectSpecial(special)"
    >
      <strong>{{ special.name }}</strong
      ><span>Dzień międzykalendarzowy</span>
    </button>
    <div class="campaign-calendar__weekdays" aria-hidden="true">
      <span v-for="name in definition.week.names" :key="name">{{ name }}</span>
    </div>
    <div class="campaign-calendar__month-grid">
      <span v-for="index in leading" :key="`empty-${index}`" class="is-empty" />
      <button
        v-for="date in dates"
        :key="date.dayOfYear"
        type="button"
        class="campaign-calendar__day"
        :class="{
          'is-world-day': isWorld(date),
          'is-selected': selectedDay === date.dayOfYear,
          'has-events': eventsOn(date).length,
        }"
        :aria-label="date.formatted"
        @click="$emit('select', date)"
      >
        <span class="campaign-calendar__day-number">{{ date.day }}</span>
        <CalendarMoonIcon :phase="date.mannslieb" name="Mannslieb" />
        <span class="campaign-calendar__event-marks" aria-hidden="true">
          <i
            v-for="event in eventsOn(date).slice(0, 4)"
            :key="event.id"
            :style="{ backgroundColor: event.color }"
          />
        </span>
        <small v-if="eventsOn(date)[0]">{{ eventsOn(date)[0].title }}</small>
      </button>
    </div>
    <button
      v-for="special in adjacent.after"
      :key="`after-${special.key}`"
      type="button"
      class="campaign-calendar__special-band"
      :class="{ 'is-selected': selectedDay === special.dayOfYear }"
      @click="selectSpecial(special)"
    >
      <strong>{{ special.name }}</strong
      ><span>Dzień międzykalendarzowy</span>
    </button>
  </section>
  <div v-else class="campaign-calendar__state" role="status">
    Przygotowywanie miesiąca…
  </div>
</template>

<script>
import CalendarMoonIcon from "./CalendarMoonIcon.vue";
import {
  adjacentSpecialDays,
  calendarDate,
  eventsForDate,
  monthDates,
} from "@/lib/calendar/calendarDate";

export default {
  name: "CalendarMonthView",
  components: { CalendarMoonIcon },
  props: {
    definition: { type: Object, required: true },
    year: { type: Number, required: true },
    monthKey: { type: String, required: true },
    selectedDay: { type: Number, default: null },
    worldState: { type: Object, required: true },
    events: { type: Array, default: () => [] },
  },
  emits: ["select"],
  computed: {
    month() {
      return this.definition.months.find((item) => item.key === this.monthKey);
    },
    dates() {
      return monthDates(this.definition, this.year, this.monthKey);
    },
    leading() {
      return this.dates[0]?.weekdayIndex || 0;
    },
    adjacent() {
      return adjacentSpecialDays(this.definition, this.monthKey);
    },
  },
  methods: {
    eventsOn(date) {
      return eventsForDate(this.events, date);
    },
    isWorld(date) {
      return (
        Number(this.worldState.year) === Number(date.year) &&
        Number(this.worldState.dayOfYear) === Number(date.dayOfYear)
      );
    },
    selectSpecial(special) {
      this.$emit(
        "select",
        calendarDate(this.definition, this.year, special.dayOfYear),
      );
    },
  },
};
</script>

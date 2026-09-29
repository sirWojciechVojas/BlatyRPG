<template>
  <section class="campaign-calendar__year" :aria-label="`Rok ${year}`">
    <template
      v-for="segment in definition.timeline"
      :key="`${segment.type}-${segment.key}`"
    >
      <button
        v-if="segment.type === 'intercalary'"
        type="button"
        class="campaign-calendar__special-band campaign-calendar__special-band--year"
        @click="select(segment.dayOfYear)"
      >
        <strong>{{ segment.name }}</strong
        ><span>{{ year }} {{ definition.era.suffix }}</span>
      </button>
      <article v-else class="campaign-calendar__year-month">
        <button
          type="button"
          class="campaign-calendar__year-title"
          @click="$emit('open-month', segment.key)"
        >
          {{ segment.name }}
        </button>
        <div class="campaign-calendar__year-weekdays" aria-hidden="true">
          <span v-for="name in definition.week.names" :key="name">{{
            name.slice(0, 1)
          }}</span>
        </div>
        <div class="campaign-calendar__year-grid">
          <i v-for="empty in leading(segment.key)" :key="`e-${empty}`" />
          <button
            v-for="date in dates(segment.key)"
            :key="date.dayOfYear"
            type="button"
            :class="{
              'is-world-day': isWorld(date),
              'is-selected': selectedDay === date.dayOfYear,
              'has-events': eventsOn(date).length,
            }"
            :title="date.formatted"
            @click="select(date.dayOfYear)"
          >
            {{ date.day }}
          </button>
        </div>
      </article>
    </template>
  </section>
</template>

<script>
import {
  calendarDate,
  eventsForDate,
  monthDates,
} from "@/lib/calendar/calendarDate";

export default {
  name: "CalendarYearView",
  props: {
    definition: { type: Object, required: true },
    year: { type: Number, required: true },
    selectedDay: { type: Number, default: null },
    worldState: { type: Object, required: true },
    events: { type: Array, default: () => [] },
  },
  emits: ["select", "open-month"],
  methods: {
    dates(key) {
      return monthDates(this.definition, this.year, key);
    },
    leading(key) {
      return this.dates(key)[0]?.weekdayIndex || 0;
    },
    eventsOn(date) {
      return eventsForDate(this.events, date);
    },
    isWorld(date) {
      return (
        Number(this.worldState.year) === Number(date.year) &&
        Number(this.worldState.dayOfYear) === Number(date.dayOfYear)
      );
    },
    select(dayOfYear) {
      this.$emit("select", calendarDate(this.definition, this.year, dayOfYear));
    },
  },
};
</script>

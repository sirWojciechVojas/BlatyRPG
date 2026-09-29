<template>
  <section class="campaign-calendar__agenda" aria-label="Agenda">
    <p v-if="!items.length" class="campaign-calendar__empty">
      Brak wydarzeń w tym roku.
    </p>
    <button
      v-for="item in items"
      :key="item.key"
      type="button"
      class="campaign-calendar__agenda-row"
      :class="{ 'is-system': item.system }"
      @click="$emit('select', item.date)"
    >
      <time
        >{{ item.date.formatted
        }}<span v-if="item.time"> · {{ item.time }}</span></time
      >
      <i :style="{ backgroundColor: item.color }" />
      <strong>{{ item.title }}</strong>
      <small>{{ item.system ? "Święto systemowe" : item.typeLabel }}</small>
    </button>
  </section>
</template>

<script>
import {
  calendarDate,
  compareCalendarPosition,
} from "@/lib/calendar/calendarDate";
import { CALENDAR_EVENT_TYPES } from "@/lib/calendar/calendarEventTypes";

export default {
  name: "CalendarAgendaView",
  props: {
    definition: { type: Object, required: true },
    year: { type: Number, required: true },
    events: { type: Array, default: () => [] },
  },
  emits: ["select"],
  computed: {
    items() {
      const system = this.definition.holidays.map((holiday) => ({
        key: `system-${holiday.key}`,
        system: true,
        title: holiday.name,
        color: "#8b682f",
        date: calendarDate(this.definition, this.year, holiday.dayOfYear),
        time: "",
        typeLabel: "Święto",
      }));
      const campaign = this.events.map((event) => ({
        key: `event-${event.id}-${event.start.year}`,
        system: false,
        title: event.title,
        color: event.color,
        date: calendarDate(
          this.definition,
          event.start.year,
          event.start.dayOfYear,
          event.start.minuteOfDay,
        ),
        time: event.allDay ? "" : event.start.time,
        typeLabel:
          CALENDAR_EVENT_TYPES.find((item) => item.key === event.type)?.label ||
          event.type,
      }));
      return [...system, ...campaign].sort((a, b) =>
        compareCalendarPosition(a.date, b.date),
      );
    },
  },
};
</script>

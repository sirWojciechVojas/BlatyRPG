<template>
  <aside class="campaign-calendar__day-panel" aria-label="Szczegóły dnia">
    <header>
      <div>
        <strong>{{ date.formatted }}</strong>
        <small
          >{{ date.weekdayName || "Dzień międzykalendarzowy" }} ·
          {{ date.season?.name }}</small
        >
      </div>
      <button v-if="canManage" type="button" @click="$emit('add-event')">
        + Wydarzenie
      </button>
    </header>
    <dl>
      <div>
        <dt>Pora roku</dt>
        <dd>{{ date.season?.name || "—" }}</dd>
      </div>
      <div>
        <dt>Dzień tygodnia</dt>
        <dd>{{ date.weekdayName || "—" }}</dd>
      </div>
      <div>
        <dt>Mannslieb</dt>
        <dd>
          <CalendarMoonIcon :phase="date.mannslieb" name="Mannslieb" />{{
            date.mannslieb?.name || "—"
          }}
        </dd>
      </div>
      <div>
        <dt>Morrslieb</dt>
        <dd class="campaign-calendar__moon-control">
          <CalendarMoonIcon :phase="morrslieb" name="Morrslieb" />{{
            morrslieb?.name || "Nieokreślona"
          }}
          <select
            v-if="canManage && morrsliebPhases.length"
            class="campaign-calendar__moon-select"
            :value="morrslieb?.key || ''"
            :disabled="busy"
            aria-label="Faza Morrslieba dla wybranego dnia"
            @change="$emit('set-morrslieb', $event.target.value || null)"
          >
            <option value="">Nieokreślona</option>
            <option
              v-for="phase in morrsliebPhases"
              :key="phase.key"
              :value="phase.key"
            >
              {{ phase.name }}
            </option>
          </select>
        </dd>
      </div>
    </dl>
    <div v-if="date.holidays.length" class="campaign-calendar__day-section">
      <h4>Święta</h4>
      <p v-for="holiday in date.holidays" :key="holiday.key">
        {{ holiday.name }} <span>systemowe</span>
      </p>
    </div>
    <div class="campaign-calendar__day-section">
      <h4>Wydarzenia kampanii</h4>
      <p v-if="!events.length" class="campaign-calendar__empty">
        Brak wydarzeń.
      </p>
      <button
        v-for="event in events"
        :key="event.id"
        type="button"
        class="campaign-calendar__day-event"
        :disabled="!canManage"
        @click="$emit('edit-event', event)"
      >
        <i :style="{ backgroundColor: event.color }" />
        <span
          ><strong>{{ event.title }}</strong
          ><small>{{
            event.allDay ? "Cały dzień" : event.start.time
          }}</small></span
        >
      </button>
    </div>
  </aside>
</template>

<script>
import CalendarMoonIcon from "./CalendarMoonIcon.vue";

export default {
  name: "CalendarDayPanel",
  components: { CalendarMoonIcon },
  props: {
    date: { type: Object, required: true },
    events: { type: Array, default: () => [] },
    morrslieb: { type: Object, default: null },
    morrsliebPhases: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ["add-event", "edit-event", "set-morrslieb"],
};
</script>

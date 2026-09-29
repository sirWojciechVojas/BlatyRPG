<template>
  <section class="campaign-calendar">
    <div v-if="loading" class="campaign-calendar__state" role="status">
      Wczytywanie kalendarza…
    </div>
    <div
      v-else-if="error && !definition"
      class="campaign-calendar__state"
      role="alert"
    >
      <strong>Nie udało się wczytać kalendarza.</strong>
      <button type="button" @click="initialize">Spróbuj ponownie</button>
    </div>
    <template v-else-if="definition && worldState">
      <CalendarToolbar
        :current-date="worldState.date.formatted"
        :view="view"
        :sync-error="syncError"
        :realtime-status="realtimeStatus"
        @year="moveYear"
        @month="moveMonth"
        @today="showWorldDate"
        @view="view = $event"
        @refresh="refresh"
      />
      <div v-if="error" class="campaign-calendar__operation-error" role="alert">
        <span>{{ operationError }}</span>
        <button type="button" @click="refresh">Wczytaj aktualny stan</button>
      </div>
      <div
        class="campaign-calendar__content"
        :class="{ 'has-time-manager': canManage }"
      >
        <div class="campaign-calendar__main">
          <CalendarMonthView
            v-if="view === 'month' && displayMonth"
            :definition="definition"
            :year="displayYear"
            :month-key="displayMonth.key"
            :selected-day="selectedDate?.dayOfYear"
            :world-state="worldState"
            :events="events"
            @select="selectDate"
          />
          <CalendarYearView
            v-else-if="view === 'year' && displayYear !== null"
            :definition="definition"
            :year="displayYear"
            :selected-day="selectedDate?.dayOfYear"
            :world-state="worldState"
            :events="events"
            @select="selectDate"
            @open-month="openMonth"
          />
          <CalendarAgendaView
            v-else-if="view === 'agenda' && displayYear !== null"
            :definition="definition"
            :year="displayYear"
            :events="events"
            @select="selectDate"
          />
          <div v-else class="campaign-calendar__state" role="status">
            Przygotowywanie kalendarza…
          </div>
        </div>
        <aside class="campaign-calendar__sidebar">
          <CalendarDayPanel
            v-if="selectedDate"
            :date="selectedDate"
            :events="selectedEvents"
            :morrslieb="selectedMorrslieb"
            :morrslieb-phases="definition.moonCycles?.morrslieb?.phases || []"
            :can-manage="canManage"
            :busy="busy"
            @add-event="openEventForm()"
            @edit-event="openEventForm"
            @set-morrslieb="setSelectedMorrslieb"
          />
          <CalendarTimeManager
            v-if="canManage && selectedDate"
            :selected-date="selectedDate"
            :world-state="worldState"
            :busy="busy"
            @set="setSelectedDate"
          />
        </aside>
      </div>
      <div v-if="eventFormOpen" class="campaign-calendar__scrim">
        <CalendarEventForm
          :key="editingEvent?.id || `new-${selectedDate.dayOfYear}`"
          :definition="definition"
          :selected-date="selectedDate"
          :event="editingEvent"
          :members="members"
          :server-error="error ? operationError : ''"
          @save="saveEvent"
          @delete="deleteEvent"
          @cancel="closeEventForm"
        />
      </div>
    </template>
  </section>
</template>

<script>
import CalendarAgendaView from "./CalendarAgendaView.vue";
import CalendarDayPanel from "./CalendarDayPanel.vue";
import CalendarEventForm from "./CalendarEventForm.vue";
import CalendarMonthView from "./CalendarMonthView.vue";
import CalendarTimeManager from "./CalendarTimeManager.vue";
import CalendarToolbar from "./CalendarToolbar.vue";
import CalendarYearView from "./CalendarYearView.vue";
import {
  calendarDate,
  eventsForDate,
  morrsliebForDate,
} from "@/lib/calendar/calendarDate";

export default {
  name: "CampaignCalendar",
  components: {
    CalendarAgendaView,
    CalendarDayPanel,
    CalendarEventForm,
    CalendarMonthView,
    CalendarTimeManager,
    CalendarToolbar,
    CalendarYearView,
  },
  props: {
    campaignId: { type: [Number, String], required: true },
    members: { type: Array, default: () => [] },
  },
  data: () => ({
    view: "month",
    displayYear: null,
    displayMonthKey: "",
    selectedDate: null,
    followsWorldDate: true,
    eventFormOpen: false,
    editingEvent: null,
  }),
  computed: {
    calendar() {
      return this.$store.state.calendar;
    },
    definition() {
      return this.calendar.definition;
    },
    worldState() {
      return this.calendar.worldState;
    },
    displayMonth() {
      return (
        this.definition?.months?.find(
          (month) => month.key === this.displayMonthKey,
        ) || null
      );
    },
    events() {
      return this.calendar.events || [];
    },
    moonOverrides() {
      return this.calendar.moonOverrides || [];
    },
    canManage() {
      return this.calendar.capabilities?.canManage === true;
    },
    loading() {
      return this.calendar.phase === "loading" && !this.definition;
    },
    busy() {
      return this.calendar.phase === "saving";
    },
    error() {
      return this.calendar.error;
    },
    syncError() {
      return this.calendar.syncError;
    },
    operationError() {
      if (
        ["calendar_revision_conflict", "calendar_event_conflict"].includes(
          this.error?.code,
        )
      ) {
        return "Kalendarz został zmieniony w innej sesji. Wczytaj aktualny stan i ponów operację.";
      }
      if (this.error?.status === 403) {
        return "Nie masz uprawnień do tej operacji.";
      }
      return "Nie udało się zapisać zmiany kalendarza.";
    },
    realtimeStatus() {
      return this.$store.state.realtime?.status || "disconnected";
    },
    selectedEvents() {
      return this.selectedDate
        ? eventsForDate(this.events, this.selectedDate)
        : [];
    },
    selectedMorrslieb() {
      return morrsliebForDate(this.moonOverrides, this.selectedDate);
    },
  },
  watch: {
    campaignId: "initialize",
    worldState: {
      deep: true,
      handler(value) {
        if (!value || !this.definition) return;
        if (this.displayYear === null || this.followsWorldDate)
          this.showWorldDate();
      },
    },
  },
  mounted() {
    this.initialize();
  },
  methods: {
    async initialize() {
      const id = Number(this.campaignId);
      if (
        Number(this.calendar.campaignId) === id &&
        this.definition &&
        this.worldState
      ) {
        this.showWorldDate();
        return;
      }
      await this.$store.dispatch("calendar/initialize", id).catch(() => {});
      if (this.definition && this.worldState) this.showWorldDate();
    },
    refresh() {
      this.$store.dispatch("calendar/refresh").catch(() => {});
      this.$store.dispatch("realtime/retry").catch(() => {});
    },
    showWorldDate() {
      const date = calendarDate(
        this.definition,
        this.worldState.year,
        this.worldState.dayOfYear,
        this.worldState.minuteOfDay,
      );
      this.displayYear = Number(this.worldState.year);
      this.displayMonthKey =
        date.monthKey ||
        this.definition.months.find(
          (month) => Number(month.startDayOfYear) > date.dayOfYear,
        )?.key ||
        this.definition.months[this.definition.months.length - 1].key;
      this.selectedDate = date;
      this.followsWorldDate = true;
      this.ensureEvents();
    },
    ensureEvents() {
      if (
        Number(this.calendar.loadedRange?.fromYear) === Number(this.displayYear)
      )
        return;
      this.$store
        .dispatch("calendar/loadEvents", { year: this.displayYear })
        .catch(() => {});
    },
    selectDate(date) {
      this.selectedDate = date;
      this.followsWorldDate = false;
    },
    moveYear(amount) {
      this.displayYear = Math.max(1, Number(this.displayYear) + Number(amount));
      this.selectedDate = calendarDate(
        this.definition,
        this.displayYear,
        Math.min(
          this.selectedDate?.dayOfYear || 1,
          this.definition.daysPerYear,
        ),
      );
      this.followsWorldDate = false;
      this.ensureEvents();
    },
    moveMonth(amount) {
      const months = this.definition.months;
      let index = months.findIndex(
        (month) => month.key === this.displayMonthKey,
      );
      index += Number(amount);
      if (index < 0) {
        if (this.displayYear <= 1) return;
        this.displayYear -= 1;
        index = months.length - 1;
      } else if (index >= months.length) {
        this.displayYear += 1;
        index = 0;
      }
      this.displayMonthKey = months[index].key;
      this.selectedDate = calendarDate(
        this.definition,
        this.displayYear,
        months[index].startDayOfYear,
      );
      this.followsWorldDate = false;
      this.ensureEvents();
    },
    openMonth(key) {
      this.displayMonthKey = key;
      this.view = "month";
      const month = this.definition.months.find((item) => item.key === key);
      this.selectDate(
        calendarDate(this.definition, this.displayYear, month.startDayOfYear),
      );
    },
    openEventForm(event = null) {
      if (!this.canManage) return;
      this.editingEvent = event;
      this.eventFormOpen = true;
    },
    closeEventForm() {
      this.eventFormOpen = false;
      this.editingEvent = null;
    },
    async saveEvent(event) {
      const action = event.id ? "calendar/updateEvent" : "calendar/createEvent";
      await this.$store
        .dispatch(action, event)
        .then(this.closeEventForm)
        .catch(() => {});
    },
    async deleteEvent(event) {
      if (!window.confirm(`Usunąć wydarzenie „${event.title}”?`)) return;
      await this.$store
        .dispatch("calendar/deleteEvent", event)
        .then(this.closeEventForm)
        .catch(() => {});
    },
    async setSelectedDate() {
      if (!this.canManage || !this.selectedDate) return;
      await this.$store
        .dispatch("calendar/setState", {
          year: this.selectedDate.year,
          dayOfYear: this.selectedDate.dayOfYear,
          minuteOfDay: this.worldState.minuteOfDay,
          showTime: false,
        })
        .catch(() => {});
    },
    async setSelectedMorrslieb(phase) {
      if (!this.canManage || !this.selectedDate) return;
      await this.$store
        .dispatch("calendar/setMorrslieb", {
          year: this.selectedDate.year,
          dayOfYear: this.selectedDate.dayOfYear,
          phase,
        })
        .catch(() => {});
    },
  },
};
</script>

<style src="./calendar.css"></style>

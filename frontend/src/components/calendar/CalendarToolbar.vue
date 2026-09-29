<template>
  <header class="campaign-calendar__toolbar">
    <div class="campaign-calendar__current">
      <strong>{{ currentDate }}</strong>
      <span :class="{ 'is-error': syncError }">
        {{ syncError ? "Synchronizacja przerwana" : realtimeLabel }}
      </span>
    </div>
    <div
      class="campaign-calendar__navigation"
      aria-label="Nawigacja kalendarza"
    >
      <button type="button" title="Poprzedni rok" @click="$emit('year', -1)">
        «
      </button>
      <button
        type="button"
        title="Poprzedni miesiąc"
        @click="$emit('month', -1)"
      >
        ‹
      </button>
      <button type="button" @click="$emit('today')">Aktualna data</button>
      <button type="button" title="Następny miesiąc" @click="$emit('month', 1)">
        ›
      </button>
      <button type="button" title="Następny rok" @click="$emit('year', 1)">
        »
      </button>
    </div>
    <div class="campaign-calendar__view-switch" aria-label="Widok kalendarza">
      <button
        v-for="item in views"
        :key="item.key"
        type="button"
        :class="{ 'is-active': view === item.key }"
        :aria-pressed="view === item.key"
        @click="$emit('view', item.key)"
      >
        {{ item.label }}
      </button>
    </div>
    <button v-if="syncError" type="button" @click="$emit('refresh')">
      Odśwież połączenie
    </button>
  </header>
</template>

<script>
export default {
  name: "CalendarToolbar",
  props: {
    currentDate: { type: String, default: "" },
    view: { type: String, required: true },
    syncError: { type: Object, default: null },
    realtimeStatus: { type: String, default: "disconnected" },
  },
  emits: ["year", "month", "today", "view", "refresh"],
  data: () => ({
    views: [
      { key: "month", label: "Miesiąc" },
      { key: "year", label: "Rok" },
      { key: "agenda", label: "Agenda" },
    ],
  }),
  computed: {
    realtimeLabel() {
      return ["ready", "syncing"].includes(this.realtimeStatus)
        ? "Czas zsynchronizowany"
        : "Tryb oczekiwania na synchronizację";
    },
  },
};
</script>

import { calendarApiClient } from "@/lib/calendar/calendarApiClient";

export const calendarState = () => ({
  campaignId: null,
  definition: null,
  worldState: null,
  events: [],
  moonOverrides: [],
  capabilities: { canManage: false, canCreateEvents: false },
  loadedRange: null,
  phase: "idle",
  error: null,
  syncError: null,
});

const normalizedError = (error) => ({
  code: String(error?.code || error?.message || "unknown_error"),
  status: Number(error?.status || 0),
  details: error?.payload?.errors || null,
});

const upsert = (items, value) => {
  const index = items.findIndex((item) => Number(item.id) === Number(value.id));
  if (index < 0) return [...items, value];
  const result = [...items];
  result.splice(index, 1, value);
  return result;
};

export const calendarMutations = {
  SWITCH_CAMPAIGN(state, campaignId) {
    Object.assign(state, calendarState(), {
      campaignId: Number(campaignId) || null,
      phase: "loading",
    });
  },
  REQUEST(state, phase = "loading") {
    state.phase = phase;
    state.error = null;
  },
  SET_CALENDAR(state, payload) {
    state.definition = payload.definition;
    state.worldState = payload.state;
    state.moonOverrides = payload.moonOverrides || [];
    state.capabilities = payload.capabilities || state.capabilities;
    state.phase = "ready";
    state.error = null;
    state.syncError = null;
  },
  SET_STATE(state, value) {
    if (
      state.worldState &&
      Number(value?.revision) < Number(state.worldState.revision)
    ) {
      return;
    }
    state.worldState = value;
    state.phase = "ready";
    state.error = null;
  },
  SET_REVISION(state, revision) {
    if (
      !state.worldState ||
      Number(revision) <= Number(state.worldState.revision)
    ) {
      return;
    }
    state.worldState = { ...state.worldState, revision: Number(revision) };
    state.phase = "ready";
    state.error = null;
  },
  SET_EVENTS(state, { events, range, revision }) {
    state.events = events || [];
    state.loadedRange = range;
    state.phase = "ready";
    if (Number(revision) > Number(state.worldState?.revision || 0)) {
      state.worldState = { ...state.worldState, revision: Number(revision) };
    }
  },
  UPSERT_EVENT(state, event) {
    state.events = upsert(state.events, event);
  },
  REMOVE_EVENT(state, eventId) {
    state.events = state.events.filter(
      (item) => Number(item.id) !== Number(eventId),
    );
  },
  APPLY_MOON(state, { year, dayOfYear, moon }) {
    state.moonOverrides = state.moonOverrides.filter(
      (item) =>
        Number(item.year) !== Number(year) ||
        Number(item.dayOfYear) !== Number(dayOfYear),
    );
    if (moon) state.moonOverrides.push(moon);
  },
  FAIL(state, error) {
    state.phase = "error";
    state.error = normalizedError(error);
  },
  SYNC_FAIL(state, error) {
    state.syncError = normalizedError(error);
  },
};

export const createCalendarActions = (api = calendarApiClient) => ({
  async initialize({ state, commit, dispatch }, campaignId) {
    const id = Number(campaignId);
    if (state.campaignId !== id) commit("SWITCH_CAMPAIGN", id);
    else commit("REQUEST", "loading");
    try {
      const payload = await api.get(id);
      if (state.campaignId !== id) return null;
      commit("SET_CALENDAR", payload);
      await dispatch("loadEvents", { year: payload.state.year });
      return payload;
    } catch (error) {
      if (state.campaignId === id) commit("FAIL", error);
      throw error;
    }
  },
  async refresh({ state, commit, dispatch }) {
    if (!state.campaignId) return null;
    try {
      const payload = await api.get(state.campaignId);
      commit("SET_CALENDAR", payload);
      await dispatch("loadEvents", {
        year: state.loadedRange?.fromYear || payload.state.year,
      });
      return payload;
    } catch (error) {
      commit("SYNC_FAIL", error);
      throw error;
    }
  },
  async loadEvents({ state, commit }, { year } = {}) {
    if (!state.campaignId || !state.definition) return [];
    const selectedYear = Number(year || state.worldState?.year);
    const range = {
      fromYear: selectedYear,
      fromDay: 1,
      toYear: selectedYear,
      toDay: state.definition.daysPerYear,
    };
    const payload = await api.events(state.campaignId, range);
    commit("SET_EVENTS", { ...payload, range });
    return payload.events;
  },
  async setState({ state, commit }, draft) {
    commit("REQUEST", "saving");
    try {
      const payload = await api.setState(state.campaignId, {
        ...draft,
        expectedRevision: state.worldState.revision,
      });
      commit("SET_STATE", payload.state);
      return payload.state;
    } catch (error) {
      commit("FAIL", error);
      throw error;
    }
  },
  async advance({ state, commit }, change) {
    commit("REQUEST", "saving");
    try {
      const payload = await api.advance(state.campaignId, {
        ...change,
        expectedRevision: state.worldState.revision,
      });
      commit("SET_STATE", payload.state);
      return payload.state;
    } catch (error) {
      commit("FAIL", error);
      throw error;
    }
  },
  async createEvent({ state, commit }, event) {
    commit("REQUEST", "saving");
    try {
      const payload = await api.createEvent(state.campaignId, {
        ...event,
        expectedRevision: state.worldState.revision,
      });
      commit("UPSERT_EVENT", payload.event);
      commit("SET_REVISION", payload.revision);
      return payload.event;
    } catch (error) {
      commit("FAIL", error);
      throw error;
    }
  },
  async updateEvent({ state, commit }, event) {
    const { id, revision, ...changes } = event;
    commit("REQUEST", "saving");
    try {
      const payload = await api.updateEvent(state.campaignId, id, {
        ...changes,
        expectedRevision: state.worldState.revision,
        eventRevision: revision,
      });
      commit("UPSERT_EVENT", payload.event);
      commit("SET_REVISION", payload.revision);
      return payload.event;
    } catch (error) {
      commit("FAIL", error);
      throw error;
    }
  },
  async deleteEvent({ state, commit }, event) {
    commit("REQUEST", "saving");
    try {
      const payload = await api.deleteEvent(state.campaignId, event.id, {
        expectedRevision: state.worldState.revision,
        eventRevision: event.revision,
      });
      commit("REMOVE_EVENT", event.id);
      commit("SET_REVISION", payload.revision);
    } catch (error) {
      commit("FAIL", error);
      throw error;
    }
  },
  async setMorrslieb({ state, commit }, change) {
    commit("REQUEST", "saving");
    try {
      const payload = await api.setMorrslieb(state.campaignId, {
        ...change,
        expectedRevision: state.worldState.revision,
      });
      commit("APPLY_MOON", payload);
      commit("SET_REVISION", payload.revision);
      return payload.moon;
    } catch (error) {
      commit("FAIL", error);
      throw error;
    }
  },
  async applyRealtimeEvent({ state, commit, dispatch }, event) {
    const revision = Number(event?.payload?.revision);
    if (
      Number(event?.campaignId) !== Number(state.campaignId) ||
      !Number.isInteger(revision) ||
      revision <= Number(state.worldState?.revision || 0)
    ) {
      return false;
    }
    if (event.type === "calendar.state.updated" && event.payload.state) {
      commit("SET_STATE", event.payload.state);
      return true;
    }
    commit("SET_REVISION", revision);
    if (event.type === "calendar.moon.updated") {
      commit("APPLY_MOON", event.payload);
      return true;
    }
    if (event.type.startsWith("calendar.event.")) {
      await dispatch("loadEvents", {
        year: state.loadedRange?.fromYear || state.worldState?.year,
      });
      return true;
    }
    return false;
  },
});

export const createCalendarModule = (api = calendarApiClient) => ({
  namespaced: true,
  state: calendarState,
  mutations: calendarMutations,
  actions: createCalendarActions(api),
});

export default createCalendarModule();

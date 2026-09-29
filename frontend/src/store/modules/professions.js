import { professionApiClient } from "@/lib/professions/professionApiClient";

let catalogSequence = 0;
let characterSequence = 0;

const emptyMeta = () => ({
  system_id: null,
  system_code: "",
  system_name: "",
  campaign_id: null,
  total: 0,
  basic: 0,
  advanced: 0,
  rules_available: false,
  rules_status: "unavailable",
});

const emptyCharacter = () => ({
  id: null,
  phase: "idle",
  error: null,
  record: null,
  current: null,
  history: [],
  historyAvailable: null,
  capabilities: {
    canManageProfession: false,
    canReorderProfessionHistory: false,
  },
  requestKey: "",
});

export const professionState = () => ({
  campaignId: null,
  phase: "idle",
  error: null,
  items: [],
  meta: emptyMeta(),
  requestKey: "",
  query: "",
  type: "all",
  page: 1,
  selectedId: null,
  section: "info",
  view: "catalog",
  character: emptyCharacter(),
});

const numericId = (value) => {
  const id = Number(value);
  return Number.isInteger(id) && id > 0 ? id : null;
};

const errorPayload = (error) => ({
  code: String(error?.code || error?.message || "profession_load_failed"),
  status: Number(error?.status || 0),
  network: error?.network === true,
});

export default {
  namespaced: true,
  state: professionState,
  getters: {
    selected(state) {
      return (
        state.items.find((item) => Number(item.id) === state.selectedId) || null
      );
    },
  },
  mutations: {
    SET_CONTEXT(state, campaignId) {
      const id = numericId(campaignId);
      if (state.campaignId === id) return;
      const fresh = professionState();
      Object.assign(state, fresh, { campaignId: id });
    },
    CATALOG_LOADING(state, requestKey) {
      state.phase = "loading";
      state.error = null;
      state.requestKey = requestKey;
    },
    CATALOG_READY(state, { requestKey, payload }) {
      if (state.requestKey !== requestKey) return;
      state.items = Array.isArray(payload?.items) ? payload.items : [];
      state.meta = { ...emptyMeta(), ...(payload?.meta || {}) };
      state.phase = "ready";
      state.error = null;
      if (
        state.selectedId &&
        !state.items.some((item) => Number(item.id) === state.selectedId)
      ) {
        state.selectedId = null;
      }
    },
    CATALOG_FAILED(state, { requestKey, error }) {
      if (state.requestKey !== requestKey) return;
      state.phase = "error";
      state.error = error;
    },
    SET_QUERY(state, query) {
      state.query = String(query || "");
      state.page = 1;
    },
    SET_TYPE(state, type) {
      state.type = ["all", "basic", "advanced"].includes(type) ? type : "all";
      state.page = 1;
    },
    SET_PAGE(state, page) {
      state.page = Math.max(1, Number(page) || 1);
    },
    SELECT(state, id) {
      state.selectedId = numericId(id);
      state.section = "info";
    },
    SET_SECTION(state, section) {
      if (["info", "details"].includes(section)) {
        state.section = section;
      }
    },
    SET_VIEW(state, view) {
      state.view = view === "character" ? "character" : "catalog";
    },
    CHARACTER_CLEAR(state, characterId = null) {
      state.character = { ...emptyCharacter(), id: numericId(characterId) };
    },
    CHARACTER_LOADING(state, { id, requestKey }) {
      state.character = {
        ...emptyCharacter(),
        id,
        phase: "loading",
        requestKey,
      };
    },
    CHARACTER_READY(state, { requestKey, payload }) {
      if (state.character.requestKey !== requestKey) return;
      state.character.phase = "ready";
      state.character.error = null;
      state.character.record = payload?.character || null;
      state.character.current = payload?.current || null;
      state.character.history = Array.isArray(payload?.history)
        ? payload.history
        : [];
      state.character.historyAvailable = payload?.historyAvailable === true;
      state.character.capabilities = {
        canManageProfession:
          payload?.capabilities?.canManageProfession === true,
        canReorderProfessionHistory:
          payload?.capabilities?.canReorderProfessionHistory === true,
      };
    },
    CHARACTER_FAILED(state, { requestKey, error }) {
      if (state.character.requestKey !== requestKey) return;
      state.character.phase = "error";
      state.character.error = error;
    },
  },
  actions: {
    async loadCatalog({ state, commit }, { campaignId, force = false }) {
      const id = numericId(campaignId);
      if (!id) return;
      if (state.campaignId !== id) commit("SET_CONTEXT", id);
      if (!force && ["loading", "ready"].includes(state.phase)) return;
      const requestKey = `${id}:${++catalogSequence}`;
      commit("CATALOG_LOADING", requestKey);
      try {
        const payload = await professionApiClient.catalog(id);
        if (state.campaignId !== id) return;
        commit("CATALOG_READY", { requestKey, payload });
      } catch (error) {
        commit("CATALOG_FAILED", { requestKey, error: errorPayload(error) });
      }
    },
    async loadCharacter(
      { state, commit },
      { campaignId, characterId, force = false },
    ) {
      const campaign = numericId(campaignId);
      const character = numericId(characterId);
      if (!campaign || !character) {
        commit("CHARACTER_CLEAR", character);
        return;
      }
      if (state.campaignId !== campaign) commit("SET_CONTEXT", campaign);
      if (
        !force &&
        state.character.id === character &&
        ["loading", "ready"].includes(state.character.phase)
      ) {
        return;
      }
      const requestKey = `${campaign}:${character}:${++characterSequence}`;
      commit("CHARACTER_LOADING", { id: character, requestKey });
      try {
        const payload = await professionApiClient.characterHistory(
          campaign,
          character,
        );
        if (state.campaignId !== campaign || state.character.id !== character) {
          return;
        }
        commit("CHARACTER_READY", { requestKey, payload });
      } catch (error) {
        commit("CHARACTER_FAILED", { requestKey, error: errorPayload(error) });
      }
    },
    async changeCharacterProfession(
      { dispatch },
      { campaignId, characterId, professionId },
    ) {
      const result = await professionApiClient.changeCharacterProfession(
        campaignId,
        characterId,
        professionId,
      );
      await dispatch("loadCharacter", {
        campaignId,
        characterId,
        force: true,
      });
      return result;
    },
    async reorderCharacterProfessions(
      { dispatch },
      { campaignId, characterId, historyIds },
    ) {
      const result = await professionApiClient.reorderCharacterProfessions(
        campaignId,
        characterId,
        historyIds,
      );
      await dispatch("loadCharacter", {
        campaignId,
        characterId,
        force: true,
      });
      return result;
    },
    async deleteCharacterProfession(
      { dispatch },
      { campaignId, characterId, historyId },
    ) {
      const result = await professionApiClient.deleteCharacterProfession(
        campaignId,
        characterId,
        historyId,
      );
      await dispatch("loadCharacter", {
        campaignId,
        characterId,
        force: true,
      });
      return result;
    },
    async activateCharacterProfession(
      { dispatch },
      { campaignId, characterId, historyId },
    ) {
      const result = await professionApiClient.activateCharacterProfession(
        campaignId,
        characterId,
        historyId,
      );
      await dispatch("loadCharacter", {
        campaignId,
        characterId,
        force: true,
      });
      return result;
    },
  },
};

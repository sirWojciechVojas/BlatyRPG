import { magicApiClient } from "@/lib/magic/magicApiClient";
import { createMagicCastMessage } from "@/lib/chat/magicCastMessage";

const keyOf = (campaignId, characterId) =>
  `${Number(campaignId)}:${Number(characterId)}`;

const emptyBook = () => ({
  phase: "idle",
  error: null,
  data: null,
  activeCast: null,
});

const module = {
  namespaced: true,
  state: () => ({ books: {} }),
  getters: {
    book: (state) => (campaignId, characterId) =>
      state.books[keyOf(campaignId, characterId)] || emptyBook(),
  },
  mutations: {
    patchBook(state, { key, changes }) {
      state.books[key] = {
        ...(state.books[key] || emptyBook()),
        ...changes,
      };
    },
  },
  actions: {
    async load({ commit }, { campaignId, characterId }) {
      const key = keyOf(campaignId, characterId);
      commit("patchBook", { key, changes: { phase: "loading", error: null } });
      try {
        const data = await magicApiClient.get(campaignId, characterId);
        commit("patchBook", { key, changes: { phase: "ready", data } });
        return data;
      } catch (error) {
        commit("patchBook", { key, changes: { phase: "error", error } });
        throw error;
      }
    },

    async preference({ dispatch }, { campaignId, characterId, changes }) {
      await magicApiClient.preferences(campaignId, characterId, changes);
      return dispatch("load", { campaignId, characterId });
    },

    async learn({ dispatch }, { campaignId, characterId, request }) {
      const result = await magicApiClient.requestLearning(
        campaignId,
        characterId,
        request,
      );
      await dispatch("load", { campaignId, characterId });
      return result;
    },

    async decideLearning(
      { dispatch },
      { campaignId, characterId, requestId, decision },
    ) {
      const result = await magicApiClient.decideLearning(
        campaignId,
        requestId,
        decision,
      );
      await dispatch("load", { campaignId, characterId });
      return result;
    },

    async createCast({ commit }, { campaignId, characterId, declaration }) {
      const key = keyOf(campaignId, characterId);
      const result = await magicApiClient.createCast(
        campaignId,
        characterId,
        declaration,
      );
      commit("patchBook", { key, changes: { activeCast: result.cast } });
      return result.cast;
    },

    async channel({ commit }, { campaignId, characterId, castId }) {
      const key = keyOf(campaignId, characterId);
      const result = await magicApiClient.channel(campaignId, castId);
      commit("patchBook", { key, changes: { activeCast: result.cast } });
      return result.cast;
    },

    async advanceCast({ commit }, { campaignId, characterId, castId }) {
      const key = keyOf(campaignId, characterId);
      const result = await magicApiClient.advanceCast(campaignId, castId);
      commit("patchBook", { key, changes: { activeCast: result.cast } });
      return result.cast;
    },

    async resolveCast(
      { commit, dispatch, state },
      { campaignId, characterId, castId },
    ) {
      const key = keyOf(campaignId, characterId);
      const result = await magicApiClient.resolveCast(campaignId, castId);
      commit("patchBook", { key, changes: { activeCast: result.cast } });
      if (!result.duplicate) {
        const body = createMagicCastMessage({
          characterName: state.books[key]?.data?.character?.name,
          cast: result.cast,
        });
        if (body) {
          dispatch("realtime/sendChatMessage", body, { root: true });
        }
      }
      await dispatch("load", { campaignId, characterId });
      commit("patchBook", { key, changes: { activeCast: result.cast } });
      return result.cast;
    },

    async createRitual({ dispatch }, { campaignId, characterId, ritual }) {
      const result = await magicApiClient.createRitual(
        campaignId,
        characterId,
        ritual,
      );
      await dispatch("load", { campaignId, characterId });
      return result.ritual;
    },
  },
};

export default module;
export { emptyBook, keyOf };

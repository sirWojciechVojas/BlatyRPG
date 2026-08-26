import { normalizedError } from "./actions";

export const createCombatActions = (api) => ({
  async loadCombat({ state, commit }, requestedSceneId = null) {
    const sceneId = Number(requestedSceneId || state.selectedSceneId);
    if (!sceneId || sceneId !== Number(state.selectedSceneId) || !api?.get) {
      return null;
    }
    commit("SET_COMBAT_PHASE", "loading");
    try {
      const result = await api.get(state.campaignId, sceneId);
      if (sceneId !== Number(state.selectedSceneId)) return null;
      commit("RECEIVE_COMBAT", { sceneId, ...result });
      return result.combat;
    } catch (error) {
      commit("COMBAT_FAILED", normalizedError(error));
      throw error;
    }
  },
});

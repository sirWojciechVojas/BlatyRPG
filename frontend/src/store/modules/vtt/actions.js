const normalizedError = (error) => ({
  code: String(error?.code || error?.message || "unknown_error"),
  status: Number(error?.status || 0),
  network: error?.network === true,
  details: error?.payload?.errors || null,
});

const forbiddenError = () => {
  const error = new Error("forbidden");
  error.code = "forbidden";
  error.status = 403;
  return error;
};

const startRequest = (state, commit, phase) => {
  const requestId = state.requestId + 1;
  commit("BEGIN_REQUEST", { phase, requestId });
  return requestId;
};

const failRequest = (commit, requestId, error) => {
  commit("REQUEST_FAILED", { requestId, error: normalizedError(error) });
  throw error;
};

const assertCanManage = (state) => {
  if (!state.capabilities.canManage) throw forbiddenError();
};

export const createVttActions = (api) => ({
  async initialize({ state, commit }) {
    const requestId = startRequest(state, commit, "loading");
    try {
      const collection = await api.list(state.campaignId);
      if (state.requestId !== requestId) return;
      commit("RECEIVE_COLLECTION", collection);
      if (state.selectedSceneId !== null) {
        const snapshot = await api.get(state.campaignId, state.selectedSceneId);
        if (state.requestId !== requestId) return;
        commit("UPSERT_SCENE", snapshot.scene);
        commit("SET_CAPABILITIES", snapshot.capabilities);
      }
      commit("REQUEST_READY", requestId);
    } catch (error) {
      if (state.requestId === requestId) failRequest(commit, requestId, error);
    }
  },
  async selectScene({ state, commit }, sceneId) {
    commit("SELECT_SCENE", sceneId);
    const requestId = startRequest(state, commit, "loading");
    try {
      const snapshot = await api.get(state.campaignId, sceneId);
      if (state.requestId !== requestId) return;
      commit("UPSERT_SCENE", snapshot.scene);
      commit("SET_CAPABILITIES", snapshot.capabilities);
      commit("REQUEST_READY", requestId);
    } catch (error) {
      if (state.requestId === requestId) failRequest(commit, requestId, error);
    }
  },
  async createScene({ state, commit }, draft) {
    assertCanManage(state);
    const requestId = startRequest(state, commit, "saving");
    try {
      const result = await api.create(state.campaignId, draft);
      if (state.requestId !== requestId) return null;
      commit("UPSERT_SCENE", result.scene);
      commit("SET_CAPABILITIES", result.capabilities);
      commit("SELECT_SCENE", result.scene.id);
      commit("REQUEST_READY", requestId);
      return result.scene;
    } catch (error) {
      return failRequest(commit, requestId, error);
    }
  },
  async updateSelectedScene({ state, getters, commit }, changes) {
    assertCanManage(state);
    const scene = getters.selectedScene;
    if (!scene) throw new Error("scene_required");
    const requestId = startRequest(state, commit, "saving");
    try {
      const result = await api.update(state.campaignId, scene.id, {
        ...changes,
        revision: scene.revision,
      });
      if (state.requestId !== requestId) return null;
      commit("UPSERT_SCENE", result.scene);
      commit("SET_CAPABILITIES", result.capabilities);
      commit("REQUEST_READY", requestId);
      return result.scene;
    } catch (error) {
      return failRequest(commit, requestId, error);
    }
  },
  async deleteSelectedScene({ state, getters, commit, dispatch }) {
    assertCanManage(state);
    const scene = getters.selectedScene;
    if (!scene) throw new Error("scene_required");
    const requestId = startRequest(state, commit, "saving");
    try {
      const result = await api.remove(
        state.campaignId,
        scene.id,
        scene.revision,
      );
      if (state.requestId !== requestId) return null;
      commit("REMOVE_SCENE", scene.id);
      commit("SET_ACTIVE_SCENE", result.activeSceneId);
      if (state.selectedSceneId !== null) {
        return dispatch("selectScene", state.selectedSceneId);
      }
      commit("REQUEST_READY", requestId);
    } catch (error) {
      return failRequest(commit, requestId, error);
    }
  },
  async activateSelectedScene({ state, getters, commit }) {
    assertCanManage(state);
    const scene = getters.selectedScene;
    if (!scene) throw new Error("scene_required");
    const requestId = startRequest(state, commit, "saving");
    try {
      const result = await api.activate(
        state.campaignId,
        scene.id,
        scene.revision,
      );
      if (state.requestId !== requestId) return null;
      commit("UPSERT_SCENE", result.scene);
      commit("SET_ACTIVE_SCENE", result.activeSceneId);
      commit("SET_CAPABILITIES", result.capabilities);
      commit("REQUEST_READY", requestId);
      return result.scene;
    } catch (error) {
      return failRequest(commit, requestId, error);
    }
  },
});

const hasScene = (state, sceneId) =>
  state.scenes.some((scene) => scene.id === sceneId);

export const vttMutations = {
  SET_CAMPAIGN(state, campaignId) {
    if (state.campaignId === campaignId) return;
    state.campaignId = campaignId;
    state.scenes = [];
    state.activeSceneId = null;
    state.selectedSceneId = null;
    state.capabilities = {
      canManage: false,
      canViewHidden: false,
    };
    state.phase = "idle";
    state.error = null;
    state.unauthorized = false;
    state.requestId += 1;
  },
  BEGIN_REQUEST(state, { phase, requestId }) {
    state.phase = phase;
    state.requestId = requestId;
    state.error = null;
    state.unauthorized = false;
  },
  RECEIVE_COLLECTION(state, collection) {
    state.scenes = collection.items;
    state.activeSceneId = collection.activeSceneId;
    state.capabilities = collection.capabilities;
    if (!hasScene(state, state.selectedSceneId)) {
      state.selectedSceneId = hasScene(state, collection.activeSceneId)
        ? collection.activeSceneId
        : (collection.items[0]?.id ?? null);
    }
  },
  SELECT_SCENE(state, sceneId) {
    state.selectedSceneId = sceneId;
  },
  UPSERT_SCENE(state, scene) {
    if (!scene) return;
    const index = state.scenes.findIndex((item) => item.id === scene.id);
    if (index === -1) state.scenes.push(scene);
    else state.scenes.splice(index, 1, scene);
  },
  REMOVE_SCENE(state, sceneId) {
    const removedActiveScene = state.activeSceneId === sceneId;
    state.scenes = state.scenes.filter((scene) => scene.id !== sceneId);
    if (removedActiveScene) state.activeSceneId = null;
    if (state.selectedSceneId === sceneId) {
      state.selectedSceneId = hasScene(state, state.activeSceneId)
        ? state.activeSceneId
        : (state.scenes[0]?.id ?? null);
    }
  },
  SET_ACTIVE_SCENE(state, sceneId) {
    state.activeSceneId = sceneId;
  },
  SET_CAPABILITIES(state, capabilities) {
    state.capabilities = capabilities;
  },
  REQUEST_READY(state, requestId) {
    if (state.requestId !== requestId) return;
    state.phase = "ready";
  },
  REQUEST_FAILED(state, { requestId, error }) {
    if (state.requestId !== requestId) return;
    state.phase = "error";
    state.error = error;
    state.unauthorized = error.status === 401 || error.status === 403;
  },
};

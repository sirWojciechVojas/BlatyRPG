import { elementActionOptions } from "./elementActionOptions";
import { createSceneElementActions } from "./sceneElementActions";

export const normalizedError = (error) => ({
  code: String(error?.code || error?.message || "unknown_error"),
  status: Number(error?.status || 0),
  network: error?.network === true,
  details: error?.details || error?.payload?.errors || null,
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

const SCENE_LOADING_STEP_IDS = Object.freeze([
  "catalog",
  "scene",
  "tokens",
  "tiles",
  "walls",
  "lights",
  "fog",
  "regions",
  "combat",
  "movement",
  "assets",
  "render",
]);

const beginSceneLoading = (commit, sceneId, options = {}) => {
  const loadingCatalog = options.loadingCatalog === true;
  const loadingScene = options.loadingScene !== false;
  commit("BEGIN_SCENE_LOADING", {
    sceneId,
    steps: SCENE_LOADING_STEP_IDS.map((id) => ({
      id,
      status:
        id === "catalog"
          ? loadingCatalog
            ? "loading"
            : "ready"
          : id === "scene" && loadingScene
            ? "loading"
            : "pending",
    })),
  });
};

const setSceneLoadingStep = (commit, id, status, detail = "") => {
  commit("SET_SCENE_LOADING_STEP", { id, status, detail });
};

const loadSceneLayers = async (context, requestId, options = {}) => {
  const { state, commit, dispatch } = context;
  const trackLoading = options.trackLoading === true;
  const loaders = [
    ["tokens", () => dispatch("loadTokens")],
    ["tiles", () => dispatch("loadTiles")],
    ["walls", () => dispatch("loadWalls")],
    ["lights", () => dispatch("loadLights")],
    ["fog", () => dispatch("loadFog")],
    ["regions", () => dispatch("loadRegions")],
    ["combat", () => dispatch("loadCombat")],
    [
      "movement",
      () =>
        options.includeMovement === true
          ? dispatch("loadMovementRequests")
          : Promise.resolve(),
    ],
  ];

  for (const [id, load] of loaders) {
    if (trackLoading) setSceneLoadingStep(commit, id, "loading");
    await load();
    if (state.requestId !== requestId) return false;
    if (trackLoading) setSceneLoadingStep(commit, id, "ready");
  }
  return true;
};

export const createVttActions = (
  api,
  tokenApi,
  wallApi,
  lightApi,
  tileApi,
  movementRequestApi,
  fogApi,
  regionApi,
  tokenSyncApi,
) => ({
  ...createSceneElementActions(
    wallApi,
    elementActionOptions("wall", "Walls", normalizedError),
  ),
  async interactWall(
    { state, commit },
    { wall, doorState, actingTokenIds = [], silent = false },
  ) {
    if (!wall || typeof wallApi?.interact !== "function") return null;
    commit("SET_WALL_PHASE", "saving");
    try {
      const updated = await wallApi.interact(
        state.campaignId,
        wall.sceneId,
        wall.id,
        wall.revision,
        doorState,
        actingTokenIds,
        silent,
      );
      commit("UPSERT_WALL", updated);
      commit("SET_WALL_PHASE", "ready");
      return updated;
    } catch (error) {
      commit("WALL_FAILED", normalizedError(error));
      throw error;
    }
  },
  ...createSceneElementActions(
    lightApi,
    elementActionOptions("light", "Lights", normalizedError),
  ),
  ...createSceneElementActions(
    tileApi,
    elementActionOptions("tile", "Tiles", normalizedError),
  ),
  ...createSceneElementActions(
    regionApi,
    elementActionOptions("region", "Regions", normalizedError),
  ),
  async initialize({ state, commit, dispatch }, options = {}) {
    const showLoader = options.showLoader === true;
    const requestId = startRequest(state, commit, "loading");
    if (showLoader) beginSceneLoading(commit, null, { loadingCatalog: true });
    try {
      const collection = await api.list(state.campaignId);
      if (state.requestId !== requestId) return;
      commit("RECEIVE_COLLECTION", collection);
      if (showLoader) setSceneLoadingStep(commit, "catalog", "ready");
      if (state.selectedSceneId !== null) {
        const snapshot = await api.get(state.campaignId, state.selectedSceneId);
        if (state.requestId !== requestId) return;
        commit("UPSERT_SCENE", snapshot.scene);
        commit("SET_CAPABILITIES", snapshot.capabilities);
      }
      if (showLoader) setSceneLoadingStep(commit, "scene", "ready");
      const loaded = await loadSceneLayers(
        { state, commit, dispatch },
        requestId,
        { includeMovement: true, trackLoading: showLoader },
      );
      if (!loaded) return;
      commit("REQUEST_READY", requestId);
    } catch (error) {
      if (state.requestId === requestId) failRequest(commit, requestId, error);
    }
  },
  async loadMovementRequests({ state, commit }) {
    if (!state.campaignId || typeof movementRequestApi?.list !== "function")
      return;
    commit("SET_MOVEMENT_REQUEST_PHASE", "loading");
    try {
      const result = await movementRequestApi.list(state.campaignId);
      commit("RECEIVE_MOVEMENT_REQUESTS", result);
    } catch (error) {
      commit("MOVEMENT_REQUEST_FAILED", normalizedError(error));
      throw error;
    }
  },
  async selectScene({ state, commit, dispatch }, sceneId) {
    commit("SELECT_SCENE", sceneId);
    const requestId = startRequest(state, commit, "loading");
    try {
      const snapshot = await api.get(state.campaignId, sceneId);
      if (state.requestId !== requestId) return;
      commit("UPSERT_SCENE", snapshot.scene);
      commit("SET_CAPABILITIES", snapshot.capabilities);
      const loaded = await loadSceneLayers(
        { state, commit, dispatch },
        requestId,
      );
      if (!loaded) return;
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
  async duplicateSelectedScene({ state, getters, commit, dispatch }, name) {
    assertCanManage(state);
    const scene = getters.selectedScene;
    if (!scene) throw new Error("scene_required");
    const requestId = startRequest(state, commit, "saving");
    try {
      const result = await api.duplicate(state.campaignId, scene.id, name);
      if (state.requestId !== requestId) return null;
      commit("UPSERT_SCENE", result.scene);
      commit("SET_CAPABILITIES", result.capabilities);
      commit("SELECT_SCENE", result.scene.id);
      commit("REQUEST_READY", requestId);
      await Promise.all([
        dispatch("loadTokens"),
        dispatch("loadWalls"),
        dispatch("loadLights"),
        dispatch("loadTiles"),
        dispatch("loadCombat"),
        dispatch("loadFog"),
        dispatch("loadRegions"),
      ]);
      return result.scene;
    } catch (error) {
      return failRequest(commit, requestId, error);
    }
  },
  async updateScene({ state, commit }, payload = {}) {
    assertCanManage(state);
    const campaignId = state.campaignId;
    const sceneId = Number(payload.sceneId);
    const scene = state.scenes.find((item) => Number(item.id) === sceneId);
    if (!scene) throw new Error("scene_required");
    const changes = payload.changes || {};
    const requestId = startRequest(state, commit, "saving");
    try {
      const result = await api.update(campaignId, scene.id, {
        ...changes,
        revision: scene.revision,
      });
      if (state.campaignId !== campaignId) return null;
      commit("UPSERT_SCENE", result.scene);
      commit("SET_CAPABILITIES", result.capabilities);
      commit("REQUEST_READY", requestId);
      return result.scene;
    } catch (error) {
      return failRequest(commit, requestId, error);
    }
  },
  updateSelectedScene({ state, dispatch }, changes) {
    if (state.selectedSceneId === null) throw new Error("scene_required");
    return dispatch("updateScene", {
      sceneId: state.selectedSceneId,
      changes,
    });
  },
  async transitionDarkness({ state, commit, dispatch }, payload = {}) {
    assertCanManage(state);
    const sceneId = Number(payload.sceneId) || state.selectedSceneId;
    const scene = state.scenes.find(
      (item) => Number(item.id) === Number(sceneId),
    );
    if (!scene || typeof api?.transitionDarkness !== "function")
      throw new Error("scene_required");
    const requestId = startRequest(state, commit, "saving");
    try {
      const result = await api.transitionDarkness(
        state.campaignId,
        scene.id,
        scene.revision,
        payload.target,
        payload.duration || 1500,
      );
      commit("UPSERT_SCENE", result.scene);
      commit("REQUEST_READY", requestId);
      Promise.resolve(
        dispatch("realtime/syncSceneLighting", result.scene, { root: true }),
      ).catch(() => {});
      return result.scene;
    } catch (error) {
      return failRequest(commit, requestId, error);
    }
  },
  async deleteScene({ state, commit, dispatch }, payload = {}) {
    assertCanManage(state);
    const sceneId = Number(payload.sceneId);
    const scene = state.scenes.find((item) => Number(item.id) === sceneId);
    if (!scene) throw new Error("scene_required");
    const requestId = startRequest(state, commit, "saving");
    try {
      const selectedWasDeleted = Number(state.selectedSceneId) === sceneId;
      const result = await api.remove(
        state.campaignId,
        scene.id,
        scene.revision,
      );
      if (state.requestId !== requestId) return null;
      commit("REMOVE_SCENE", scene.id);
      commit("SET_ACTIVE_SCENE", result.activeSceneId);
      if (selectedWasDeleted && state.selectedSceneId !== null) {
        return dispatch("selectScene", state.selectedSceneId);
      }
      commit("REQUEST_READY", requestId);
      return result;
    } catch (error) {
      return failRequest(commit, requestId, error);
    }
  },
  deleteSelectedScene({ state, dispatch }) {
    if (state.selectedSceneId === null) throw new Error("scene_required");
    return dispatch("deleteScene", { sceneId: state.selectedSceneId });
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
  async loadTokens({ state, commit }, options = {}) {
    const sceneId = state.selectedSceneId;
    if (sceneId === null || typeof tokenApi?.list !== "function") return;
    const silent = options?.silent === true;
    if (!silent) commit("SET_TOKEN_PHASE", "loading");
    try {
      const result = await tokenApi.list(state.campaignId, sceneId);
      if (state.selectedSceneId !== sceneId) return;
      commit("RECEIVE_TOKENS", { sceneId, ...result, silent });
    } catch (error) {
      commit("TOKEN_FAILED", normalizedError(error));
      throw error;
    }
  },
  async loadTokenSync({ state, commit }) {
    if (!state.campaignId || typeof tokenSyncApi?.list !== "function") return;
    commit("SET_TOKEN_SYNC_PHASE", "loading");
    try {
      const catalog = await tokenSyncApi.list(state.campaignId);
      commit("RECEIVE_TOKEN_SYNC", catalog);
      return catalog;
    } catch (error) {
      commit("TOKEN_SYNC_FAILED", normalizedError(error));
      throw error;
    }
  },
  async previewTokenSync({ state, commit }, payload = {}) {
    if (typeof tokenSyncApi?.preview !== "function") return null;
    commit("SET_TOKEN_SYNC_PHASE", "loading");
    try {
      const preview = await tokenSyncApi.preview(
        state.campaignId,
        payload.sourceTokenId,
        payload.targetTokenIds,
      );
      commit("SET_TOKEN_SYNC_PHASE", "ready");
      return preview;
    } catch (error) {
      commit("TOKEN_SYNC_FAILED", normalizedError(error));
      throw error;
    }
  },
  async commandTokenSync({ state, commit, dispatch }, request = {}) {
    const action = String(request.action || "");
    const data = request.data || {};
    const methods = {
      transfer: () => tokenSyncApi?.transfer(state.campaignId, data),
      createLinks: () => tokenSyncApi?.createLinks(state.campaignId, data),
      updateLink: () =>
        tokenSyncApi?.updateLink(state.campaignId, data.linkId, data.enabled),
      applyLink: () => tokenSyncApi?.applyLink(state.campaignId, data.linkId),
      deleteLink: () => tokenSyncApi?.deleteLink(state.campaignId, data.linkId),
    };
    if (!methods[action]) throw new TypeError("token_sync_action_invalid");
    commit("SET_TOKEN_SYNC_PHASE", "saving");
    try {
      const sent = await dispatch(
        "realtime/commandTokenSync",
        { action, data },
        { root: true },
      );
      const result = sent === true ? null : await methods[action]();
      for (const entry of result?.synchronizedTokens || []) {
        if (entry?.token) commit("UPSERT_TOKEN", entry.token);
      }
      await dispatch("loadTokenSync");
      return result || { accepted: true };
    } catch (error) {
      commit("TOKEN_SYNC_FAILED", normalizedError(error));
      throw error;
    }
  },
  async loadFog({ state, commit }, targetUserId = null) {
    const sceneId = state.selectedSceneId;
    if (sceneId === null || typeof fogApi?.get !== "function") return null;
    commit("SET_FOG_PHASE", "loading");
    try {
      const fog = await fogApi.get(state.campaignId, sceneId, targetUserId);
      if (state.selectedSceneId === sceneId) commit("RECEIVE_FOG_STATE", fog);
      return fog;
    } catch (error) {
      commit("FOG_FAILED", normalizedError(error));
      throw error;
    }
  },
  async patchFog({ state, commit }, changes) {
    const sceneId = Number(changes?.sceneId) || state.selectedSceneId;
    if (sceneId === null || typeof fogApi?.patch !== "function") return null;
    const patch = { ...changes };
    delete patch.sceneId;
    commit("SET_FOG_PHASE", "saving");
    try {
      const fog = await fogApi.patch(state.campaignId, sceneId, patch);
      if (Number(state.selectedSceneId) === Number(sceneId))
        commit("RECEIVE_FOG_STATE", fog);
      return fog;
    } catch (error) {
      commit("FOG_FAILED", normalizedError(error));
      throw error;
    }
  },
  async resetFog({ state, commit }, sceneId = null) {
    const targetSceneId = Number(sceneId) || state.selectedSceneId;
    if (targetSceneId === null || typeof fogApi?.reset !== "function")
      return null;
    assertCanManage(state);
    commit("SET_FOG_PHASE", "saving");
    try {
      const fog = await fogApi.reset(state.campaignId, targetSceneId);
      if (Number(state.selectedSceneId) === Number(targetSceneId))
        commit("RECEIVE_FOG_STATE", fog);
      return fog;
    } catch (error) {
      commit("FOG_FAILED", normalizedError(error));
      throw error;
    }
  },
  async createToken({ state, commit }, draft) {
    const sceneId = state.selectedSceneId;
    if (sceneId === null || typeof tokenApi?.create !== "function") return null;
    commit("SET_TOKEN_PHASE", "saving");
    try {
      const token = await tokenApi.create(state.campaignId, sceneId, draft);
      if (state.selectedSceneId === sceneId) {
        commit("UPSERT_TOKEN", token);
        commit("SELECT_TOKEN", token.id);
      }
      commit("SET_TOKEN_PHASE", "ready");
      return token;
    } catch (error) {
      commit("TOKEN_FAILED", normalizedError(error));
      throw error;
    }
  },
  async updateToken({ state, commit }, { token, changes }) {
    if (!token || typeof tokenApi?.update !== "function") return null;
    commit("SET_TOKEN_PHASE", "saving");
    try {
      const updated = await tokenApi.update(
        state.campaignId,
        token.sceneId,
        token.id,
        { ...changes, revision: token.revision },
      );
      commit("UPSERT_TOKEN", updated);
      commit("SET_TOKEN_PHASE", "ready");
      return updated;
    } catch (error) {
      commit("TOKEN_FAILED", normalizedError(error));
      throw error;
    }
  },
  async deleteToken({ state, commit }, token) {
    if (!token || typeof tokenApi?.remove !== "function") return;
    commit("SET_TOKEN_PHASE", "saving");
    try {
      await tokenApi.remove(
        state.campaignId,
        token.sceneId,
        token.id,
        token.revision,
      );
      commit("REMOVE_TOKEN", { sceneId: token.sceneId, tokenId: token.id });
      commit("SET_TOKEN_PHASE", "ready");
    } catch (error) {
      commit("TOKEN_FAILED", normalizedError(error));
      throw error;
    }
  },
});

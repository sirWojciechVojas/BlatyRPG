const hasScene = (state, sceneId) =>
  state.scenes.some((scene) => scene.id === sceneId);

export const vttMutations = {
  SET_CAMPAIGN(state, campaignId) {
    if (state.campaignId === campaignId) return;
    state.campaignId = campaignId;
    state.scenes = [];
    state.activeSceneId = null;
    state.selectedSceneId = null;
    state.tokensByScene = {};
    state.tokenCapabilitiesByScene = {};
    state.selectedTokenId = null;
    state.selectedTokenIds = [];
    state.targetedTokenIds = [];
    state.tokenPhase = "idle";
    state.wallsByScene = {};
    state.wallCapabilitiesByScene = {};
    state.selectedWallId = null;
    state.wallPhase = "idle";
    state.lightsByScene = {};
    state.lightCapabilitiesByScene = {};
    state.selectedLightId = null;
    state.lightPhase = "idle";
    state.tilesByScene = {};
    state.tileCapabilitiesByScene = {};
    state.selectedTileId = null;
    state.tilePhase = "idle";
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
    state.selectedTokenId = null;
    state.selectedTokenIds = [];
    state.targetedTokenIds = [];
    state.selectedWallId = null;
    state.selectedLightId = null;
    state.selectedTileId = null;
  },
  RECEIVE_TOKENS(state, { sceneId, items, capabilities }) {
    state.tokensByScene = { ...state.tokensByScene, [String(sceneId)]: items };
    state.tokenCapabilitiesByScene = {
      ...state.tokenCapabilitiesByScene,
      [String(sceneId)]: capabilities,
    };
    state.tokenPhase = "ready";
    const availableIds = new Set(items.map((token) => token.id));
    state.selectedTokenIds = state.selectedTokenIds.filter((id) =>
      availableIds.has(id),
    );
    state.targetedTokenIds = state.targetedTokenIds.filter((id) =>
      availableIds.has(id),
    );
    if (!availableIds.has(state.selectedTokenId)) {
      state.selectedTokenId = state.selectedTokenIds.at(-1) ?? null;
    }
  },
  UPSERT_TOKEN(state, token) {
    const key = String(token.sceneId);
    const items = [...(state.tokensByScene[key] || [])];
    const index = items.findIndex((item) => item.id === token.id);
    if (index < 0) items.push(token);
    else items.splice(index, 1, token);
    state.tokensByScene = { ...state.tokensByScene, [key]: items };
  },
  REMOVE_TOKEN(state, { sceneId, tokenId }) {
    const key = String(sceneId);
    state.tokensByScene = {
      ...state.tokensByScene,
      [key]: (state.tokensByScene[key] || []).filter(
        (token) => token.id !== tokenId,
      ),
    };
    state.selectedTokenIds = state.selectedTokenIds.filter(
      (id) => id !== tokenId,
    );
    state.targetedTokenIds = state.targetedTokenIds.filter(
      (id) => id !== tokenId,
    );
    if (state.selectedTokenId === tokenId) {
      state.selectedTokenId = state.selectedTokenIds.at(-1) ?? null;
    }
  },
  SELECT_TOKEN(state, selection) {
    const value =
      selection && typeof selection === "object"
        ? selection.tokenId
        : selection;
    if (value === null || value === undefined || value === "") {
      state.selectedTokenId = null;
      state.selectedTokenIds = [];
      return;
    }
    const tokenId = Number(value);
    if (!Number.isFinite(tokenId)) return;
    if (selection?.additive === true) {
      const selected = state.selectedTokenIds.includes(tokenId);
      state.selectedTokenIds = selected
        ? state.selectedTokenIds.filter((id) => id !== tokenId)
        : [...state.selectedTokenIds, tokenId];
      state.selectedTokenId = selected
        ? (state.selectedTokenIds.at(-1) ?? null)
        : tokenId;
      return;
    }
    state.selectedTokenId = tokenId;
    state.selectedTokenIds = [tokenId];
  },
  TOGGLE_TOKEN_TARGET(state, tokenId) {
    const id = Number(tokenId);
    if (!Number.isFinite(id)) return;
    state.targetedTokenIds = state.targetedTokenIds.includes(id)
      ? state.targetedTokenIds.filter((targetId) => targetId !== id)
      : [...state.targetedTokenIds, id];
  },
  SET_TOKEN_PHASE(state, phase) {
    state.tokenPhase = phase;
  },
  TOKEN_FAILED(state, error) {
    state.tokenPhase = "error";
    state.error = error;
    state.unauthorized = error.status === 401 || error.status === 403;
  },
  RECEIVE_WALLS(state, { sceneId, items, capabilities }) {
    const key = String(sceneId);
    state.wallsByScene = { ...state.wallsByScene, [key]: items };
    state.wallCapabilitiesByScene = {
      ...state.wallCapabilitiesByScene,
      [key]: capabilities,
    };
    state.wallPhase = "ready";
    if (!items.some((wall) => wall.id === state.selectedWallId)) {
      state.selectedWallId = null;
    }
  },
  UPSERT_WALL(state, wall) {
    const key = String(wall.sceneId);
    const items = [...(state.wallsByScene[key] || [])];
    const index = items.findIndex((item) => item.id === wall.id);
    if (index < 0) items.push(wall);
    else items.splice(index, 1, wall);
    state.wallsByScene = { ...state.wallsByScene, [key]: items };
  },
  REMOVE_WALL(state, { sceneId, wallId }) {
    const key = String(sceneId);
    state.wallsByScene = {
      ...state.wallsByScene,
      [key]: (state.wallsByScene[key] || []).filter(
        (wall) => wall.id !== wallId,
      ),
    };
    if (state.selectedWallId === wallId) state.selectedWallId = null;
  },
  SELECT_WALL(state, wallId) {
    state.selectedWallId = wallId;
  },
  SET_WALL_PHASE(state, phase) {
    state.wallPhase = phase;
  },
  WALL_FAILED(state, error) {
    state.wallPhase = "error";
    state.error = error;
  },
  RECEIVE_LIGHTS(state, { sceneId, items, capabilities }) {
    const key = String(sceneId);
    state.lightsByScene = { ...state.lightsByScene, [key]: items };
    state.lightCapabilitiesByScene = {
      ...state.lightCapabilitiesByScene,
      [key]: capabilities,
    };
    state.lightPhase = "ready";
    if (!items.some((light) => light.id === state.selectedLightId)) {
      state.selectedLightId = null;
    }
  },
  UPSERT_LIGHT(state, light) {
    const key = String(light.sceneId);
    const items = [...(state.lightsByScene[key] || [])];
    const index = items.findIndex((item) => item.id === light.id);
    if (index < 0) items.push(light);
    else items.splice(index, 1, light);
    state.lightsByScene = { ...state.lightsByScene, [key]: items };
  },
  REMOVE_LIGHT(state, { sceneId, lightId }) {
    const key = String(sceneId);
    state.lightsByScene = {
      ...state.lightsByScene,
      [key]: (state.lightsByScene[key] || []).filter(
        (light) => light.id !== lightId,
      ),
    };
    if (state.selectedLightId === lightId) state.selectedLightId = null;
  },
  SELECT_LIGHT(state, lightId) {
    state.selectedLightId = lightId;
  },
  SET_LIGHT_PHASE(state, phase) {
    state.lightPhase = phase;
  },
  LIGHT_FAILED(state, error) {
    state.lightPhase = "error";
    state.error = error;
  },
  RECEIVE_TILES(state, { sceneId, items, capabilities }) {
    const key = String(sceneId);
    state.tilesByScene = { ...state.tilesByScene, [key]: items };
    state.tileCapabilitiesByScene = {
      ...state.tileCapabilitiesByScene,
      [key]: capabilities,
    };
    state.tilePhase = "ready";
    if (!items.some((tile) => tile.id === state.selectedTileId)) {
      state.selectedTileId = null;
    }
  },
  UPSERT_TILE(state, tile) {
    const key = String(tile.sceneId);
    const items = [...(state.tilesByScene[key] || [])];
    const index = items.findIndex((item) => item.id === tile.id);
    if (index < 0) items.push(tile);
    else items.splice(index, 1, tile);
    state.tilesByScene = { ...state.tilesByScene, [key]: items };
  },
  REMOVE_TILE(state, { sceneId, tileId }) {
    const key = String(sceneId);
    state.tilesByScene = {
      ...state.tilesByScene,
      [key]: (state.tilesByScene[key] || []).filter(
        (tile) => tile.id !== tileId,
      ),
    };
    if (state.selectedTileId === tileId) state.selectedTileId = null;
  },
  SELECT_TILE(state, tileId) {
    state.selectedTileId = tileId;
  },
  SET_TILE_PHASE(state, phase) {
    state.tilePhase = phase;
  },
  TILE_FAILED(state, error) {
    state.tilePhase = "error";
    state.error = error;
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

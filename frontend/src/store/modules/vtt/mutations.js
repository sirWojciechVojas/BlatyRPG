import { movementRequestMutations } from "./movementRequestMutations";
import { campaignMutations } from "./campaignMutations";
import { combatMutations } from "./combatMutations";
import { tokenSelectionMutations } from "./tokenSelectionMutations";

const hasScene = (state, sceneId) =>
  state.scenes.some((scene) => scene.id === sceneId);

export const vttMutations = {
  ...campaignMutations,
  ...movementRequestMutations,
  ...combatMutations,
  ...tokenSelectionMutations,
  BEGIN_REQUEST(state, { phase, requestId }) {
    state.phase = phase;
    state.requestId = requestId;
    state.error = null;
    state.unauthorized = false;
  },
  CLEAR_ERROR(state) {
    state.error = null;
  },
  SHOW_NOTICE(state, notice) {
    state.error = notice;
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
  RECEIVE_TOKENS(state, { sceneId, items, capabilities, silent = false }) {
    state.tokensByScene = { ...state.tokensByScene, [String(sceneId)]: items };
    state.tokenCapabilitiesByScene = {
      ...state.tokenCapabilitiesByScene,
      [String(sceneId)]: capabilities,
    };
    if (!silent) state.tokenPhase = "ready";
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
  RECEIVE_REALTIME_SCENE_SNAPSHOT(state, snapshot) {
    const sceneId = Number(snapshot?.scene?.id);
    if (!sceneId || sceneId !== Number(state.selectedSceneId)) return;
    const key = String(sceneId);
    const scenes = [...state.scenes];
    const sceneIndex = scenes.findIndex(
      (scene) => Number(scene.id) === sceneId,
    );
    if (sceneIndex < 0) scenes.push(snapshot.scene);
    else scenes.splice(sceneIndex, 1, snapshot.scene);
    state.scenes = scenes;
    state.activeSceneId = snapshot.scenes.activeSceneId;
    state.capabilities = snapshot.scenes.capabilities;
    state.tokensByScene = {
      ...state.tokensByScene,
      [key]: snapshot.tokens.items,
    };
    state.tokenCapabilitiesByScene = {
      ...state.tokenCapabilitiesByScene,
      [key]: snapshot.tokens.capabilities,
    };
    state.wallsByScene = { ...state.wallsByScene, [key]: snapshot.walls.items };
    state.wallCapabilitiesByScene = {
      ...state.wallCapabilitiesByScene,
      [key]: snapshot.walls.capabilities,
    };
    state.lightsByScene = {
      ...state.lightsByScene,
      [key]: snapshot.lights.items,
    };
    state.lightCapabilitiesByScene = {
      ...state.lightCapabilitiesByScene,
      [key]: snapshot.lights.capabilities,
    };
    state.tilesByScene = { ...state.tilesByScene, [key]: snapshot.tiles.items };
    state.tileCapabilitiesByScene = {
      ...state.tileCapabilitiesByScene,
      [key]: snapshot.tiles.capabilities,
    };
    state.combatByScene = {
      ...state.combatByScene,
      [key]: snapshot.combat.combat,
    };
    state.combatCapabilitiesByScene = {
      ...state.combatCapabilitiesByScene,
      [key]: snapshot.combat.capabilities,
    };
    state.fogBySceneUser = {
      ...state.fogBySceneUser,
      [`${sceneId}:${snapshot.fog.userId}`]: snapshot.fog,
    };
    state.selectedFogUserId = snapshot.fog.userId;
    state.movementRequests = snapshot.movementRequests.items;
    state.movementRequestCapabilities = snapshot.movementRequests.capabilities;
    const tokenIds = new Set(snapshot.tokens.items.map((token) => token.id));
    state.selectedTokenIds = state.selectedTokenIds.filter((id) =>
      tokenIds.has(id),
    );
    state.targetedTokenIds = state.targetedTokenIds.filter((id) =>
      tokenIds.has(id),
    );
    if (!tokenIds.has(state.selectedTokenId)) {
      state.selectedTokenId = state.selectedTokenIds.at(-1) ?? null;
    }
    if (
      !snapshot.walls.items.some((wall) => wall.id === state.selectedWallId)
    ) {
      state.selectedWallId = null;
    }
    if (
      !snapshot.lights.items.some((light) => light.id === state.selectedLightId)
    ) {
      state.selectedLightId = null;
    }
    if (
      !snapshot.tiles.items.some((tile) => tile.id === state.selectedTileId)
    ) {
      state.selectedTileId = null;
    }
    if (state.tokenPhase !== "saving") state.tokenPhase = "ready";
    if (state.wallPhase !== "saving") state.wallPhase = "ready";
    if (state.lightPhase !== "saving") state.lightPhase = "ready";
    if (state.tilePhase !== "saving") state.tilePhase = "ready";
    if (state.combatPhase !== "saving") state.combatPhase = "ready";
    if (state.fogPhase !== "saving") state.fogPhase = "ready";
    if (state.movementRequestPhase !== "saving") {
      state.movementRequestPhase = "ready";
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
  RECEIVE_FOG_STATE(state, fog) {
    if (!fog?.sceneId || !fog?.userId) return;
    const key = `${fog.sceneId}:${fog.userId}`;
    state.fogBySceneUser = { ...state.fogBySceneUser, [key]: fog };
    state.selectedFogUserId = fog.userId;
    state.fogPhase = "ready";
  },
  SELECT_FOG_USER(state, userId) {
    state.selectedFogUserId = Number(userId) || null;
  },
  SET_FOG_PHASE(state, phase) {
    state.fogPhase = phase;
  },
  FOG_FAILED(state, error) {
    state.fogPhase = "error";
    state.error = error;
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

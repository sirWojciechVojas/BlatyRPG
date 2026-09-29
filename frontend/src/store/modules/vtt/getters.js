export const vttGetters = {
  sortedScenes: (state) =>
    [...state.scenes].sort(
      (left, right) =>
        left.sortOrder - right.sortOrder || left.name.localeCompare(right.name),
    ),
  selectedScene: (state) =>
    state.scenes.find((scene) => scene.id === state.selectedSceneId) || null,
  selectedSceneTokens: (state) =>
    state.tokensByScene[String(state.selectedSceneId)] || [],
  selectedToken: (state, getters) =>
    getters.selectedSceneTokens.find(
      (token) => token.id === state.selectedTokenId,
    ) || null,
  selectedTokens: (state, getters) =>
    getters.selectedSceneTokens.filter((token) =>
      state.selectedTokenIds.includes(token.id),
    ),
  targetedTokens: (state, getters) =>
    getters.selectedSceneTokens.filter((token) =>
      state.targetedTokenIds.includes(token.id),
    ),
  canCreateToken: (state) =>
    state.tokenCapabilitiesByScene[String(state.selectedSceneId)]?.canCreate ===
    true,
  pendingMovementRequests: (state) =>
    state.movementRequests.filter((request) => request.status === "pending"),
  canResolveMovementRequests: (state) =>
    state.movementRequestCapabilities.canResolve === true,
  selectedSceneCombat: (state) =>
    state.combatByScene[String(state.selectedSceneId)] || null,
  canManageCombat: (state) =>
    state.combatCapabilitiesByScene[String(state.selectedSceneId)]
      ?.canManage === true,
  selectedSceneWalls: (state) =>
    state.wallsByScene[String(state.selectedSceneId)] || [],
  selectedWall: (state, getters) =>
    getters.selectedSceneWalls.find(
      (wall) => wall.id === state.selectedWallId,
    ) || null,
  canManageWalls: (state) =>
    state.wallCapabilitiesByScene[String(state.selectedSceneId)]?.canManage ===
    true,
  selectedSceneLights: (state) =>
    state.lightsByScene[String(state.selectedSceneId)] || [],
  selectedLight: (state, getters) =>
    getters.selectedSceneLights.find(
      (light) => light.id === state.selectedLightId,
    ) || null,
  canManageLights: (state) =>
    state.lightCapabilitiesByScene[String(state.selectedSceneId)]?.canManage ===
    true,
  selectedSceneRegions: (state) =>
    state.regionsByScene[String(state.selectedSceneId)] || [],
  selectedRegion: (state, getters) =>
    getters.selectedSceneRegions.find(
      (region) => region.id === state.selectedRegionId,
    ) || null,
  canManageRegions: (state) =>
    state.regionCapabilitiesByScene[String(state.selectedSceneId)]
      ?.canManage === true,
  selectedSceneTiles: (state) =>
    state.tilesByScene[String(state.selectedSceneId)] || [],
  selectedTile: (state, getters) =>
    getters.selectedSceneTiles.find(
      (tile) => tile.id === state.selectedTileId,
    ) || null,
  canManageTiles: (state) =>
    state.tileCapabilitiesByScene[String(state.selectedSceneId)]?.canManage ===
    true,
  selectedFogState: (state) => {
    const prefix = `${state.selectedSceneId}:`;
    if (state.selectedFogUserId) {
      return (
        state.fogBySceneUser[`${prefix}${state.selectedFogUserId}`] || null
      );
    }
    return (
      Object.entries(state.fogBySceneUser).find(([key]) =>
        key.startsWith(prefix),
      )?.[1] || null
    );
  },
  canManage: (state) => state.capabilities.canManage === true,
  isLoading: (state) => state.phase === "loading",
  isSaving: (state) => state.phase === "saving",
};

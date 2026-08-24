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
  canCreateToken: (state) =>
    state.tokenCapabilitiesByScene[String(state.selectedSceneId)]?.canCreate ===
    true,
  canManage: (state) => state.capabilities.canManage === true,
  isLoading: (state) => state.phase === "loading",
  isSaving: (state) => state.phase === "saving",
};

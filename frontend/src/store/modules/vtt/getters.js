export const vttGetters = {
  sortedScenes: (state) =>
    [...state.scenes].sort(
      (left, right) =>
        left.sortOrder - right.sortOrder || left.name.localeCompare(right.name),
    ),
  selectedScene: (state) =>
    state.scenes.find((scene) => scene.id === state.selectedSceneId) || null,
  canManage: (state) => state.capabilities.canManage === true,
  isLoading: (state) => state.phase === "loading",
  isSaving: (state) => state.phase === "saving",
};

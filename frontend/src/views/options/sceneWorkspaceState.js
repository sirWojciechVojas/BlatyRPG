export const emptySceneWorkspaceState = () => ({
  scenes: [],
  selectedSceneId: null,
  activeSceneId: null,
  capabilities: { canManage: false, canViewHidden: false },
  phase: "idle",
  error: null,
  unauthorized: false,
});

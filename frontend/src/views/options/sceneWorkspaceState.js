export const emptySceneWorkspaceState = () => ({
  scenes: [],
  selectedSceneId: null,
  selectedTokenId: null,
  selectedTokenIds: [],
  targetedTokenIds: [],
  activeSceneId: null,
  capabilities: { canManage: false, canViewHidden: false },
  phase: "idle",
  error: null,
  unauthorized: false,
});

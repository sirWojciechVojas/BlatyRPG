export const createVttState = () => ({
  campaignId: null,
  scenes: [],
  activeSceneId: null,
  selectedSceneId: null,
  capabilities: {
    canManage: false,
    canViewHidden: false,
  },
  phase: "idle",
  error: null,
  unauthorized: false,
  requestId: 0,
});

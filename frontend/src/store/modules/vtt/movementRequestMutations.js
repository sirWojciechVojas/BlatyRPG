export const movementRequestMutations = {
  RECEIVE_MOVEMENT_REQUESTS(state, { items, capabilities }) {
    state.movementRequests = items;
    state.movementRequestCapabilities = capabilities;
    state.movementRequestPhase = "ready";
  },
  UPSERT_MOVEMENT_REQUEST(state, request) {
    const items = [...state.movementRequests];
    const index = items.findIndex((item) => item.id === request.id);
    if (index < 0) items.unshift(request);
    else items.splice(index, 1, request);
    state.movementRequests = items.slice(0, 100);
  },
  SET_MOVEMENT_REQUEST_PHASE(state, phase) {
    state.movementRequestPhase = phase;
  },
  MOVEMENT_REQUEST_FAILED(state, error) {
    state.movementRequestPhase = "error";
    state.error = error;
  },
  PATCH_TOKEN(state, patch) {
    const key = String(patch.sceneId);
    const items = [...(state.tokensByScene[key] || [])];
    const index = items.findIndex((item) => item.id === patch.id);
    if (index < 0) return;
    items.splice(index, 1, { ...items[index], ...patch });
    state.tokensByScene = { ...state.tokensByScene, [key]: items };
  },
};

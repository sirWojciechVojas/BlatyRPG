export const createContainerMutations = () => ({
  setContainerState(state, payload = {}) {
    state.containerState = {
      containers: Array.isArray(payload.containers) ? payload.containers : [],
      templateRows: Array.isArray(payload.templateRows)
        ? payload.templateRows
        : [],
      instanceRows: Array.isArray(payload.instanceRows)
        ? payload.instanceRows
        : [],
      itemInstances: Array.isArray(payload.itemInstances)
        ? payload.itemInstances
        : [],
    };
  },
});

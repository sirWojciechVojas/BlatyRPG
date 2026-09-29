export const combatMutations = {
  RECEIVE_COMBAT(state, { sceneId, combat, capabilities }) {
    const key = String(sceneId);
    state.combatByScene = { ...state.combatByScene, [key]: combat };
    state.combatCapabilitiesByScene = {
      ...state.combatCapabilitiesByScene,
      [key]: capabilities,
    };
    state.combatPhase = "ready";
    state.combatError = null;
  },
  SET_COMBAT_PHASE(state, phase) {
    state.combatPhase = phase;
  },
  COMBAT_FAILED(state, error) {
    state.combatPhase = "error";
    state.combatError = error;
  },
};

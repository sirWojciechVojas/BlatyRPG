let requestSerial = 0;

export const routeRealtimeCombatEvent = (context, event) => {
  if (!context.rootState.vtt) return;
  if (event.type === "combat.updated") {
    const sceneId = Number(event.payload.sceneId);
    context.dispatch("vtt/loadCombat", sceneId, { root: true }).catch(() => {});
    if (event.payload.movementChanged === true) {
      context.dispatch("vtt/loadTokens", null, { root: true }).catch(() => {});
    }
    return;
  }
  if (event.type === "combat.error") {
    context.commit(
      "vtt/COMBAT_FAILED",
      {
        code: String(event.payload.code || "combat_unavailable"),
        status: Number(event.payload.status || 0),
        details: event.payload.errors || null,
      },
      { root: true },
    );
    context.dispatch("vtt/loadCombat", null, { root: true }).catch(() => {});
  }
};

export const createRealtimeCombatActions = (ensureSession) => ({
  commandCombat(context, { sceneId, command }) {
    if (!Number(sceneId) || !command?.action) return false;
    context.commit("vtt/SET_COMBAT_PHASE", "saving", { root: true });
    const sent = ensureSession(context).commandCombat({
      requestId: `combat-${++requestSerial}`,
      sceneId: Number(sceneId),
      command,
    });
    if (!sent) {
      context.commit("vtt/SET_COMBAT_PHASE", "error", { root: true });
    }
    return sent;
  },
});

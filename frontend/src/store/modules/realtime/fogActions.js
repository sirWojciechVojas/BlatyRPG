let requestSerial = 0;

export const routeRealtimeFogEvent = (context, event) => {
  if (!context.rootState.vtt || event.type !== "fog.updated") return;
  const sceneId = Number(event.payload.sceneId);
  const userId = Number(event.payload.userId);
  if (sceneId !== Number(context.rootState.vtt.selectedSceneId)) return;
  const selected = Number(context.rootState.vtt.selectedFogUserId);
  const canManage = context.rootGetters?.["vtt/canManage"] === true;
  if (canManage && selected !== userId) return;
  context
    .dispatch("vtt/loadFog", canManage ? userId : null, { root: true })
    .catch(() => {});
};

export const createRealtimeFogActions = (ensureSession) => ({
  syncFog(context, fog) {
    if (!fog?.sceneId || !fog?.userId || !fog?.revision) return false;
    return ensureSession(context).syncFog({
      requestId: `fog-sync-${++requestSerial}`,
      sceneId: fog.sceneId,
      userId: fog.userId,
      revision: fog.revision,
    });
  },
});

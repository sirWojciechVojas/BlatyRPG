import { normalizeToken } from "@/lib/vtt/tokenNormalizer";

let requestSerial = 0;

export const routeRealtimeTokenEvent = (context, event) => {
  if (!context.rootState.vtt) return;
  if (event.type === "token.updated") {
    const token = normalizeToken(event.payload.token);
    if (token.id > 0 && token.sceneId > 0) {
      context.commit("vtt/UPSERT_TOKEN", token, { root: true });
    }
    return;
  }
  if (event.type === "token.error") {
    context.dispatch("vtt/loadTokens", null, { root: true }).catch(() => {});
  }
};

export const createRealtimeTokenActions = (ensureSession) => ({
  moveToken(context, { token, x, y, waypoints = [] }) {
    if (!token?.id || !token?.sceneId || !token?.revision) return false;
    return ensureSession(context).moveToken({
      requestId: `token-move-${++requestSerial}`,
      sceneId: token.sceneId,
      tokenId: token.id,
      revision: token.revision,
      x: Number(x),
      y: Number(y),
      waypoints,
    });
  },
});

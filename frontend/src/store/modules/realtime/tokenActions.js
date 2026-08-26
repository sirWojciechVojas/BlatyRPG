import { normalizeToken } from "@/lib/vtt/tokenNormalizer";
import { normalizeMovementRequest } from "@/lib/vtt/tokenMovementRequest";

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
    return;
  }
  if (event.type === "token.movement.requested") {
    context.commit(
      "vtt/UPSERT_MOVEMENT_REQUEST",
      normalizeMovementRequest(event.payload.request),
      { root: true },
    );
    context.commit("vtt/SET_MOVEMENT_REQUEST_PHASE", "ready", { root: true });
    return;
  }
  if (event.type === "token.movement.resolved") {
    context.commit(
      "vtt/UPSERT_MOVEMENT_REQUEST",
      normalizeMovementRequest(event.payload.request),
      { root: true },
    );
    if (event.payload.tokenPatch) {
      context.commit("vtt/PATCH_TOKEN", event.payload.tokenPatch, {
        root: true,
      });
    }
    context.commit("vtt/SET_MOVEMENT_REQUEST_PHASE", "ready", { root: true });
    return;
  }
  if (event.type === "token.movement.error") {
    context
      .dispatch("vtt/loadMovementRequests", null, { root: true })
      .catch(() => {});
  }
};

export const createRealtimeTokenActions = (ensureSession) => ({
  changeToken(context, { token, changes }) {
    if (!token?.id || !token?.sceneId || !token?.revision) return false;
    return ensureSession(context).changeToken({
      requestId: `token-change-${++requestSerial}`,
      sceneId: token.sceneId,
      tokenId: token.id,
      revision: token.revision,
      changes,
    });
  },
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
  requestTokenMovement(context, { token, x, y, waypoints = [] }) {
    if (!token?.id || !token?.sceneId || !token?.revision) return false;
    context.commit("vtt/SET_MOVEMENT_REQUEST_PHASE", "saving", { root: true });
    const sent = ensureSession(context).requestTokenMovement({
      requestId: `token-movement-request-${++requestSerial}`,
      sceneId: token.sceneId,
      tokenId: token.id,
      revision: token.revision,
      x: Number(x),
      y: Number(y),
      waypoints,
    });
    if (!sent) {
      context.commit("vtt/SET_MOVEMENT_REQUEST_PHASE", "error", { root: true });
    }
    return sent;
  },
  resolveTokenMovement(context, { movementRequestId, decision }) {
    if (!Number(movementRequestId)) return false;
    context.commit("vtt/SET_MOVEMENT_REQUEST_PHASE", "saving", { root: true });
    const sent = ensureSession(context).resolveTokenMovement({
      requestId: `token-movement-resolve-${++requestSerial}`,
      movementRequestId: Number(movementRequestId),
      decision,
    });
    if (!sent) {
      context.commit("vtt/SET_MOVEMENT_REQUEST_PHASE", "error", { root: true });
    }
    return sent;
  },
});

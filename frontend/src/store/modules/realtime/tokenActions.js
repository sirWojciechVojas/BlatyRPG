import { normalizeToken } from "@/lib/vtt/tokenNormalizer";
import { normalizeMovementRequest } from "@/lib/vtt/tokenMovementRequest";
import { scheduleVisibilityRefresh } from "./visibilityRefresh";

let requestSerial = 0;
let visibilityReloadTimer = null;

export const routeRealtimeTokenEvent = (context, event) => {
  if (!context.rootState.vtt) return;
  if (event.type === "scene.visibility.changed") {
    if (
      Number(event.payload.sceneId) !==
      Number(context.rootState.vtt.selectedSceneId)
    )
      return;
    window.clearTimeout(visibilityReloadTimer);
    visibilityReloadTimer = window.setTimeout(
      () =>
        context
          .dispatch("vtt/loadTokens", { silent: true }, { root: true })
          .catch(() => {}),
      80,
    );
    return;
  }
  if (event.type === "token.updated") {
    const token = normalizeToken(event.payload.token);
    if (token.id > 0 && token.sceneId > 0) {
      context.commit("vtt/UPSERT_TOKEN", token, { root: true });
      if (event.payload.visibilityChanged !== false) {
        scheduleVisibilityRefresh(context, token.sceneId);
      }
    }
    return;
  }
  if (event.type === "token.group.updated") {
    (event.payload.tokens || []).forEach((item) => {
      const token = normalizeToken(item);
      if (token.id > 0 && token.sceneId > 0) {
        context.commit("vtt/UPSERT_TOKEN", token, { root: true });
      }
    });
    const sceneId = Number(event.payload.tokens?.[0]?.sceneId);
    if (sceneId) scheduleVisibilityRefresh(context, sceneId);
    return;
  }
  if (event.type === "token.error") {
    context
      .dispatch("vtt/loadTokens", { silent: true }, { root: true })
      .catch(() => {});
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
      scheduleVisibilityRefresh(context, event.payload.tokenPatch.sceneId);
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
  moveTokenGroup(context, { moves = [] }) {
    if (moves.length < 2 || moves.some(({ token }) => !token?.revision)) {
      return false;
    }
    const sceneId = Number(moves[0].token.sceneId);
    if (
      !sceneId ||
      moves.some(({ token }) => Number(token.sceneId) !== sceneId)
    ) {
      return false;
    }
    return ensureSession(context).moveTokenGroup({
      requestId: `token-group-move-${++requestSerial}`,
      sceneId,
      moves: moves.map(({ token, x, y, waypoints }) => ({
        tokenId: token.id,
        revision: token.revision,
        x: Number(x),
        y: Number(y),
        waypoints,
      })),
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

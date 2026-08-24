import { normalizeWall } from "@/lib/vtt/wallNormalizer";

let requestSerial = 0;

export const routeRealtimeWallEvent = (context, event) => {
  if (!context.rootState.vtt) return;
  if (event.type === "wall.updated") {
    const wall = normalizeWall(event.payload.wall);
    if (wall.id > 0 && wall.sceneId > 0) {
      context.commit("vtt/UPSERT_WALL", wall, { root: true });
    }
    return;
  }
  if (event.type === "wall.deleted") {
    const sceneId = Number(event.payload.sceneId);
    const wallId = Number(event.payload.wallId);
    if (sceneId > 0 && wallId > 0) {
      context.commit("vtt/REMOVE_WALL", { sceneId, wallId }, { root: true });
    }
    return;
  }
  if (event.type === "wall.error") {
    context.dispatch("vtt/loadWalls", null, { root: true }).catch(() => {});
  }
};

export const createRealtimeWallActions = (ensureSession) => ({
  changeWall(context, { operation, wall, changes }) {
    const sceneId = Number(
      wall?.sceneId ?? context.rootState.vtt?.selectedSceneId,
    );
    if (!sceneId || !["create", "update", "delete"].includes(operation)) {
      return false;
    }
    if (operation !== "create" && (!wall?.id || !wall?.revision)) return false;
    return ensureSession(context).changeWall({
      requestId: `wall-${operation}-${++requestSerial}`,
      operation,
      sceneId,
      ...(wall?.id ? { wallId: wall.id, revision: wall.revision } : {}),
      ...(changes ? { changes } : {}),
    });
  },
});

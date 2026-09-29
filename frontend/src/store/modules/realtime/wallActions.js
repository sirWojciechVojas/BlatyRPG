import { normalizeWall } from "@/lib/vtt/wallNormalizer";
import { scheduleVisibilityRefresh } from "./visibilityRefresh";
import { playDoorSound } from "@/lib/vtt/doorAudio";
import { wallAudioRuntime } from "@/services/wallAudioRuntime";
import { wallRequestTracker } from "./sceneElementRequestTracker";

let requestSerial = 0;

export const routeRealtimeWallEvent = (context, event) => {
  if (["wall.ack", "wall.error"].includes(event.type)) {
    wallRequestTracker.settle(event);
  }
  if (!context.rootState.vtt) return;
  if (event.type === "wall.audio.state") {
    wallAudioRuntime.applyProjectedState(event.payload || {});
    return;
  }
  if (event.type === "wall.audio.cue") {
    wallAudioRuntime.playProjectedCue(event.payload || {});
    return;
  }
  if (event.type === "wall.updated") {
    const wall = normalizeWall(event.payload.wall);
    if (wall.id > 0 && wall.sceneId > 0) {
      context.commit("vtt/UPSERT_WALL", wall, { root: true });
      scheduleVisibilityRefresh(context, wall.sceneId);
      if (event.payload.sound) playDoorSound(wall, event.payload.sound);
    }
    return;
  }
  if (event.type === "wall.deleted") {
    const sceneId = Number(event.payload.sceneId);
    const wallId = Number(event.payload.wallId);
    if (sceneId > 0 && wallId > 0) {
      context.commit("vtt/REMOVE_WALL", { sceneId, wallId }, { root: true });
      scheduleVisibilityRefresh(context, sceneId);
    }
    return;
  }
  if (event.type === "wall.error") {
    const wallId = Number(event.payload?.errors?.wallId);
    const cue = String(event.payload?.errors?.sound || "");
    if (wallId > 0 && cue) {
      const walls = Object.values(context.rootState.vtt.wallsByScene || {})
        .flat()
        .filter(Boolean);
      const wall = walls.find((item) => Number(item.id) === wallId);
      if (wall) playDoorSound(wall, cue);
    }
    context.dispatch("vtt/loadWalls", null, { root: true }).catch(() => {});
  }
};

export const createRealtimeWallActions = (ensureSession) => ({
  syncWallAudio(context, { sceneId, selectedTokenId = null } = {}) {
    const id = Number(sceneId || context.rootState.vtt?.selectedSceneId);
    if (!id) return false;
    return ensureSession(context).syncWallAudio({
      requestId: `wall-audio-sync-${++requestSerial}`,
      sceneId: id,
      selectedTokenId: Number(selectedTokenId) || null,
    });
  },
  changeWall(context, { operation, wall, changes }) {
    const sceneId = Number(
      wall?.sceneId ?? context.rootState.vtt?.selectedSceneId,
    );
    if (
      !sceneId ||
      !["create", "update", "delete", "interact"].includes(operation)
    ) {
      return false;
    }
    if (operation !== "create" && (!wall?.id || !wall?.revision)) return false;
    const requestId = `wall-${operation}-${++requestSerial}`;
    return wallRequestTracker.send(requestId, () =>
      ensureSession(context).changeWall({
        requestId,
        operation,
        sceneId,
        ...(wall?.id ? { wallId: wall.id, revision: wall.revision } : {}),
        ...(changes ? { changes } : {}),
      }),
    );
  },
});

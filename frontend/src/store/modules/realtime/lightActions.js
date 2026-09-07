import { normalizeLight } from "@/lib/vtt/lightNormalizer";
import { normalizeScene } from "@/lib/vtt/sceneNormalizer";
import { lightRequestTracker } from "./lightRequestTracker";
import { scheduleVisibilityRefresh } from "./visibilityRefresh";

let requestSerial = 0;

export const routeRealtimeLightEvent = (context, event) => {
  if (["light.ack", "light.error"].includes(event.type)) {
    lightRequestTracker.settle(event);
  }
  if (!context.rootState.vtt) return;
  if (event.type === "scene.updated") {
    const scene = normalizeScene(event.payload.scene);
    if (scene?.id) context.commit("vtt/UPSERT_SCENE", scene, { root: true });
    if (scene?.id) scheduleVisibilityRefresh(context, scene.id);
    return;
  }
  if (event.type === "light.updated") {
    const light = normalizeLight(event.payload.light);
    if (light.id > 0 && light.sceneId > 0) {
      context.commit("vtt/UPSERT_LIGHT", light, { root: true });
      scheduleVisibilityRefresh(context, light.sceneId);
    }
    return;
  }
  if (event.type === "light.deleted") {
    const sceneId = Number(event.payload.sceneId);
    const lightId = Number(event.payload.lightId);
    if (sceneId > 0 && lightId > 0) {
      context.commit("vtt/REMOVE_LIGHT", { sceneId, lightId }, { root: true });
      scheduleVisibilityRefresh(context, sceneId);
    }
    return;
  }
  if (event.type === "light.error") {
    context.dispatch("vtt/loadLights", null, { root: true }).catch(() => {});
  }
};

export const createRealtimeLightActions = (ensureSession) => ({
  syncSceneLighting(context, scene) {
    if (!scene?.id) return false;
    const requestId = `scene-lighting-sync-${++requestSerial}`;
    return lightRequestTracker.send(requestId, () =>
      ensureSession(context).changeLight({
        requestId,
        operation: "syncScene",
        sceneId: Number(scene.id),
      }),
    );
  },
  changeLight(context, { operation, light, changes }) {
    const sceneId = Number(
      light?.sceneId ?? context.rootState.vtt?.selectedSceneId,
    );
    if (!sceneId || !["create", "update", "delete"].includes(operation)) {
      return false;
    }
    if (operation !== "create" && (!light?.id || !light?.revision)) {
      return false;
    }
    const requestId = `light-${operation}-${++requestSerial}`;
    return lightRequestTracker.send(requestId, () =>
      ensureSession(context).changeLight({
        requestId,
        operation,
        sceneId,
        ...(light?.id ? { lightId: light.id, revision: light.revision } : {}),
        ...(changes ? { changes } : {}),
      }),
    );
  },
});

import { normalizeRegion } from "@/lib/vtt/regionNormalizer";
import { scheduleVisibilityRefresh } from "./visibilityRefresh";

let requestSerial = 0;

export const routeRealtimeRegionEvent = (context, event) => {
  if (!context.rootState.vtt) return;
  if (event.type === "region.updated") {
    const region = normalizeRegion(event.payload.region);
    if (region.id > 0 && region.sceneId > 0) {
      context.commit("vtt/UPSERT_REGION", region, { root: true });
      scheduleVisibilityRefresh(context, region.sceneId);
    }
  } else if (event.type === "region.deleted") {
    const sceneId = Number(event.payload.sceneId);
    const regionId = Number(event.payload.regionId);
    if (sceneId > 0 && regionId > 0) {
      context.commit(
        "vtt/REMOVE_REGION",
        { sceneId, regionId },
        { root: true },
      );
      scheduleVisibilityRefresh(context, sceneId);
    }
  } else if (event.type === "region.error") {
    context.dispatch("vtt/loadRegions", null, { root: true }).catch(() => {});
  }
};

export const createRealtimeRegionActions = (ensureSession) => ({
  changeRegion(context, { operation, region, changes }) {
    const sceneId = Number(
      region?.sceneId ?? context.rootState.vtt?.selectedSceneId,
    );
    if (!sceneId || !["create", "update", "delete"].includes(operation)) {
      return false;
    }
    if (operation !== "create" && (!region?.id || !region?.revision)) {
      return false;
    }
    return ensureSession(context).changeRegion({
      requestId: `region-${operation}-${++requestSerial}`,
      operation,
      sceneId,
      ...(region?.id ? { regionId: region.id, revision: region.revision } : {}),
      ...(changes ? { changes } : {}),
    });
  },
});

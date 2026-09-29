import { normalizeTile } from "@/lib/vtt/tileNormalizer";

let requestSerial = 0;

export const routeRealtimeTileEvent = (context, event) => {
  if (!context.rootState.vtt) return;
  if (event.type === "tile.updated") {
    const tile = normalizeTile(event.payload.tile);
    if (tile.id > 0 && tile.sceneId > 0) {
      context.commit("vtt/UPSERT_TILE", tile, { root: true });
    }
    return;
  }
  if (event.type === "tile.deleted") {
    const sceneId = Number(event.payload.sceneId);
    const tileId = Number(event.payload.tileId);
    if (sceneId > 0 && tileId > 0) {
      context.commit("vtt/REMOVE_TILE", { sceneId, tileId }, { root: true });
    }
    return;
  }
  if (event.type === "tile.error") {
    context.dispatch("vtt/loadTiles", null, { root: true }).catch(() => {});
  }
};

export const createRealtimeTileActions = (ensureSession) => ({
  changeTile(context, { operation, tile, changes }) {
    const sceneId = Number(
      tile?.sceneId ?? context.rootState.vtt?.selectedSceneId,
    );
    if (!sceneId || !["create", "update", "delete"].includes(operation)) {
      return false;
    }
    if (operation !== "create" && (!tile?.id || !tile?.revision)) {
      return false;
    }
    return ensureSession(context).changeTile({
      requestId: `tile-${operation}-${++requestSerial}`,
      operation,
      sceneId,
      ...(tile?.id ? { tileId: tile.id, revision: tile.revision } : {}),
      ...(changes ? { changes } : {}),
    });
  },
});

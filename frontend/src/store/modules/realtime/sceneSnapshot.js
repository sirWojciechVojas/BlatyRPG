import { normalizeCombat } from "@/lib/vtt/combatNormalizer";
import { normalizeFog } from "@/lib/vtt/fogApiClient";
import { normalizeLight } from "@/lib/vtt/lightNormalizer";
import {
  normalizeScene,
  normalizeSceneCollection,
} from "@/lib/vtt/sceneNormalizer";
import { normalizeTile } from "@/lib/vtt/tileNormalizer";
import { normalizeToken } from "@/lib/vtt/tokenNormalizer";
import { normalizeMovementRequest } from "@/lib/vtt/tokenMovementRequest";
import { normalizeWall } from "@/lib/vtt/wallNormalizer";

const object = (value) =>
  value !== null && typeof value === "object" && !Array.isArray(value);

const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};

const collection = (source, normalize, sceneId = null) => {
  if (!object(source) || !Array.isArray(source.items)) return null;
  const items = source.items.map(normalize).filter((item) => {
    if (!positiveId(item?.id)) return false;
    return sceneId === null || Number(item.sceneId) === Number(sceneId);
  });
  if (items.length !== source.items.length) return null;
  return {
    items,
    capabilities: object(source.capabilities) ? source.capabilities : {},
  };
};

export const normalizeRealtimeSceneSnapshot = (source) => {
  if (!object(source)) return null;
  const scene = normalizeScene(source.scene);
  const sceneId = positiveId(scene?.id);
  if (!sceneId || !object(source.capabilities)) return null;
  const scenes = normalizeSceneCollection(source.scenes);
  if (!scenes.items.some((item) => Number(item.id) === sceneId)) return null;
  const tokens = collection(source.tokens, normalizeToken, sceneId);
  const walls = collection(source.walls, normalizeWall, sceneId);
  const lights = collection(source.lights, normalizeLight, sceneId);
  const tiles = collection(source.tiles, normalizeTile, sceneId);
  const movementRequests = collection(
    source.movementRequests,
    normalizeMovementRequest,
  );
  if (!tokens || !walls || !lights || !tiles || !movementRequests) return null;
  if (!object(source.combat) || !object(source.fog)) return null;
  const fog = normalizeFog(source.fog);
  if (Number(fog.sceneId) !== sceneId || !positiveId(fog.userId)) return null;
  return {
    scene,
    scenes,
    capabilities: source.capabilities,
    tokens,
    walls,
    lights,
    tiles,
    combat: {
      combat: normalizeCombat(source.combat.combat || {}, sceneId),
      capabilities: object(source.combat.capabilities)
        ? source.combat.capabilities
        : {},
    },
    fog,
    movementRequests,
  };
};

export const routeRealtimeSceneSnapshot = (context, event) => {
  if (event.type !== "sync.snapshot" || !context.rootState.vtt) return;
  const snapshot = normalizeRealtimeSceneSnapshot(event.payload?.snapshot);
  if (!snapshot) return;
  context.commit("vtt/RECEIVE_REALTIME_SCENE_SNAPSHOT", snapshot, {
    root: true,
  });
};

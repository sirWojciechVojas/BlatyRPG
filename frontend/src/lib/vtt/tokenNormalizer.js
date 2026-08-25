import { normalizeTokenAngle } from "./tokenFacing";
import { normalizeTokenPermissionScope } from "./tokenPermissions";

const number = (value, fallback = 0) => {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
};

export const normalizeToken = (source = {}) => ({
  id: number(source.id),
  sceneId: number(source.sceneId ?? source.scene_id),
  characterId:
    (source.characterId ?? source.character_id)
      ? number(source.characterId ?? source.character_id)
      : null,
  name: String(source.name || ""),
  imageUrl: String(source.imageUrl ?? source.image_url ?? ""),
  x: number(source.x),
  y: number(source.y),
  width: number(source.width, 100),
  height: number(source.height, 100),
  rotation: normalizeTokenAngle(source.rotation),
  facing: normalizeTokenAngle(source.facing, source.rotation),
  elevation: number(source.elevation),
  disposition: String(source.disposition || "neutral"),
  hidden: source.hidden === true || source.hidden === 1,
  locked: source.locked === true || source.locked === 1,
  visibleTo: normalizeTokenPermissionScope(
    source.visibleTo ?? source.visible_to_json,
    source.hidden === true || source.hidden === 1 ? "gm" : "everyone",
  ),
  controlledBy: normalizeTokenPermissionScope(
    source.controlledBy ?? source.controlled_by_json,
    "inherit",
  ),
  editableBy: normalizeTokenPermissionScope(
    source.editableBy ?? source.editable_by_json,
    "gm",
  ),
  observerBy: normalizeTokenPermissionScope(
    source.observerBy ?? source.observer_by_json,
    "inherit",
  ),
  bars: source.bars || {},
  statuses: Array.isArray(source.statuses) ? source.statuses : [],
  vision: source.vision || {},
  light: source.light || {},
  revision: number(source.revision, 1),
  capabilities: {
    canControl: source.capabilities?.canControl === true,
    canEdit: source.capabilities?.canEdit === true,
    canObserve: source.capabilities?.canObserve === true,
    canManage: source.capabilities?.canManage === true,
  },
});

export const tokenWritePayload = (changes = {}, includeRevision = false) => {
  const allowed = [
    "characterId",
    "name",
    "imageUrl",
    "x",
    "y",
    "width",
    "height",
    "rotation",
    "facing",
    "elevation",
    "disposition",
    "hidden",
    "locked",
    "visibleTo",
    "controlledBy",
    "editableBy",
    "observerBy",
  ];
  const payload = {};
  allowed.forEach((key) => {
    if (changes[key] !== undefined) payload[key] = changes[key];
  });
  if (includeRevision) payload.revision = number(changes.revision);
  return payload;
};

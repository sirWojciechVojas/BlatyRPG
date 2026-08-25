import { normalizeTokenAngle } from "./tokenFacing";
import { normalizeTokenPermissionScope } from "./tokenPermissions";
import { normalizeTokenResources } from "./tokenResources";
import { normalizeTokenResourceBarPosition } from "./tokenResourcePosition";

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
  rotationHandleEnabled:
    source.rotationHandleEnabled === true ||
    source.rotation_handle_enabled === 1,
  facingHandleEnabled:
    source.facingHandleEnabled === true || source.facing_handle_enabled === 1,
  showInfoUnselected:
    source.showInfoUnselected === true || source.show_info_unselected === 1,
  resourceBarPosition: normalizeTokenResourceBarPosition(
    source.resourceBarPosition ?? source.resource_bar_position,
  ),
  movementRange: Math.max(
    0,
    number(source.movementRange ?? source.movement_range, 6),
  ),
  movementSpent: Math.max(
    0,
    number(source.movementSpent ?? source.movement_spent),
  ),
  movementPoints: Math.max(
    0,
    number(
      source.movementPoints ?? source.movement_points,
      number(source.movementRange ?? source.movement_range, 6) -
        number(source.movementSpent ?? source.movement_spent),
    ),
  ),
  movementResetMode: ["turn", "round", "manual"].includes(
    source.movementResetMode ?? source.movement_reset_mode,
  )
    ? (source.movementResetMode ?? source.movement_reset_mode)
    : "turn",
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
  resources: normalizeTokenResources(
    source.resources || { bars: source.bars, bubbles: source.bubbles },
  ),
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
    "rotationHandleEnabled",
    "facingHandleEnabled",
    "showInfoUnselected",
    "resourceBarPosition",
    "movementRange",
    "movementSpent",
    "movementResetMode",
    "elevation",
    "disposition",
    "hidden",
    "locked",
    "visibleTo",
    "controlledBy",
    "editableBy",
    "observerBy",
    "statuses",
    "resources",
    "waypoints",
  ];
  const payload = {};
  allowed.forEach((key) => {
    if (changes[key] !== undefined) payload[key] = changes[key];
  });
  if (includeRevision) payload.revision = number(changes.revision);
  return payload;
};

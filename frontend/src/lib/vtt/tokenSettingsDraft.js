import { normalizeTokenAngle } from "./tokenFacing";
import { normalizeTokenPermissionScope } from "./tokenPermissions";

const finite = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const clamped = (value, minimum, maximum) =>
  Math.min(maximum, Math.max(minimum, finite(value, minimum)));

const scope = (value, fallback) =>
  normalizeTokenPermissionScope(value, fallback);

export const TOKEN_SIZE_PRESETS = Object.freeze([
  { key: "tiny", cells: 0.5 },
  { key: "standard", cells: 1 },
  { key: "large", cells: 2 },
  { key: "huge", cells: 3 },
  { key: "gargantuan", cells: 4 },
]);

export const createTokenSettingsDraft = (token, gridSize) => {
  const size = Math.max(1, finite(gridSize, 100));
  return {
    name: String(token.name || ""),
    imageUrl: String(token.imageUrl || ""),
    widthCells: finite(token.width, size) / size,
    heightCells: finite(token.height, size) / size,
    rotation: normalizeTokenAngle(token.rotation),
    facing: normalizeTokenAngle(token.facing, token.rotation),
    elevation: finite(token.elevation),
    disposition: String(token.disposition || "neutral"),
    hidden: token.hidden === true,
    locked: token.locked === true,
    visibleTo: scope(token.visibleTo, token.hidden ? "gm" : "everyone"),
    controlledBy: scope(token.controlledBy, "inherit"),
    editableBy: scope(token.editableBy, "gm"),
    observerBy: scope(token.observerBy, "inherit"),
  };
};

export const tokenSettingsPayload = (draft, gridSize, canManage) => {
  const size = Math.max(1, finite(gridSize, 100));
  const payload = {
    name: String(draft.name || "").trim(),
    imageUrl: String(draft.imageUrl || "").trim(),
    width: clamped(finite(draft.widthCells, 1) * size, 8, 10000),
    height: clamped(finite(draft.heightCells, 1) * size, 8, 10000),
    rotation: normalizeTokenAngle(draft.rotation),
    facing: normalizeTokenAngle(draft.facing, draft.rotation),
    elevation: finite(draft.elevation),
    disposition: String(draft.disposition || "neutral"),
  };
  if (!canManage) return payload;
  return {
    ...payload,
    hidden: draft.hidden === true,
    locked: draft.locked === true,
    visibleTo: scope(draft.visibleTo, "gm"),
    controlledBy: scope(draft.controlledBy, "gm"),
    editableBy: scope(draft.editableBy, "gm"),
    observerBy: scope(draft.observerBy, "gm"),
  };
};

export const tokenSettingsPosition = (
  anchor,
  viewport,
  panel = { width: 420, height: 700 },
) => {
  const margin = 10;
  const gap = 12;
  const right = finite(anchor?.right);
  const left = finite(anchor?.left);
  const top = finite(anchor?.top);
  const width = Math.min(panel.width, viewport.width - margin * 2);
  const height = Math.min(panel.height, viewport.height - margin * 2);
  const preferredX =
    right + gap + width <= viewport.width - margin
      ? right + gap
      : left - width - gap;
  return {
    left: clamped(preferredX, margin, viewport.width - width - margin),
    top: clamped(top, margin, viewport.height - height - margin),
  };
};

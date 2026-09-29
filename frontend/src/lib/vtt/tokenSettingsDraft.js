import { normalizeTokenAngle } from "./tokenFacing";
import { normalizeTokenPermissionScope } from "./tokenPermissions";
import {
  cloneTokenResources,
  tokenMovementResourceState,
  tokenResourcesWithMovement,
} from "./tokenResources";
import { normalizeTokenResourceBarPosition } from "./tokenResourcePosition";

const finite = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const clamped = (value, minimum, maximum) =>
  Math.min(maximum, Math.max(minimum, finite(value, minimum)));

const scope = (value, fallback) =>
  normalizeTokenPermissionScope(value, fallback);

const color = (value, fallback) =>
  /^#[0-9a-f]{6}$/iu.test(String(value || ""))
    ? String(value).toLocaleLowerCase()
    : fallback;

export const TOKEN_SIZE_PRESETS = Object.freeze([
  { key: "minuscule", cells: 0.25 },
  { key: "tiny", cells: 0.5 },
  { key: "standard", cells: 1 },
  { key: "large", cells: 2 },
  { key: "huge", cells: 3 },
]);

export const createTokenSettingsDraft = (token, gridSize) => {
  const size = Math.max(1, finite(gridSize, 100));
  const movementRange = Math.max(0, finite(token.movementRange, 6));
  const movementSpent = Math.max(0, finite(token.movementSpent));
  return {
    name: String(token.name || ""),
    imageUrl: String(token.imageUrl || ""),
    widthCells: finite(token.width, size) / size,
    heightCells: finite(token.height, size) / size,
    rotation: normalizeTokenAngle(token.rotation),
    facing: normalizeTokenAngle(token.facing, token.rotation),
    rotationHandleEnabled: token.rotationHandleEnabled === true,
    facingHandleEnabled: token.facingHandleEnabled === true,
    showInfoUnselected: token.showInfoUnselected === true,
    resourceBarPosition: normalizeTokenResourceBarPosition(
      token.resourceBarPosition,
    ),
    movementRange,
    movementSpent,
    movementResetMode: ["turn", "round", "manual"].includes(
      token.movementResetMode,
    )
      ? token.movementResetMode
      : "turn",
    elevation: finite(token.elevation),
    disposition: String(token.disposition || "neutral"),
    hidden: token.hidden === true,
    locked: token.locked === true,
    visibleTo: scope(token.visibleTo, token.hidden ? "gm" : "everyone"),
    controlledBy: scope(token.controlledBy, "inherit"),
    editableBy: scope(token.editableBy, "gm"),
    observerBy: scope(token.observerBy, "inherit"),
    vision: {
      enabled: token.vision?.enabled === true,
      range: clamped(
        token.vision?.range ?? token.vision?.dimRadius ?? size * 6,
        0,
        100000,
      ),
      angle: clamped(token.vision?.angle ?? 360, 1, 360),
      direction: normalizeTokenAngle(token.vision?.direction, token.facing),
      minimumRadius: clamped(token.vision?.minimumRadius ?? 0, 0, 100000),
      darkvision: token.vision?.darkvision === true,
      darkvisionRange: clamped(token.vision?.darkvisionRange ?? 0, 0, 100000),
      limitByLight: token.vision?.limitByLight !== false,
      constrainedByWalls: token.vision?.constrainedByWalls !== false,
      showShape: token.vision?.showShape === true,
      shapeBorderColor: color(token.vision?.shapeBorderColor, "#65d7ff"),
      shapeBorderOpacity: clamped(
        token.vision?.shapeBorderOpacity ?? 0.8,
        0,
        1,
      ),
      shapeFillColor: color(token.vision?.shapeFillColor, "#65d7ff"),
      shapeFillOpacity: clamped(token.vision?.shapeFillOpacity ?? 0.12, 0, 1),
    },
    resources: tokenResourcesWithMovement(
      token.resources,
      movementRange,
      Math.max(0, movementRange - movementSpent),
    ),
  };
};

export const tokenSettingsPayload = (draft, gridSize, canManage) => {
  const size = Math.max(1, finite(gridSize, 100));
  const resources = cloneTokenResources(draft.resources);
  const movement = tokenMovementResourceState(
    resources,
    draft.movementRange,
    draft.movementSpent,
  );
  const payload = {
    name: String(draft.name || "").trim(),
    imageUrl: String(draft.imageUrl || "").trim(),
    width: clamped(finite(draft.widthCells, 1) * size, 1, 10000),
    height: clamped(finite(draft.heightCells, 1) * size, 1, 10000),
    rotation: normalizeTokenAngle(draft.rotation),
    facing: normalizeTokenAngle(
      draft.vision?.angle < 360 ? draft.vision?.direction : draft.facing,
      draft.rotation,
    ),
    elevation: finite(draft.elevation),
    disposition: String(draft.disposition || "neutral"),
    resourceBarPosition: normalizeTokenResourceBarPosition(
      draft.resourceBarPosition,
    ),
    resources,
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
    vision: {
      enabled: draft.vision?.enabled === true,
      range: clamped(draft.vision?.range, 0, 100000),
      angle: clamped(draft.vision?.angle, 1, 360),
      direction: normalizeTokenAngle(draft.vision?.direction, draft.facing),
      minimumRadius: clamped(draft.vision?.minimumRadius, 0, 100000),
      darkvision: draft.vision?.darkvision === true,
      darkvisionRange: clamped(draft.vision?.darkvisionRange, 0, 100000),
      limitByLight: draft.vision?.limitByLight !== false,
      constrainedByWalls: draft.vision?.constrainedByWalls !== false,
      showShape: draft.vision?.showShape === true,
      shapeBorderColor: color(draft.vision?.shapeBorderColor, "#65d7ff"),
      shapeBorderOpacity: clamped(draft.vision?.shapeBorderOpacity, 0, 1),
      shapeFillColor: color(draft.vision?.shapeFillColor, "#65d7ff"),
      shapeFillOpacity: clamped(draft.vision?.shapeFillOpacity, 0, 1),
    },
    rotationHandleEnabled: draft.rotationHandleEnabled === true,
    facingHandleEnabled: draft.facingHandleEnabled === true,
    showInfoUnselected: draft.showInfoUnselected === true,
    movementRange: clamped(movement.range, 0, 10000),
    movementSpent: clamped(movement.spent, 0, 10000),
    movementResetMode: ["turn", "round", "manual"].includes(
      draft.movementResetMode,
    )
      ? draft.movementResetMode
      : "turn",
  };
};

export const tokenSettingsPosition = (
  anchor,
  viewport,
  panel = { width: 860, height: 720 },
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

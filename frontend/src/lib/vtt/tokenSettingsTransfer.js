import { normalizeTokenAngle } from "./tokenFacing";
import { normalizeTokenPermissionScope } from "./tokenPermissions";
import { normalizeTokenResourceBarPosition } from "./tokenResourcePosition";
import { cloneTokenResources } from "./tokenResources";

export const TOKEN_SETTINGS_TRANSFER_KIND = "blatyrpg.token-settings";
export const TOKEN_SETTINGS_TRANSFER_VERSION = 1;

const finite = (value, fallback) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const size = (value, fallback = 1) =>
  Math.min(100, Math.max(0.25, finite(value, fallback)));

const movement = (value, fallback = 0) =>
  Math.min(10000, Math.max(0, finite(value, fallback)));

const permission = (value, fallback) =>
  normalizeTokenPermissionScope(value, fallback);

const opacity = (value, fallback) =>
  Math.min(1, Math.max(0, finite(value, fallback)));

const color = (value, fallback) =>
  /^#[0-9a-f]{6}$/iu.test(String(value || ""))
    ? String(value).toLocaleLowerCase()
    : fallback;

const vision = (value = {}) => ({
  enabled: value.enabled === true,
  range: Math.min(100000, Math.max(0, finite(value.range, 600))),
  angle: Math.min(360, Math.max(1, finite(value.angle, 360))),
  direction: normalizeTokenAngle(value.direction),
  minimumRadius: Math.min(100000, Math.max(0, finite(value.minimumRadius, 0))),
  darkvision: value.darkvision === true,
  darkvisionRange: Math.min(
    100000,
    Math.max(0, finite(value.darkvisionRange, 0)),
  ),
  limitByLight: value.limitByLight !== false,
  constrainedByWalls: value.constrainedByWalls !== false,
  showShape: value.showShape === true,
  shapeBorderColor: color(value.shapeBorderColor, "#65d7ff"),
  shapeBorderOpacity: opacity(value.shapeBorderOpacity, 0.8),
  shapeFillColor: color(value.shapeFillColor, "#65d7ff"),
  shapeFillOpacity: opacity(value.shapeFillOpacity, 0.12),
});

const transferableSettings = (draft) => ({
  widthCells: size(draft.widthCells),
  heightCells: size(draft.heightCells),
  rotation: normalizeTokenAngle(draft.rotation),
  facing: normalizeTokenAngle(draft.facing, draft.rotation),
  rotationHandleEnabled: draft.rotationHandleEnabled === true,
  facingHandleEnabled: draft.facingHandleEnabled === true,
  showInfoUnselected: draft.showInfoUnselected === true,
  resourceBarPosition: normalizeTokenResourceBarPosition(
    draft.resourceBarPosition,
  ),
  movementRange: movement(draft.movementRange, 6),
  movementResetMode: ["turn", "round", "manual"].includes(
    draft.movementResetMode,
  )
    ? draft.movementResetMode
    : "turn",
  elevation: finite(draft.elevation, 0),
  disposition: String(draft.disposition || "neutral"),
  hidden: draft.hidden === true,
  locked: draft.locked === true,
  visibleTo: permission(draft.visibleTo, "everyone"),
  controlledBy: permission(draft.controlledBy, "inherit"),
  editableBy: permission(draft.editableBy, "gm"),
  observerBy: permission(draft.observerBy, "inherit"),
  vision: vision(draft.vision),
  resources: cloneTokenResources(draft.resources),
});

export const exportTokenSettings = (draft) =>
  JSON.stringify(
    {
      kind: TOKEN_SETTINGS_TRANSFER_KIND,
      version: TOKEN_SETTINGS_TRANSFER_VERSION,
      settings: transferableSettings(draft),
    },
    null,
    2,
  );

export const importTokenSettings = (text, currentDraft) => {
  let payload;
  try {
    payload = JSON.parse(String(text || ""));
  } catch (_error) {
    throw new TypeError("token_settings_json_invalid");
  }
  if (
    payload?.kind !== TOKEN_SETTINGS_TRANSFER_KIND ||
    payload?.version !== TOKEN_SETTINGS_TRANSFER_VERSION ||
    !payload.settings ||
    typeof payload.settings !== "object" ||
    Array.isArray(payload.settings)
  ) {
    throw new TypeError("token_settings_format_unsupported");
  }
  return {
    ...currentDraft,
    ...transferableSettings({ ...currentDraft, ...payload.settings }),
    name: currentDraft.name,
    imageUrl: currentDraft.imageUrl,
    movementSpent: currentDraft.movementSpent,
  };
};

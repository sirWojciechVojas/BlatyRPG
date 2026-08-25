import { normalizeTokenResourceBarPosition } from "./tokenResourcePosition";

const finite = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const cells = (value) => Math.max(0.25, finite(value, 1));

export const tokenPreviewMetrics = (draft = {}, availableSize = 172) => {
  const widthCells = cells(draft.widthCells);
  const heightCells = cells(draft.heightCells);
  const largestDimension = Math.max(3, widthCells, heightCells);
  const cellSize = Math.min(54, availableSize / largestDimension);
  return {
    cellSize,
    width: Math.max(10, widthCells * cellSize),
    height: Math.max(10, heightCells * cellSize),
  };
};

export const tokenSettingsPreview = (draft = {}, token = {}) => {
  const movementRange = Math.max(0, finite(draft.movementRange, 6));
  const movementSpent = Math.max(0, finite(draft.movementSpent));
  return {
    ...token,
    name: String(draft.name || token.name || ""),
    imageUrl: String(draft.imageUrl || ""),
    rotation: finite(draft.rotation),
    facing: finite(draft.facing, draft.rotation),
    rotationHandleEnabled: draft.rotationHandleEnabled === true,
    facingHandleEnabled: draft.facingHandleEnabled === true,
    showInfoUnselected: draft.showInfoUnselected === true,
    resourceBarPosition: normalizeTokenResourceBarPosition(
      draft.resourceBarPosition,
    ),
    disposition: String(draft.disposition || "neutral"),
    elevation: finite(draft.elevation),
    hidden: draft.hidden === true,
    locked: draft.locked === true,
    movementRange,
    movementSpent,
    movementPoints: Math.max(0, movementRange - movementSpent),
    resources: draft.resources || token.resources || {},
  };
};

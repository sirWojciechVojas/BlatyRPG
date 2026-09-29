import { normalizeTokenResourceBarPosition } from "./tokenResourcePosition";
import {
  synchronizeTokenResourceLinks,
  tokenMovementResourceState,
  tokenResourcesWithMovement,
} from "./tokenResources";

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
  const fallbackRange = Math.max(0, finite(draft.movementRange, 6));
  const fallbackSpent = Math.max(0, finite(draft.movementSpent));
  const resources = draft.resources
    ? synchronizeTokenResourceLinks(draft.resources)
    : tokenResourcesWithMovement(
        token.resources,
        fallbackRange,
        Math.max(0, fallbackRange - fallbackSpent),
      );
  const movement = tokenMovementResourceState(
    resources,
    fallbackRange,
    fallbackSpent,
  );
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
    movementRange: movement.range,
    movementSpent: movement.spent,
    movementPoints: movement.remaining,
    resources,
  };
};

import { snapTokenPosition } from "./grid";
import { tokenMovementPreview } from "./tokenMovement";

const sameId = (left, right) => String(left) === String(right);

export const tokenDragGroup = (tokens, selectedIds, anchor) => {
  const selected = new Set((selectedIds || []).map(String));
  if (selected.size < 2 || !selected.has(String(anchor.id))) return [anchor];
  return (tokens || []).filter((token) => selected.has(String(token.id)));
};

export const tokenGroupBlockers = (tokens) =>
  (tokens || []).filter((token) => {
    if (
      token.locked ||
      token.disabled ||
      token.capabilities?.canControl !== true
    ) {
      return true;
    }
    const remaining = Number(token.movementPoints);
    return (
      token.capabilities?.canManage !== true &&
      Number.isFinite(remaining) &&
      remaining <= 0
    );
  });

const translatedPosition = (scene, anchor, token, position) =>
  snapTokenPosition(
    scene,
    {
      x: Number(token.x) + Number(position.x) - Number(anchor.x),
      y: Number(token.y) + Number(position.y) - Number(anchor.y),
    },
    token,
  );

export const tokenGroupMovementPreviews = (
  scene,
  tokens,
  anchor,
  position,
  waypoints = [],
) =>
  (tokens || []).map((token) => {
    const target = sameId(token.id, anchor.id)
      ? position
      : translatedPosition(scene, anchor, token, position);
    const route = sameId(token.id, anchor.id)
      ? waypoints
      : waypoints.map((waypoint) =>
          translatedPosition(scene, anchor, token, waypoint),
        );
    return {
      token,
      position: target,
      waypoints: route,
      movement: tokenMovementPreview(scene, token, target, route),
    };
  });

export const exceededGroupTokens = (previews) =>
  (previews || [])
    .filter(
      ({ token, movement }) =>
        movement.exceeded && token.capabilities?.canManage !== true,
    )
    .map(({ token }) => token);

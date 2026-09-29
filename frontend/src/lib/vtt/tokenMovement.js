import {
  GRID_TYPES,
  gridCellAtPoint,
  gridCellDistance,
  normalizeGridSettings,
} from "./grid";

const finite = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};
const rounded = (value) => Number(value.toFixed(3));

export const tokenMovementSegmentCost = (scene, start, end) => {
  const settings = normalizeGridSettings(scene);
  if (settings.type === GRID_TYPES.GRIDLESS) {
    return (
      Math.hypot(
        finite(end?.x) - finite(start?.x),
        finite(end?.y) - finite(start?.y),
      ) / settings.size
    );
  }
  return gridCellDistance(
    scene,
    gridCellAtPoint(scene, start),
    gridCellAtPoint(scene, end),
  );
};

export const tokenMovementRouteCost = (scene, points = []) =>
  rounded(
    points
      .slice(1)
      .reduce(
        (total, point, index) =>
          total + tokenMovementSegmentCost(scene, points[index], point),
        0,
      ),
  );

export const tokenRouteCenters = (token, position, waypoints = []) => {
  const width = finite(token?.width, 100);
  const height = finite(token?.height, 100);
  const center = (item) => ({
    x: finite(item?.x) + width / 2,
    y: finite(item?.y) + height / 2,
  });
  const points = [center(token), ...waypoints.map(center), center(position)];
  return points.filter(
    (point, index) =>
      index === 0 ||
      point.x !== points[index - 1].x ||
      point.y !== points[index - 1].y,
  );
};

export const tokenMovementState = (token = {}) => {
  const range = Math.max(0, finite(token.movementRange, 6));
  const spent = Math.max(0, finite(token.movementSpent));
  return {
    range,
    spent,
    remaining: Math.max(0, rounded(range - spent)),
  };
};

export const tokenMovementPreview = (
  scene,
  token,
  position,
  waypoints = [],
) => {
  const points = tokenRouteCenters(token, position, waypoints);
  const cost = tokenMovementRouteCost(scene, points);
  const { spent, range } = tokenMovementState(token);
  const projected = rounded(spent + cost);
  return {
    points,
    cost,
    spent,
    range,
    projected,
    remaining: Math.max(0, rounded(range - projected)),
    exceeded: projected > range + 0.0005,
  };
};

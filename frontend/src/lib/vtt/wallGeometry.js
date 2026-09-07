import { clamp } from "./grid";

export const wallPoint = (event, element, scene, snap = true) => {
  const rect = element.getBoundingClientRect();
  const raw = {
    x: ((event.clientX - rect.left) / Math.max(1, rect.width)) * scene.width,
    y: ((event.clientY - rect.top) / Math.max(1, rect.height)) * scene.height,
  };
  const step = Math.max(1, Number(scene.gridSize) || 100) / 2;
  return {
    x: clamp(snap ? Math.round(raw.x / step) * step : raw.x, 0, scene.width),
    y: clamp(snap ? Math.round(raw.y / step) * step : raw.y, 0, scene.height),
  };
};

export const wallLength = (wall) =>
  Math.hypot(
    Number(wall.x2) - Number(wall.x1),
    Number(wall.y2) - Number(wall.y1),
  );

export const wallMidpoint = (wall) => ({
  x: (Number(wall.x1) + Number(wall.x2)) / 2,
  y: (Number(wall.y1) + Number(wall.y2)) / 2,
});

export const WALL_TYPE_COLORS = Object.freeze({
  wall: "#56D6EF",
  door: "#D6A83E",
  window: "#65BCE8",
  secret: "#BC72DD",
  open: "#71C98B",
  locked: "#E35E54",
  disabled: "#77736D",
});

export const wallColor = (wall = {}) => {
  if (wall.color) return wall.color;
  if (wall.enabled === false) return WALL_TYPE_COLORS.disabled;
  if (wall.type !== "wall" && wall.doorState === "open")
    return WALL_TYPE_COLORS.open;
  if (wall.type !== "wall" && wall.doorState === "locked")
    return WALL_TYPE_COLORS.locked;
  return WALL_TYPE_COLORS[wall.type] || WALL_TYPE_COLORS.wall;
};

const endpoints = (walls = []) =>
  walls.flatMap((wall) => [
    { wall, endpoint: "start", x: Number(wall.x1), y: Number(wall.y1) },
    { wall, endpoint: "end", x: Number(wall.x2), y: Number(wall.y2) },
  ]);

export const nearestWallEndpoint = (
  point,
  walls = [],
  tolerance = 12,
  excludedWallId = null,
) => {
  let nearest = null;
  for (const candidate of endpoints(walls)) {
    if (candidate.wall.id === excludedWallId) continue;
    const distance = Math.hypot(candidate.x - point.x, candidate.y - point.y);
    if (distance <= tolerance && (!nearest || distance < nearest.distance)) {
      nearest = { ...candidate, distance };
    }
  }
  return nearest;
};

export const resolveWallPoint = (
  event,
  element,
  scene,
  walls = [],
  options = {},
) => {
  const raw = wallPoint(event, element, scene, false);
  const connection = options.connect
    ? nearestWallEndpoint(
        raw,
        walls,
        options.tolerance ?? 12,
        options.excludedWallId,
      )
    : null;
  if (connection) {
    return {
      point: { x: connection.x, y: connection.y },
      connection,
    };
  }
  return {
    point: options.snapToGrid ? wallPoint(event, element, scene, true) : raw,
    connection: null,
  };
};

export const connectedWallEndpoints = (walls = [], point, tolerance = 0.01) =>
  endpoints(walls).filter(
    (candidate) =>
      Math.hypot(candidate.x - point.x, candidate.y - point.y) <= tolerance,
  );

const pointDistance = (point, start, end) => {
  const dx = end.x - start.x;
  const dy = end.y - start.y;
  if (dx === 0 && dy === 0)
    return Math.hypot(point.x - start.x, point.y - start.y);
  const ratio = Math.max(
    0,
    Math.min(
      1,
      ((point.x - start.x) * dx + (point.y - start.y) * dy) /
        (dx * dx + dy * dy),
    ),
  );
  return Math.hypot(
    point.x - (start.x + ratio * dx),
    point.y - (start.y + ratio * dy),
  );
};

export const simplifyWallPath = (points = [], tolerance = 4) => {
  if (points.length <= 2) return points.slice();
  let farthest = 0;
  let index = 0;
  for (let cursor = 1; cursor < points.length - 1; cursor += 1) {
    const distance = pointDistance(
      points[cursor],
      points[0],
      points[points.length - 1],
    );
    if (distance > farthest) {
      farthest = distance;
      index = cursor;
    }
  }
  if (farthest <= Math.max(0, Number(tolerance) || 0)) {
    return [points[0], points[points.length - 1]];
  }
  const left = simplifyWallPath(points.slice(0, index + 1), tolerance);
  const right = simplifyWallPath(points.slice(index), tolerance);
  return [...left.slice(0, -1), ...right];
};

export const splitWallForOpening = (
  wall,
  point,
  requestedLength,
  minimumSegment = 2,
) => {
  const length = wallLength(wall);
  if (length < Math.max(6, minimumSegment * 3)) return null;
  const dx = Number(wall.x2) - Number(wall.x1);
  const dy = Number(wall.y2) - Number(wall.y1);
  const projected =
    ((Number(point.x) - Number(wall.x1)) * dx +
      (Number(point.y) - Number(wall.y1)) * dy) /
    (length * length);
  const openingLength = Math.min(
    Math.max(2, Number(requestedLength) || 2),
    length - minimumSegment * 2,
  );
  const half = openingLength / length / 2;
  const minimum = minimumSegment / length;
  const center = Math.max(
    minimum + half,
    Math.min(1 - minimum - half, projected),
  );
  const startRatio = center - half;
  const endRatio = center + half;
  const at = (ratio) => ({
    x: Number(wall.x1) + dx * ratio,
    y: Number(wall.y1) + dy * ratio,
  });
  const start = at(startRatio);
  const end = at(endRatio);
  return {
    before: { x1: wall.x1, y1: wall.y1, x2: start.x, y2: start.y },
    opening: { x1: start.x, y1: start.y, x2: end.x, y2: end.y },
    after: { x1: end.x, y1: end.y, x2: wall.x2, y2: wall.y2 },
  };
};

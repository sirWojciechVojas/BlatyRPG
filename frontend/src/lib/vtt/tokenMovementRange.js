import {
  GRID_TYPES,
  gridCellAtPoint,
  gridCellCenter,
  gridCellVertices,
  normalizeGridSettings,
} from "./grid";
import { tokenMovementState } from "./tokenMovement";

const EXACT_HEX_RADIUS = 24;
const rounded = (value) => Number(value.toFixed(3));
const point = ([x, y]) => `${rounded(x)} ${rounded(y)}`;

const polygonPath = (vertices) =>
  vertices.length
    ? `M ${point(vertices[0])} ${vertices
        .slice(1)
        .map((vertex) => `L ${point(vertex)}`)
        .join(" ")} Z`
    : "";

const cross = (origin, left, right) =>
  (left[0] - origin[0]) * (right[1] - origin[1]) -
  (left[1] - origin[1]) * (right[0] - origin[0]);

const convexHull = (vertices) => {
  const sorted = [...vertices].sort(
    (left, right) => left[0] - right[0] || left[1] - right[1],
  );
  if (sorted.length <= 2) return sorted;
  const half = (items) => {
    const result = [];
    items.forEach((vertex) => {
      while (
        result.length > 1 &&
        cross(result.at(-2), result.at(-1), vertex) <= 0
      ) {
        result.pop();
      }
      result.push(vertex);
    });
    return result;
  };
  const lower = half(sorted);
  const upper = half([...sorted].reverse());
  return [...lower.slice(0, -1), ...upper.slice(0, -1)];
};

const axialCells = (origin, radius) => {
  const cells = [];
  for (let dq = -radius; dq <= radius; dq += 1) {
    const minimum = Math.max(-radius, -dq - radius);
    const maximum = Math.min(radius, -dq + radius);
    for (let dr = minimum; dr <= maximum; dr += 1) {
      cells.push({ q: origin.q + dq, r: origin.r + dr });
    }
  }
  return cells;
};

const hexRangePath = (scene, origin, radius) => {
  if (radius <= EXACT_HEX_RADIUS) {
    return axialCells(origin, radius)
      .map((cell) => polygonPath(gridCellVertices(scene, cell)))
      .join(" ");
  }
  const cornerCells = [
    [radius, 0],
    [0, radius],
    [-radius, radius],
    [-radius, 0],
    [0, -radius],
    [radius, -radius],
  ].map(([q, r]) => ({ q: origin.q + q, r: origin.r + r }));
  const vertices = cornerCells.flatMap((cell) => gridCellVertices(scene, cell));
  return polygonPath(convexHull(vertices));
};

const circlePath = (center, radius) => {
  if (radius <= 0) return "";
  return [
    `M ${rounded(center.x - radius)} ${rounded(center.y)}`,
    `a ${rounded(radius)} ${rounded(radius)} 0 1 0 ${rounded(radius * 2)} 0`,
    `a ${rounded(radius)} ${rounded(radius)} 0 1 0 ${rounded(-radius * 2)} 0`,
    "Z",
  ].join(" ");
};

export const buildTokenMovementRange = (scene = {}, token = {}) => {
  const settings = normalizeGridSettings(scene);
  const movement = tokenMovementState(token);
  const origin = {
    x: Number(token.x || 0) + Number(token.width || settings.size) / 2,
    y: Number(token.y || 0) + Number(token.height || settings.size) / 2,
  };
  if (settings.type === GRID_TYPES.GRIDLESS) {
    return {
      ...movement,
      origin,
      path: circlePath(origin, movement.remaining * settings.size),
    };
  }
  const originCell = gridCellAtPoint(scene, origin);
  const center = gridCellCenter(scene, originCell);
  const radius = Math.floor(movement.remaining + 0.0005);
  if (settings.type === GRID_TYPES.SQUARE) {
    const half = (radius + 0.5) * settings.size;
    return {
      ...movement,
      origin: center,
      path: polygonPath([
        [center.x - half, center.y - half],
        [center.x + half, center.y - half],
        [center.x + half, center.y + half],
        [center.x - half, center.y + half],
      ]),
    };
  }
  return {
    ...movement,
    origin: center,
    path: hexRangePath(scene, originCell, radius),
  };
};

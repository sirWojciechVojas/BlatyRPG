const finite = (value, fallback = 0) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const close = (left, right) =>
  Math.abs(left.x - right.x) < 0.001 && Math.abs(left.y - right.y) < 0.001;

export const clampTokenSelectionPoint = (point, scene = {}) => ({
  x: Math.min(Math.max(0, finite(point?.x)), Math.max(0, finite(scene.width))),
  y: Math.min(Math.max(0, finite(point?.y)), Math.max(0, finite(scene.height))),
});

export const tokenSelectionPolygonPoints = (selection = {}) => {
  const points = [...(selection.points || [])];
  if (
    selection.current &&
    (!points.length || !close(points.at(-1), selection.current))
  ) {
    points.push(selection.current);
  }
  return points;
};

export const tokenSelectionBounds = (selection = {}) => {
  const start = selection.start || { x: 0, y: 0 };
  const current = selection.current || start;
  return {
    x: Math.min(start.x, current.x),
    y: Math.min(start.y, current.y),
    width: Math.abs(current.x - start.x),
    height: Math.abs(current.y - start.y),
  };
};

export const tokenSelectionRadius = (selection = {}) => {
  const start = selection.start || { x: 0, y: 0 };
  const current = selection.current || start;
  return Math.hypot(current.x - start.x, current.y - start.y);
};

const pointOnSegment = (point, start, end) => {
  const cross =
    (point.y - start.y) * (end.x - start.x) -
    (point.x - start.x) * (end.y - start.y);
  if (Math.abs(cross) > 0.001) return false;
  const dot =
    (point.x - start.x) * (end.x - start.x) +
    (point.y - start.y) * (end.y - start.y);
  const length = (end.x - start.x) ** 2 + (end.y - start.y) ** 2;
  return dot >= 0 && dot <= length;
};

export const pointInPolygon = (point, points = []) => {
  if (points.length < 3) return false;
  let inside = false;
  for (
    let index = 0, previous = points.length - 1;
    index < points.length;
    previous = index++
  ) {
    const left = points[index];
    const right = points[previous];
    if (pointOnSegment(point, left, right)) return true;
    const crossed =
      left.y > point.y !== right.y > point.y &&
      point.x <
        ((right.x - left.x) * (point.y - left.y)) / (right.y - left.y) + left.x;
    if (crossed) inside = !inside;
  }
  return inside;
};

export const pointInTokenSelection = (point, selection = {}) => {
  if (selection.type === "rectangle") {
    const bounds = tokenSelectionBounds(selection);
    return (
      point.x >= bounds.x &&
      point.x <= bounds.x + bounds.width &&
      point.y >= bounds.y &&
      point.y <= bounds.y + bounds.height
    );
  }
  if (selection.type === "circle") {
    const start = selection.start || { x: 0, y: 0 };
    return (
      Math.hypot(point.x - start.x, point.y - start.y) <=
      tokenSelectionRadius(selection)
    );
  }
  return pointInPolygon(point, tokenSelectionPolygonPoints(selection));
};

export const selectableTokensInArea = (tokens = [], selection = {}) =>
  tokens.filter((token) => {
    const capabilities = token.capabilities || {};
    if (capabilities.canControl !== true && capabilities.canManage !== true)
      return false;
    return pointInTokenSelection(
      {
        x: finite(token.x) + Math.max(1, finite(token.width, 100)) / 2,
        y: finite(token.y) + Math.max(1, finite(token.height, 100)) / 2,
      },
      selection,
    );
  });

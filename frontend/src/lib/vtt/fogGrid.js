export const fogGrid = (scene = {}, requestedCellSize = 64) => {
  const width = Math.max(1, Number(scene.width) || 1);
  const height = Math.max(1, Number(scene.height) || 1);
  const minimum = Math.max(
    32,
    Math.ceil(Math.max(width, height) / 2048),
    Math.ceil(Math.sqrt((width * height) / 1048576)),
  );
  const cellSize = Math.max(
    minimum,
    Math.min(512, Number(requestedCellSize) || 64),
  );
  const columns = Math.ceil(width / cellSize);
  const rows = Math.ceil(height / cellSize);
  return { width, height, cellSize, columns, rows, length: columns * rows };
};

export const normalizeFogRanges = (
  ranges = [],
  maximum = Number.MAX_SAFE_INTEGER,
) => {
  const normalized = ranges
    .filter((range) => Array.isArray(range) && range.length === 2)
    .map(([start, end]) => [Number(start), Number(end)])
    .filter(([start, end]) => Number.isInteger(start) && Number.isInteger(end))
    .map(([start, end]) => [
      Math.max(0, Math.min(maximum - 1, Math.min(start, end))),
      Math.max(0, Math.min(maximum - 1, Math.max(start, end))),
    ])
    .sort((left, right) => left[0] - right[0]);
  const merged = [];
  normalized.forEach((range) => {
    const previous = merged.at(-1);
    if (previous && range[0] <= previous[1] + 1)
      previous[1] = Math.max(previous[1], range[1]);
    else merged.push(range);
  });
  return merged;
};

export const maskFromRanges = (length, ranges = []) => {
  const mask = new Uint8Array(Math.max(0, length));
  normalizeFogRanges(ranges, mask.length).forEach(([start, end]) =>
    mask.fill(1, start, end + 1),
  );
  return mask;
};

export const rangesFromMask = (mask, predicate = (value) => value > 0) => {
  const ranges = [];
  let start = -1;
  for (let index = 0; index <= mask.length; index += 1) {
    const active = index < mask.length && predicate(mask[index], index);
    if (active && start < 0) start = index;
    if (!active && start >= 0) {
      ranges.push([start, index - 1]);
      start = -1;
    }
  }
  return ranges;
};

export const newMaskRanges = (candidate, existing, blocked = null) =>
  rangesFromMask(
    candidate,
    (value, index) =>
      value > 0 && existing[index] === 0 && (!blocked || blocked[index] === 0),
  );

export const pointInPolygon = (point, polygon = []) => {
  let inside = false;
  for (
    let index = 0, previous = polygon.length - 1;
    index < polygon.length;
    previous = index, index += 1
  ) {
    const left = polygon[index];
    const right = polygon[previous];
    const crosses = left.y > point.y !== right.y > point.y;
    if (
      crosses &&
      point.x <
        ((right.x - left.x) * (point.y - left.y)) / (right.y - left.y) + left.x
    )
      inside = !inside;
  }
  return inside;
};

export const markPolygon = (mask, grid, polygon = [], value = 1) => {
  if (polygon.length < 3) return mask;
  const xs = polygon.map((point) => Number(point.x));
  const ys = polygon.map((point) => Number(point.y));
  const minColumn = Math.max(0, Math.floor(Math.min(...xs) / grid.cellSize));
  const maxColumn = Math.min(
    grid.columns - 1,
    Math.floor(Math.max(...xs) / grid.cellSize),
  );
  const minRow = Math.max(0, Math.floor(Math.min(...ys) / grid.cellSize));
  const maxRow = Math.min(
    grid.rows - 1,
    Math.floor(Math.max(...ys) / grid.cellSize),
  );
  for (let row = minRow; row <= maxRow; row += 1) {
    for (let column = minColumn; column <= maxColumn; column += 1) {
      const point = {
        x: (column + 0.5) * grid.cellSize,
        y: (row + 0.5) * grid.cellSize,
      };
      if (pointInPolygon(point, polygon))
        mask[row * grid.columns + column] = value;
    }
  }
  return mask;
};

export const markCircle = (mask, grid, origin, radius, value = 1) => {
  const polygon = Array.from({ length: 48 }, (_, index) => {
    const angle = (index / 48) * Math.PI * 2;
    return {
      x: origin.x + Math.cos(angle) * radius,
      y: origin.y + Math.sin(angle) * radius,
    };
  });
  return markPolygon(mask, grid, polygon, value);
};

const brushThreshold = (index) =>
  ((Math.imul(index + 1, 2654435761) >>> 0) % 10000) / 10000;

export const markBrush = (
  mask,
  grid,
  origin,
  size,
  hardness = 1,
  value = 1,
) => {
  const radius = Math.max(grid.cellSize / 2, Number(size) / 2 || 0);
  const hard = Math.min(1, Math.max(0, Number(hardness) || 0));
  const innerRadius = radius * hard;
  const minColumn = Math.max(
    0,
    Math.floor((Number(origin.x) - radius) / grid.cellSize),
  );
  const maxColumn = Math.min(
    grid.columns - 1,
    Math.floor((Number(origin.x) + radius) / grid.cellSize),
  );
  const minRow = Math.max(
    0,
    Math.floor((Number(origin.y) - radius) / grid.cellSize),
  );
  const maxRow = Math.min(
    grid.rows - 1,
    Math.floor((Number(origin.y) + radius) / grid.cellSize),
  );
  for (let row = minRow; row <= maxRow; row += 1) {
    for (let column = minColumn; column <= maxColumn; column += 1) {
      const dx = (column + 0.5) * grid.cellSize - Number(origin.x);
      const dy = (row + 0.5) * grid.cellSize - Number(origin.y);
      const distance = Math.hypot(dx, dy);
      if (distance > radius) continue;
      const coverage =
        distance <= innerRadius || innerRadius >= radius
          ? 1
          : (radius - distance) / Math.max(0.0001, radius - innerRadius);
      const index = row * grid.columns + column;
      if (coverage >= brushThreshold(index)) mask[index] = value;
    }
  }
  return mask;
};

export const cellAtPoint = (grid, point) => {
  const column = Math.floor(Number(point.x) / grid.cellSize);
  const row = Math.floor(Number(point.y) / grid.cellSize);
  if (column < 0 || row < 0 || column >= grid.columns || row >= grid.rows)
    return -1;
  return row * grid.columns + column;
};

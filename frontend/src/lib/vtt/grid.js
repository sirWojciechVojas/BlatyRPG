export const GRID_TYPES = Object.freeze({
  GRIDLESS: "gridless",
  SQUARE: "square",
  HEX_POINTY: "hex_pointy",
  HEX_FLAT: "hex_flat",
});

const SUPPORTED_TYPES = new Set(Object.values(GRID_TYPES));
const SQRT_THREE = Math.sqrt(3);
const rounded = (value) => Number(value.toFixed(3));
const point = ([x, y]) => `${rounded(x)} ${rounded(y)}`;
const finite = (value, fallback = 0) => {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
};

export const clamp = (value, minimum, maximum) =>
  Math.min(maximum, Math.max(minimum, Number(value)));

export const normalizeGridSettings = (scene = {}) => {
  const type = SUPPORTED_TYPES.has(scene.gridType)
    ? scene.gridType
    : GRID_TYPES.SQUARE;
  return {
    type,
    size: clamp(scene.gridSize || 100, 1, 1000),
    offsetX: finite(scene.gridOffsetX),
    offsetY: finite(scene.gridOffsetY),
    color: String(scene.gridColor || "#000000"),
    opacity: clamp(scene.gridOpacity ?? 0.35, 0, 1),
  };
};

const roundAxial = (q, r) => {
  let x = Math.round(q);
  let z = Math.round(r);
  const y = Math.round(-q - r);
  const xDifference = Math.abs(x - q);
  const yDifference = Math.abs(y + q + r);
  const zDifference = Math.abs(z - r);
  if (xDifference > yDifference && xDifference > zDifference) x = -y - z;
  else if (zDifference > yDifference) z = -x - y;
  return { q: x, r: z };
};

export const gridCellAtPoint = (scene = {}, pointValue = {}) => {
  const settings = normalizeGridSettings(scene);
  if (settings.type === GRID_TYPES.GRIDLESS) return null;
  const x = finite(pointValue.x) - settings.offsetX;
  const y = finite(pointValue.y) - settings.offsetY;
  if (settings.type === GRID_TYPES.SQUARE) {
    return {
      q: Math.round((x - settings.size / 2) / settings.size),
      r: Math.round((y - settings.size / 2) / settings.size),
    };
  }
  if (settings.type === GRID_TYPES.HEX_POINTY) {
    return roundAxial(
      x / settings.size - y / (settings.size * SQRT_THREE),
      (2 * y) / (settings.size * SQRT_THREE),
    );
  }
  return roundAxial(
    (2 * x) / (settings.size * SQRT_THREE),
    y / settings.size - x / (settings.size * SQRT_THREE),
  );
};

export const gridCellCenter = (scene = {}, cell = {}) => {
  const settings = normalizeGridSettings(scene);
  const q = finite(cell.q);
  const r = finite(cell.r);
  if (settings.type === GRID_TYPES.SQUARE) {
    return {
      x: settings.offsetX + (q + 0.5) * settings.size,
      y: settings.offsetY + (r + 0.5) * settings.size,
    };
  }
  return settings.type === GRID_TYPES.HEX_POINTY
    ? {
        x: settings.offsetX + settings.size * (q + r / 2),
        y: settings.offsetY + (settings.size * SQRT_THREE * r) / 2,
      }
    : {
        x: settings.offsetX + (settings.size * SQRT_THREE * q) / 2,
        y: settings.offsetY + settings.size * (r + q / 2),
      };
};

export const gridCellDistance = (scene = {}, start = {}, end = {}) => {
  const settings = normalizeGridSettings(scene);
  const dq = finite(end.q) - finite(start.q);
  const dr = finite(end.r) - finite(start.r);
  return settings.type === GRID_TYPES.SQUARE
    ? Math.max(Math.abs(dq), Math.abs(dr))
    : (Math.abs(dq) + Math.abs(dr) + Math.abs(dq + dr)) / 2;
};

export const gridCellVertices = (scene = {}, cell = {}) => {
  const settings = normalizeGridSettings(scene);
  const center = gridCellCenter(scene, cell);
  if (settings.type === GRID_TYPES.SQUARE) {
    const half = settings.size / 2;
    return [
      [center.x - half, center.y - half],
      [center.x + half, center.y - half],
      [center.x + half, center.y + half],
      [center.x - half, center.y + half],
    ];
  }
  const radius = settings.size / SQRT_THREE;
  const startAngle = settings.type === GRID_TYPES.HEX_POINTY ? -Math.PI / 2 : 0;
  return Array.from({ length: 6 }, (_, index) => {
    const angle = startAngle + (Math.PI / 3) * index;
    return [
      center.x + radius * Math.cos(angle),
      center.y + radius * Math.sin(angle),
    ];
  });
};

export const snapPointToGrid = (scene = {}, pointValue = {}) => {
  const settings = normalizeGridSettings(scene);
  if (settings.type === GRID_TYPES.GRIDLESS) {
    return { x: finite(pointValue.x), y: finite(pointValue.y) };
  }
  const center = gridCellCenter(scene, gridCellAtPoint(scene, pointValue));
  return { x: rounded(center.x), y: rounded(center.y) };
};

export const snapTokenPosition = (scene = {}, position = {}, token = {}) => {
  const settings = normalizeGridSettings(scene);
  if (settings.type === GRID_TYPES.GRIDLESS) {
    return { x: finite(position.x), y: finite(position.y) };
  }
  const width = Math.max(1, finite(token.width, settings.size));
  const height = Math.max(1, finite(token.height, settings.size));
  const center = snapPointToGrid(scene, {
    x: finite(position.x) + width / 2,
    y: finite(position.y) + height / 2,
  });
  return {
    x: rounded(center.x - width / 2),
    y: rounded(center.y - height / 2),
  };
};

const hexPath = (centers, radius, startAngle) =>
  centers
    .map(([centerX, centerY]) => {
      const vertices = Array.from({ length: 6 }, (_, index) => {
        const angle = startAngle + (Math.PI / 3) * index;
        return [
          centerX + radius * Math.cos(angle),
          centerY + radius * Math.sin(angle),
        ];
      });
      return `M ${point(vertices[0])} ${vertices
        .slice(1)
        .map((vertex) => `L ${point(vertex)}`)
        .join(" ")} Z`;
    })
    .join(" ");

const pointyPattern = (size) => {
  const radius = size / SQRT_THREE;
  const height = 3 * radius;
  const centers = [
    [0, 0],
    [size, 0],
    [size * 2, 0],
    [size / 2, height / 2],
    [size * 1.5, height / 2],
    [0, height],
    [size, height],
    [size * 2, height],
  ];
  return {
    width: size * 2,
    height,
    path: hexPath(centers, radius, -Math.PI / 2),
  };
};

const flatPattern = (size) => {
  const radius = size / SQRT_THREE;
  const width = 3 * radius;
  const centers = [
    [0, 0],
    [0, size],
    [0, size * 2],
    [width / 2, size / 2],
    [width / 2, size * 1.5],
    [width, 0],
    [width, size],
    [width, size * 2],
  ];
  return {
    width,
    height: size * 2,
    path: hexPath(centers, radius, 0),
  };
};

export const buildGridPattern = (scene = {}) => {
  const settings = normalizeGridSettings(scene);
  if (settings.type === GRID_TYPES.GRIDLESS) return null;

  let geometry;
  if (settings.type === GRID_TYPES.SQUARE) {
    geometry = {
      width: settings.size,
      height: settings.size,
      path: `M ${settings.size} 0 H 0 V ${settings.size}`,
    };
  } else if (settings.type === GRID_TYPES.HEX_POINTY) {
    geometry = pointyPattern(settings.size);
  } else {
    geometry = flatPattern(settings.size);
  }
  return { ...settings, ...geometry };
};

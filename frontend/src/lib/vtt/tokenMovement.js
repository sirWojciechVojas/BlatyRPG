const SQRT_THREE = Math.sqrt(3);
const finite = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};
const rounded = (value) => Number(value.toFixed(3));

const roundAxial = (q, r) => {
  let x = Math.round(q);
  let z = Math.round(r);
  const y = Math.round(-q - r);
  const dx = Math.abs(x - q);
  const dy = Math.abs(y + q + r);
  const dz = Math.abs(z - r);
  if (dx > dy && dx > dz) x = -y - z;
  else if (dz > dy) z = -x - y;
  return { q: x, r: z };
};

const gridCell = (scene, point) => {
  const size = Math.max(1, finite(scene?.gridSize, 100));
  const x = finite(point?.x) - finite(scene?.gridOffsetX);
  const y = finite(point?.y) - finite(scene?.gridOffsetY);
  if ((scene?.gridType || "square") === "square") {
    return {
      q: Math.round((x - size / 2) / size),
      r: Math.round((y - size / 2) / size),
    };
  }
  return scene?.gridType === "hex_pointy"
    ? roundAxial(
        x / size - y / (size * SQRT_THREE),
        (2 * y) / (size * SQRT_THREE),
      )
    : roundAxial(
        (2 * x) / (size * SQRT_THREE),
        y / size - x / (size * SQRT_THREE),
      );
};

export const tokenMovementSegmentCost = (scene, start, end) => {
  const size = Math.max(1, finite(scene?.gridSize, 100));
  if (scene?.gridType === "gridless") {
    return (
      Math.hypot(
        finite(end?.x) - finite(start?.x),
        finite(end?.y) - finite(start?.y),
      ) / size
    );
  }
  const a = gridCell(scene, start);
  const b = gridCell(scene, end);
  if ((scene?.gridType || "square") === "square") {
    return Math.max(Math.abs(b.q - a.q), Math.abs(b.r - a.r));
  }
  const dq = b.q - a.q;
  const dr = b.r - a.r;
  return (Math.abs(dq) + Math.abs(dr) + Math.abs(dq + dr)) / 2;
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

export const tokenMovementPreview = (
  scene,
  token,
  position,
  waypoints = [],
) => {
  const points = tokenRouteCenters(token, position, waypoints);
  const cost = tokenMovementRouteCost(scene, points);
  const spent = Math.max(0, finite(token?.movementSpent));
  const range = Math.max(0, finite(token?.movementRange, 6));
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

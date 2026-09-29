const distance = (left, right) =>
  Math.hypot(
    Number(left.x) - Number(right.x),
    Number(left.y) - Number(right.y),
  );

const endpoints = (wall) => [
  { x: Number(wall.x1), y: Number(wall.y1) },
  { x: Number(wall.x2), y: Number(wall.y2) },
];

const nearestConnection = (point, walls, tolerance) => {
  let match = null;
  walls.forEach((wall, wallIndex) => {
    endpoints(wall).forEach((candidate, endpointIndex) => {
      const separation = distance(point, candidate);
      if (separation <= tolerance && (!match || separation < match.distance)) {
        match = { wallIndex, endpointIndex, distance: separation };
      }
    });
  });
  return match;
};

export const wallsToPolygons = (source = [], tolerance = 0.01) => {
  const remaining = source.map((wall) => ({ ...wall }));
  const polygons = [];
  while (remaining.length) {
    const first = remaining.shift();
    const [origin, next] = endpoints(first);
    const polygon = [origin, next];
    while (remaining.length) {
      const current = polygon[polygon.length - 1];
      if (polygon.length >= 3 && distance(current, origin) <= tolerance) {
        polygon.pop();
        break;
      }
      const match = nearestConnection(current, remaining, tolerance);
      if (!match) {
        const candidates = remaining.flatMap(endpoints);
        const closest = candidates.sort(
          (left, right) => distance(current, left) - distance(current, right),
        )[0];
        return {
          closed: false,
          polygons,
          gap: closest
            ? { from: current, to: closest }
            : { from: current, to: origin },
        };
      }
      const [a, b] = endpoints(remaining[match.wallIndex]);
      remaining.splice(match.wallIndex, 1);
      polygon.push(match.endpointIndex === 0 ? b : a);
    }
    const last = polygon[polygon.length - 1];
    if (polygon.length < 3 || distance(last, origin) > tolerance) {
      return { closed: false, polygons, gap: { from: last, to: origin } };
    }
    if (distance(last, origin) <= tolerance) polygon.pop();
    polygons.push(polygon);
  }
  return { closed: polygons.length > 0, polygons, gap: null };
};

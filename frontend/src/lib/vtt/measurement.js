const finite = (value) => (Number.isFinite(Number(value)) ? Number(value) : 0);

export const measuredDistance = (scene, start, end, scale = 1) => {
  const dx = finite(end.x) - finite(start.x);
  const dy = finite(end.y) - finite(start.y);
  const pixels = Math.hypot(dx, dy) / Math.max(0.05, finite(scale));
  const gridSize = Math.max(1, finite(scene?.gridSize) || 100);
  const gridDistance = Math.max(0.001, finite(scene?.gridDistance) || 1);
  return pixels * (gridDistance / gridSize);
};

export const formatDistance = (value, unit = "") => {
  const rounded = value >= 100 ? Math.round(value) : Number(value.toFixed(1));
  return `${rounded} ${String(unit || "").trim()}`.trim();
};

export const conePath = (start, end, angleDegrees = 60) => {
  const dx = end.x - start.x;
  const dy = end.y - start.y;
  const radius = Math.hypot(dx, dy);
  if (radius < 1) return `M ${start.x} ${start.y}`;
  const direction = Math.atan2(dy, dx);
  const half = (angleDegrees * Math.PI) / 360;
  const left = {
    x: start.x + Math.cos(direction - half) * radius,
    y: start.y + Math.sin(direction - half) * radius,
  };
  const right = {
    x: start.x + Math.cos(direction + half) * radius,
    y: start.y + Math.sin(direction + half) * radius,
  };
  return `M ${start.x} ${start.y} L ${left.x} ${left.y} A ${radius} ${radius} 0 0 1 ${right.x} ${right.y} Z`;
};

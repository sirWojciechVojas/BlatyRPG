import { formatDistance, measuredDistance } from "@/lib/vtt/measurement";
import { tokenMovementPreview } from "@/lib/vtt/tokenMovement";

export const buildTokenDragIndicator = (
  scene,
  token,
  position,
  waypoints = [],
) => {
  if (!scene || !token || !position) return null;
  const halfWidth = Number(token.width) / 2;
  const halfHeight = Number(token.height) / 2;
  const start = {
    x: Number(token.x) + halfWidth,
    y: Number(token.y) + halfHeight,
  };
  const end = {
    x: Number(position.x) + halfWidth,
    y: Number(position.y) + halfHeight,
  };
  const movement = tokenMovementPreview(scene, token, position, waypoints);
  const distance = movement.points
    .slice(1)
    .reduce(
      (total, point, index) =>
        total + measuredDistance(scene, movement.points[index], point),
      0,
    );
  return {
    start,
    end,
    points: movement.points,
    polyline: movement.points.map((point) => `${point.x},${point.y}`).join(" "),
    waypoints: movement.points.slice(1, -1),
    movement: `${movement.projected} / ${movement.range} PR`,
    exceeded: movement.exceeded,
    name: String(token.name || ""),
    imageUrl: String(token.imageUrl || ""),
    initials: String(token.name || "?")
      .split(/\s+/u)
      .slice(0, 2)
      .map((part) => part[0])
      .join("")
      .toLocaleUpperCase(),
    width: Number(token.width),
    height: Number(token.height),
    radius: Math.max(Number(token.width), Number(token.height)) / 2 + 14,
    distance: formatDistance(distance, scene.gridUnit),
  };
};

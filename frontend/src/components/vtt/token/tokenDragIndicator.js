import { formatDistance, measuredDistance } from "@/lib/vtt/measurement";

export const buildTokenDragIndicator = (scene, token, position) => {
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
  return {
    start,
    end,
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
    distance: formatDistance(
      measuredDistance(scene, start, end),
      scene.gridUnit,
    ),
  };
};

import { normalizeTokenResources } from "./tokenResources";

const finite = (value) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
};

export const movementPercent = (points, range) => {
  const safeRange = Math.max(0, finite(range));
  if (safeRange === 0) return 0;
  return Math.min(100, Math.max(0, (finite(points) / safeRange) * 100));
};

export const tokenInitials = (name) => {
  const initials = String(name || "?")
    .trim()
    .split(/\s+/u)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join("")
    .toLocaleUpperCase();
  return initials || "?";
};

export const tokenMovementColor = (token = {}) => {
  const movementBar = normalizeTokenResources(token.resources).bars.find(
    ({ movementSource }) => movementSource,
  );
  return movementBar?.color || "#4caf72";
};

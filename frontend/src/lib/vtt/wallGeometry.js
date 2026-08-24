import { clamp } from "./grid";

export const wallPoint = (event, element, scene, snap = true) => {
  const rect = element.getBoundingClientRect();
  const raw = {
    x: ((event.clientX - rect.left) / Math.max(1, rect.width)) * scene.width,
    y: ((event.clientY - rect.top) / Math.max(1, rect.height)) * scene.height,
  };
  const step = Math.max(1, Number(scene.gridSize) || 100) / 2;
  return {
    x: clamp(snap ? Math.round(raw.x / step) * step : raw.x, 0, scene.width),
    y: clamp(snap ? Math.round(raw.y / step) * step : raw.y, 0, scene.height),
  };
};

export const wallLength = (wall) =>
  Math.hypot(
    Number(wall.x2) - Number(wall.x1),
    Number(wall.y2) - Number(wall.y1),
  );

export const wallMidpoint = (wall) => ({
  x: (Number(wall.x1) + Number(wall.x2)) / 2,
  y: (Number(wall.y1) + Number(wall.y2)) / 2,
});

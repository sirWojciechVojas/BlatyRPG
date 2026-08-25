export const TOKEN_ACTOR_MIME = "application/x-blatyrpg-actor";

export const readDroppedActor = (dataTransfer) => {
  try {
    const value = JSON.parse(dataTransfer?.getData(TOKEN_ACTOR_MIME) || "");
    const id = Number(value?.id);
    return Number.isSafeInteger(id) && id > 0 ? { ...value, id } : null;
  } catch (_error) {
    return null;
  }
};

export const canvasDropPosition = (event, viewport, camera, padding = 0) => {
  const rect = viewport.getBoundingClientRect();
  const scale = Math.max(0.05, Number(camera.scale) || 1);
  return {
    x: (event.clientX - rect.left - camera.x) / scale - padding,
    y: (event.clientY - rect.top - camera.y) / scale - padding,
  };
};

export const TOKEN_RESOURCE_BAR_POSITIONS = Object.freeze([
  "above",
  "top-overlap",
  "bottom-overlap",
  "below",
]);

export const normalizeTokenResourceBarPosition = (value) =>
  TOKEN_RESOURCE_BAR_POSITIONS.includes(value) ? value : "below";

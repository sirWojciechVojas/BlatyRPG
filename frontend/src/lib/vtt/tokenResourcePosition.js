export const TOKEN_RESOURCE_BAR_POSITIONS = Object.freeze([
  "above",
  "top-overlap",
  "bottom-overlap",
  "below",
]);

export const normalizeTokenResourceBarPosition = (value) =>
  TOKEN_RESOURCE_BAR_POSITIONS.includes(value) ? value : "below";

export const tokenResourceStackHeight = (barCount) => {
  const count = Math.max(0, Number(barCount) || 0);
  return count > 0 ? count * 15 - 2 : 0;
};

export const tokenResourceBubbleOffsets = (position, barCount) => {
  const normalized = normalizeTokenResourceBarPosition(position);
  const stackHeight = tokenResourceStackHeight(barCount);
  return {
    top:
      normalized === "above"
        ? stackHeight + 49
        : normalized === "top-overlap"
          ? stackHeight / 2 + 42
          : 42,
    bottom:
      normalized === "below"
        ? stackHeight + 65
        : normalized === "bottom-overlap"
          ? stackHeight / 2 + 60
          : 66,
  };
};

export const SOUND_EFFECT_SCREEN_LIMITS = Object.freeze({
  columns: Object.freeze({ min: 1, max: 20 }),
  rows: Object.freeze({ min: 1, max: 10 }),
  textLines: Object.freeze({ min: 1, max: 5 }),
});

export const SOUND_EFFECT_PAD_STYLES = Object.freeze([
  "square",
  "wide",
  "compact",
]);

export const DEFAULT_SOUND_EFFECT_SCREEN_LAYOUT = Object.freeze({
  columns: 3,
  rows: 4,
  textLines: 1,
  padStyle: "square",
});

const boundedInteger = (value, fallback, limits) => {
  const parsed = Number(value);
  if (!Number.isInteger(parsed)) return fallback;
  return Math.max(limits.min, Math.min(limits.max, parsed));
};

export const normalizeSoundEffectScreenLayout = (screen = {}) => ({
  columns: boundedInteger(
    screen.columns,
    DEFAULT_SOUND_EFFECT_SCREEN_LAYOUT.columns,
    SOUND_EFFECT_SCREEN_LIMITS.columns,
  ),
  rows: boundedInteger(
    screen.rows,
    DEFAULT_SOUND_EFFECT_SCREEN_LAYOUT.rows,
    SOUND_EFFECT_SCREEN_LIMITS.rows,
  ),
  textLines: boundedInteger(
    screen.textLines,
    DEFAULT_SOUND_EFFECT_SCREEN_LAYOUT.textLines,
    SOUND_EFFECT_SCREEN_LIMITS.textLines,
  ),
  padStyle: SOUND_EFFECT_PAD_STYLES.includes(screen.padStyle)
    ? screen.padStyle
    : DEFAULT_SOUND_EFFECT_SCREEN_LAYOUT.padStyle,
});

export const soundEffectScreenCapacity = (screen = {}) => {
  const layout = normalizeSoundEffectScreenLayout(screen);
  return layout.columns * layout.rows;
};

export const soundEffectScreenGridStyle = (screen = {}) => {
  const layout = normalizeSoundEffectScreenLayout(screen);
  return {
    gridTemplateColumns: `repeat(${layout.columns}, minmax(0, 1fr))`,
    gridTemplateRows: `repeat(${layout.rows}, minmax(0, 1fr))`,
    "--sound-effect-text-lines": layout.textLines,
  };
};

export const visibleSoundEffectSlotCount = (screen = {}) => {
  const capacity = soundEffectScreenCapacity(screen);
  return (screen.slots || []).filter(
    (slot) => Number(slot.position) >= 0 && Number(slot.position) < capacity,
  ).length;
};

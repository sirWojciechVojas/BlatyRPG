const BAR_DEFAULTS = [
  { enabled: true, label: "HP", value: 0, max: 0, color: "#d95d55" },
  { enabled: true, label: "PR", value: 6, max: 6, color: "#4caf72" },
  { enabled: false, label: "", value: 0, max: 0, color: "#4f91d9" },
  { enabled: false, label: "", value: 0, max: 0, color: "#d5a64f" },
];
const LEGACY_BAR_COLORS = ["#4caf72", "#d95d55", "#4f91d9", "#d5a64f"];
const BUBBLE_POSITIONS = ["top-left", "top-center", "top-right"];

const finite = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? number : fallback;
};

const text = (value, limit = 30) =>
  String(value || "")
    .trim()
    .slice(0, limit);

const path = (value) => {
  const normalized = text(value, 180);
  const segments = normalized.split(".");
  const blocked = ["__proto__", "prototype", "constructor"];
  return normalized.match(/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+){0,7}$/u) &&
    !segments.some((segment) => blocked.includes(segment.toLocaleLowerCase()))
    ? normalized
    : "";
};

const bar = (source, index = 0) => {
  const fallback = BAR_DEFAULTS[index];
  const value = source || fallback;
  return {
    enabled: value.enabled === true,
    label: text(value.label),
    value: finite(value.value),
    max: finite(value.max),
    color: /^#[0-9a-f]{6}$/iu.test(value.color)
      ? value.color.toLocaleLowerCase()
      : fallback.color,
    attributePath: path(value.attributePath),
    maxAttributePath: path(value.maxAttributePath),
  };
};

const bubble = (source = {}, index = 0) => ({
  enabled: source.enabled === true,
  label: text(source.label),
  value: finite(source.value),
  position: [
    "top-left",
    "top-center",
    "top-right",
    "bottom-left",
    "bottom-center",
    "bottom-right",
  ].includes(source.position)
    ? source.position
    : BUBBLE_POSITIONS[index],
  attributePath: path(source.attributePath),
});

const legacyEmptyBars = (bars) =>
  bars.length === LEGACY_BAR_COLORS.length &&
  bars.every(
    (item, index) =>
      item?.enabled !== true &&
      !text(item?.label) &&
      finite(item?.value) === 0 &&
      finite(item?.max) === 0 &&
      !path(item?.attributePath) &&
      !path(item?.maxAttributePath) &&
      String(item?.color || "").toLocaleLowerCase() ===
        LEGACY_BAR_COLORS[index],
  );

export const normalizeTokenResources = (source = {}) => {
  const suppliedBars = Array.isArray(source.bars) ? source.bars : [];
  const bars = legacyEmptyBars(suppliedBars) ? [] : suppliedBars;
  const bubbles = Array.isArray(source.bubbles) ? source.bubbles : [];
  return {
    bars: BAR_DEFAULTS.map((_, index) => bar(bars[index], index)),
    bubbles: BUBBLE_POSITIONS.map((_, index) => bubble(bubbles[index], index)),
  };
};

export const tokenDisplayResourceBars = (token = {}) => {
  const resources = normalizeTokenResources(token.resources);
  const range = Math.max(0, finite(token.movementRange, 6));
  const remaining = Math.max(
    0,
    finite(
      token.movementPoints,
      range - Math.max(0, finite(token.movementSpent)),
    ),
  );
  return resources.bars
    .map((item, index) =>
      index === 1 && item.label.toLocaleUpperCase() === "PR"
        ? { ...item, value: remaining, max: range }
        : item,
    )
    .filter(({ enabled }) => enabled);
};

export const cloneTokenResources = (resources) =>
  normalizeTokenResources(JSON.parse(JSON.stringify(resources || {})));

export const activeTokenResources = (resources) => {
  const normalized = normalizeTokenResources(resources);
  return {
    bars: normalized.bars.filter(({ enabled }) => enabled),
    bubbles: normalized.bubbles.filter(({ enabled }) => enabled),
  };
};

export const tokenBarPercent = (barValue) => {
  const maximum = finite(barValue?.max);
  if (maximum <= 0) return 0;
  return Math.max(0, Math.min(100, (finite(barValue?.value) / maximum) * 100));
};

export const numericActorAttributes = (data, maximum = 300) => {
  const result = [];
  const blocked = new Set(["__proto__", "prototype", "constructor"]);
  const visit = (value, segments) => {
    if (result.length >= maximum || segments.length > 8) return;
    if (typeof value === "number" && Number.isFinite(value)) {
      result.push({ path: segments.join("."), value });
      return;
    }
    if (!value || typeof value !== "object" || Array.isArray(value)) return;
    Object.entries(value).forEach(([key, child]) => {
      if (!blocked.has(key.toLocaleLowerCase()))
        visit(child, [...segments, key]);
    });
  };
  visit(data, []);
  return result.sort((left, right) => left.path.localeCompare(right.path));
};

const BAR_COLORS = ["#4caf72", "#d95d55", "#4f91d9", "#d5a64f"];
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

const bar = (source = {}, index = 0) => ({
  enabled: source.enabled === true,
  label: text(source.label),
  value: finite(source.value),
  max: finite(source.max),
  color: /^#[0-9a-f]{6}$/iu.test(source.color)
    ? source.color.toLocaleLowerCase()
    : BAR_COLORS[index],
  attributePath: path(source.attributePath),
  maxAttributePath: path(source.maxAttributePath),
});

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

export const normalizeTokenResources = (source = {}) => {
  const bars = Array.isArray(source.bars) ? source.bars : [];
  const bubbles = Array.isArray(source.bubbles) ? source.bubbles : [];
  return {
    bars: BAR_COLORS.map((_, index) => bar(bars[index], index)),
    bubbles: BUBBLE_POSITIONS.map((_, index) => bubble(bubbles[index], index)),
  };
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

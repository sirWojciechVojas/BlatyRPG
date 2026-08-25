const BAR_DEFAULTS = [
  { enabled: true, label: "HP", value: 0, max: 0, color: "#d95d55" },
  {
    enabled: true,
    label: "PR",
    value: 6,
    max: 6,
    color: "#4caf72",
    movementSource: true,
  },
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
    movementSource:
      typeof value.movementSource === "boolean"
        ? value.movementSource
        : fallback.movementSource === true,
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
  linkedBarIndex: [0, 1, 2, 3].includes(source.linkedBarIndex)
    ? source.linkedBarIndex
    : null,
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
  let movementSourceClaimed = false;
  const normalizedBars = BAR_DEFAULTS.map((_, index) =>
    bar(bars[index], index),
  ).map((item) => {
    const movementSource = item.movementSource && !movementSourceClaimed;
    if (movementSource) movementSourceClaimed = true;
    return {
      ...item,
      movementSource,
      enabled: movementSource ? true : item.enabled,
    };
  });
  return {
    bars: normalizedBars,
    bubbles: BUBBLE_POSITIONS.map((_, index) => bubble(bubbles[index], index)),
  };
};

export const tokenDisplayResourceBars = (token = {}) => {
  const range = Math.max(0, finite(token.movementRange, 6));
  const spent = Math.max(0, finite(token.movementSpent));
  const resources = tokenResourcesWithMovement(
    token.resources,
    range,
    Math.max(0, finite(token.movementPoints, range - spent)),
  );
  return resources.bars.filter(({ enabled }) => enabled);
};

export const tokenMovementResourceIndex = (resources) =>
  normalizeTokenResources(resources).bars.findIndex(
    ({ movementSource }) => movementSource,
  );

export const synchronizeTokenResourceLinks = (resources) => {
  const normalized = normalizeTokenResources(resources);
  normalized.bubbles.forEach((bubble) => {
    if (bubble.linkedBarIndex !== null) {
      bubble.value = normalized.bars[bubble.linkedBarIndex].value;
    }
  });
  return normalized;
};

export const updateLinkedTokenBubble = (resources, bubbleIndex) => {
  const normalized = normalizeTokenResources(resources);
  const bubble = normalized.bubbles[bubbleIndex];
  if (bubble?.linkedBarIndex !== null && bubble?.linkedBarIndex !== undefined) {
    normalized.bars[bubble.linkedBarIndex].value = bubble.value;
  }
  return synchronizeTokenResourceLinks(normalized);
};

export const tokenResourcesWithMovement = (resources, range, remaining) => {
  const normalized = normalizeTokenResources(resources);
  const index = normalized.bars.findIndex(
    ({ movementSource }) => movementSource,
  );
  if (index < 0) return synchronizeTokenResourceLinks(normalized);
  const maximum = Math.max(0, finite(range, normalized.bars[index].max));
  normalized.bars[index].max = maximum;
  normalized.bars[index].value = Math.min(
    maximum,
    Math.max(0, finite(remaining, normalized.bars[index].value)),
  );
  return synchronizeTokenResourceLinks(normalized);
};

export const tokenMovementResourceState = (
  resources,
  fallbackRange = 6,
  fallbackSpent = 0,
) => {
  const normalized = normalizeTokenResources(resources);
  const index = normalized.bars.findIndex(
    ({ movementSource }) => movementSource,
  );
  if (index < 0) {
    const range = Math.max(0, finite(fallbackRange, 6));
    const spent = Math.max(0, finite(fallbackSpent));
    return { index, range, spent, remaining: Math.max(0, range - spent) };
  }
  const range = Math.max(0, finite(normalized.bars[index].max));
  const remaining = Math.min(
    range,
    Math.max(0, finite(normalized.bars[index].value)),
  );
  return { index, range, remaining, spent: range - remaining };
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

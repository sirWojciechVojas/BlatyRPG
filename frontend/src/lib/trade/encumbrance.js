export const BG_CARRY_LIMIT = 300;
export const BG_CARRY_UNIT_SHORT = "KP";
export const BG_CARRY_UNIT_NAME = "Kamienie Podroznika";
export const BG_CARRY_HIGH_RATIO = 0.7;
export const BG_CARRY_WARNING_RATIO = 0.9;

export const ENCUMBRANCE_STATUS = Object.freeze({
  UNKNOWN: "unknown",
  LIGHT: "light",
  HEAVY: "heavy",
  WARNING: "warning",
  OVERLOADED: "overloaded",
});

export const resolveItemQuantity = (item, fallback = 1) => {
  const quantity = Number(item?.QUANTITY);
  if (!Number.isFinite(quantity)) {
    return Math.max(0, Math.round(fallback));
  }
  return Math.max(0, Math.round(quantity));
};

export const resolveItemCharge = (
  item,
  templateItemsMap = {},
  fallback = 0,
) => {
  const directCharge = Number(item?.CHARGE);
  if (Number.isFinite(directCharge) && directCharge >= 0) {
    return directCharge;
  }
  const templateId = Number(item?.INV_ID ?? item?.ID);
  if (!Number.isFinite(templateId)) {
    return fallback;
  }
  const templateCharge = Number(templateItemsMap?.[templateId]?.CHARGE);
  if (Number.isFinite(templateCharge) && templateCharge >= 0) {
    return templateCharge;
  }
  return fallback;
};

export const calculateInventoryEncumbrance = (items, templateItemsMap = {}) =>
  (Array.isArray(items) ? items : []).reduce(
    (total, item) =>
      total +
      resolveItemCharge(item, templateItemsMap, 0) *
        resolveItemQuantity(item, 1),
    0,
  );

export const resolveEncumbranceStatus = (load, limit = BG_CARRY_LIMIT) => {
  if (!Number.isFinite(load) || !Number.isFinite(limit) || limit <= 0) {
    return ENCUMBRANCE_STATUS.UNKNOWN;
  }
  if (load > limit) {
    return ENCUMBRANCE_STATUS.OVERLOADED;
  }
  const ratio = load / limit;
  if (ratio >= BG_CARRY_WARNING_RATIO) {
    return ENCUMBRANCE_STATUS.WARNING;
  }
  if (ratio >= BG_CARRY_HIGH_RATIO) {
    return ENCUMBRANCE_STATUS.HEAVY;
  }
  return ENCUMBRANCE_STATUS.LIGHT;
};

export const bgEncumbranceStatusLabel = (status) =>
  ({
    [ENCUMBRANCE_STATUS.UNKNOWN]: "Brak danych",
    [ENCUMBRANCE_STATUS.LIGHT]: "Lekko",
    [ENCUMBRANCE_STATUS.HEAVY]: "Ciezko",
    [ENCUMBRANCE_STATUS.WARNING]: "Na granicy",
    [ENCUMBRANCE_STATUS.OVERLOADED]: "Przeciazony",
  })[status] || "Brak danych";

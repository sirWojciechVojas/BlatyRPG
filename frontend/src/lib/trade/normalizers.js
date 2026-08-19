import { OWNER_CODES, TRASH_KINDS } from "@/lib/trade/constants";

const toText = (value, fallback = "") => String(value ?? fallback);
const toNumber = (value, fallback = 0) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};
const toPositiveInt = (value, fallback = NaN) => {
  const parsed = Math.round(Number(value));
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
};
const toNonNegativeNumber = (value, fallback = 0) =>
  Math.max(0, toNumber(value, fallback));
const toQuantity = (value, fallback = 1) =>
  Math.max(0, Math.round(toNumber(value, fallback)));

export const normalizeOwnerCode = (
  value,
  fallback = OWNER_CODES.DEFAULT,
  { allowDefault = true } = {},
) => {
  const normalized = toText(value).trim().toUpperCase();
  if (
    normalized &&
    normalized !== OWNER_CODES.DEFAULT &&
    normalized !== OWNER_CODES.TRASH &&
    /^[A-Z0-9][A-Z0-9_-]{0,31}$/.test(normalized)
  ) {
    return normalized;
  }
  if (allowDefault && normalized === OWNER_CODES.DEFAULT) {
    return OWNER_CODES.DEFAULT;
  }
  if (normalized === OWNER_CODES.TRASH) {
    return OWNER_CODES.TRASH;
  }
  return fallback;
};

export const normalizeTrashKind = (value) => {
  const normalized = toText(value).trim().toUpperCase();
  return normalized === TRASH_KINDS.TEMPLATE
    ? TRASH_KINDS.TEMPLATE
    : TRASH_KINDS.ITEM;
};

export const normalizeTemplateRecord = (input = {}) => {
  const itemClass = toText(input.ITEM_CLASS ?? input.itemClass)
    .trim()
    .toUpperCase();
  return {
    id: toPositiveInt(input.ID ?? input.id),
    name: toText(input.NAME ?? input.name).trim(),
    description: toText(input.DESCRIPTION ?? input.description).trim(),
    details: toText(input.DETAILS ?? input.details).trim(),
    itemClass,
    itemId: itemClass === "WEAPON" ? toText(input.ITEM_ID ?? input.itemId) : "",
    itemGenre: toText(input.ITEM_GENRE ?? input.itemGenre)
      .trim()
      .toUpperCase(),
    imgClass: toText(input.IMG_CLASS ?? input.imgClass, "v0001")
      .trim()
      .toLowerCase(),
    prize: toNonNegativeNumber(input.PRIZE ?? input.prize, 0),
    charge: toNonNegativeNumber(input.CHARGE ?? input.charge, 0),
  };
};

export const normalizeInventoryRecord = (input = {}) => {
  const ownerOpt = toText(
    input.OWNER_OPT ?? input.ownerOpt,
    OWNER_CODES.DEFAULT,
  )
    .trim()
    .toUpperCase();
  const ownerFallback =
    ownerOpt && ownerOpt !== OWNER_CODES.TRASH ? ownerOpt : OWNER_CODES.TRASH;
  const ownerCode =
    ownerOpt === OWNER_CODES.DEFAULT
      ? OWNER_CODES.DEFAULT
      : normalizeOwnerCode(input.OWNER ?? input.owner, ownerFallback);
  const itemPlace = toText(
    input.ITEM_PLACE ?? input.itemPlace ?? input.SLOT ?? input.slot,
    ownerOpt === OWNER_CODES.DEFAULT ? "DEFAULT" : "STOS",
  ).trim();
  return {
    id: toPositiveInt(input.ID ?? input.id),
    templateId: toPositiveInt(
      input.INV_ID ?? input.invId ?? input.templateId,
      0,
    ),
    itemPlace,
    // Compatibility: keep `slot` until all legacy callers are migrated.
    slot: itemPlace,
    personalPseu: toText(input.PERSONAL_PSEU ?? input.personalPseu).trim(),
    personalDesc: toText(input.PERSONAL_DESC ?? input.personalDesc).trim(),
    personalCost: toNonNegativeNumber(
      input.PERSONAL_COST ?? input.personalCost ?? input.PRIZE ?? input.prize,
      0,
    ),
    quantity: toQuantity(input.QUANTITY ?? input.quantity, 1),
    ownerOpt: ownerOpt || OWNER_CODES.DEFAULT,
    ownerCode,
    name: toText(input.NAME ?? input.name).trim(),
    description: toText(input.DESCRIPTION ?? input.description).trim(),
    imgClass: toText(input.IMG_CLASS ?? input.imgClass, "v0001")
      .trim()
      .toLowerCase(),
    prize: toNonNegativeNumber(input.PRIZE ?? input.prize, 0),
    charge: toNonNegativeNumber(input.CHARGE ?? input.charge, 0),
  };
};

export const normalizeTrashRecord = (input = {}) => {
  const inventory = normalizeInventoryRecord(input);
  const ownerCode = normalizeOwnerCode(
    input.OWNER ?? input.owner,
    OWNER_CODES.TRASH,
    { allowDefault: false },
  );
  return {
    ...inventory,
    ownerCode,
    ownerOpt: OWNER_CODES.TRASH,
    trashKind: normalizeTrashKind(input.TRASH_KIND ?? input.trashKind),
    trashSourceId: toPositiveInt(
      input.TRASH_SOURCE_ID ?? input.trashSourceId,
      null,
    ),
  };
};

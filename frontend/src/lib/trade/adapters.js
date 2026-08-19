import { OWNER_CODES } from "@/lib/trade/constants";
import {
  normalizeInventoryRecord,
  normalizeTemplateRecord,
  normalizeTrashRecord,
} from "@/lib/trade/normalizers";

export const toLegacyTemplateRecord = (record = {}, source = {}) => ({
  ...source,
  ...record,
  ID: Number.isFinite(record.id) ? record.id : Number(source.ID || 0),
  NAME: record.name ?? source.NAME ?? "",
  DESCRIPTION: record.description ?? source.DESCRIPTION ?? "",
  DETAILS: record.details ?? source.DETAILS ?? "",
  ITEM_CLASS: record.itemClass ?? source.ITEM_CLASS ?? "",
  ITEM_ID: record.itemId ?? source.ITEM_ID ?? "",
  ITEM_GENRE: record.itemGenre ?? source.ITEM_GENRE ?? "",
  IMG_CLASS: record.imgClass ?? source.IMG_CLASS ?? "v0001",
  PRIZE: Number.isFinite(record.prize)
    ? record.prize
    : Number(source.PRIZE || 0),
  CHARGE: Number.isFinite(record.charge)
    ? record.charge
    : Number(source.CHARGE || 0),
});

export const toLegacyInventoryRecord = (record = {}, source = {}) => ({
  ...source,
  ...record,
  ID: Number.isFinite(record.id) ? record.id : Number(source.ID || 0),
  INV_ID: Number.isFinite(record.templateId)
    ? record.templateId
    : Number(source.INV_ID || 0),
  ITEM_PLACE:
    record.itemPlace ??
    record.slot ??
    source.ITEM_PLACE ??
    source.SLOT ??
    "DEFAULT",
  SLOT:
    record.itemPlace ??
    record.slot ??
    source.ITEM_PLACE ??
    source.SLOT ??
    "DEFAULT",
  PERSONAL_PSEU: record.personalPseu ?? source.PERSONAL_PSEU ?? "",
  PERSONAL_DESC: record.personalDesc ?? source.PERSONAL_DESC ?? "",
  PERSONAL_COST: Number.isFinite(record.personalCost)
    ? record.personalCost
    : Number(source.PERSONAL_COST || 0),
  QUANTITY: Number.isFinite(record.quantity)
    ? record.quantity
    : Number(source.QUANTITY || 0),
  OWNER_OPT: record.ownerOpt ?? source.OWNER_OPT ?? OWNER_CODES.DEFAULT,
  OWNER:
    source.OWNER ??
    record.ownerCode ??
    source.OWNER_OPT ??
    record.ownerOpt ??
    OWNER_CODES.DEFAULT,
  NAME: record.name ?? source.NAME ?? "",
  DESCRIPTION: record.description ?? source.DESCRIPTION ?? "",
  IMG_CLASS: record.imgClass ?? source.IMG_CLASS ?? "v0001",
  PRIZE: Number.isFinite(record.prize)
    ? record.prize
    : Number(source.PRIZE || 0),
  CHARGE: Number.isFinite(record.charge)
    ? record.charge
    : Number(source.CHARGE || 0),
});

export const toLegacyTrashRecord = (record = {}, source = {}) => ({
  ...toLegacyInventoryRecord(
    {
      ...record,
      ownerOpt: OWNER_CODES.TRASH,
    },
    source,
  ),
  OWNER_OPT: OWNER_CODES.TRASH,
  OWNER:
    record.ownerCode ?? source.OWNER_OPT ?? source.OWNER ?? OWNER_CODES.TRASH,
  TRASH_KIND: record.trashKind ?? source.TRASH_KIND ?? "ITEM",
  TRASH_SOURCE_ID:
    record.trashSourceId ??
    (Number.isFinite(Number(source.TRASH_SOURCE_ID))
      ? Number(source.TRASH_SOURCE_ID)
      : null),
});

export const normalizeLegacyTemplateRecord = (input = {}) =>
  toLegacyTemplateRecord(normalizeTemplateRecord(input), input);

export const normalizeLegacyInventoryRecord = (input = {}) =>
  toLegacyInventoryRecord(normalizeInventoryRecord(input), input);

export const normalizeLegacyTrashRecord = (input = {}) =>
  toLegacyTrashRecord(normalizeTrashRecord(input), input);

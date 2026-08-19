import { TRADE_PERSIST_SCHEMA_VERSION } from "@/lib/trade/constants";
import {
  normalizeLegacyInventoryRecord,
  normalizeLegacyTemplateRecord,
  normalizeLegacyTrashRecord,
} from "@/lib/trade/adapters";

const clone = (value) => JSON.parse(JSON.stringify(value));

const normalizeArray = (value, mapper) =>
  Array.isArray(value) ? value.map((entry) => mapper(clone(entry))) : [];

export const normalizePersistedTradePayload = (payload = {}) => {
  const source = payload && typeof payload === "object" ? payload : {};
  return {
    schemaVersion: TRADE_PERSIST_SCHEMA_VERSION,
    version: Number(source.version || 1),
    savedAt: source.savedAt || null,
    shops: Array.isArray(source.shops) ? source.shops.map(clone) : [],
    templateItems: normalizeArray(
      source.templateItems,
      normalizeLegacyTemplateRecord,
    ),
    inventoryItems: normalizeArray(
      source.inventoryItems,
      normalizeLegacyInventoryRecord,
    ),
    trashItems: normalizeArray(source.trashItems, normalizeLegacyTrashRecord),
    shopProfiles:
      source.shopProfiles && typeof source.shopProfiles === "object"
        ? clone(source.shopProfiles)
        : {},
    activeShopId:
      source.activeShopId === null || source.activeShopId === undefined
        ? null
        : Number(source.activeShopId),
  };
};

export const buildPersistedTradePayload = (payload = {}) => ({
  schemaVersion: TRADE_PERSIST_SCHEMA_VERSION,
  version: TRADE_PERSIST_SCHEMA_VERSION,
  savedAt: payload.savedAt || new Date().toISOString(),
  shops: Array.isArray(payload.shops) ? payload.shops.map(clone) : [],
  templateItems: normalizeArray(
    payload.templateItems,
    normalizeLegacyTemplateRecord,
  ),
  inventoryItems: normalizeArray(
    payload.inventoryItems,
    normalizeLegacyInventoryRecord,
  ),
  trashItems: normalizeArray(payload.trashItems, normalizeLegacyTrashRecord),
  shopProfiles:
    payload.shopProfiles && typeof payload.shopProfiles === "object"
      ? clone(payload.shopProfiles)
      : {},
  activeShopId:
    payload.activeShopId === null || payload.activeShopId === undefined
      ? null
      : Number(payload.activeShopId),
});

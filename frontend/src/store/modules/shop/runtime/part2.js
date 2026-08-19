import { OWNER_CODES } from "@/lib/trade/constants";
import {
  resolveDisplayedPrice,
  createDefaultShopPricingConfig,
} from "@/lib/trade/shopPriceCalculator";
import {
  SLOT_CODES,
  cloneItem,
  defaultShopkeeperLabel,
  findById,
  resolveItemPlace,
  toNonNegativeNumber,
} from "./part1";

export const isDefaultStackInventoryItem = (item) =>
  String(item?.OWNER_OPT || OWNER_CODES.DEFAULT).toUpperCase() ===
  OWNER_CODES.DEFAULT;

export const isNonTrashInventoryItem = (item) =>
  String(item?.OWNER_OPT || OWNER_CODES.DEFAULT).toUpperCase() !==
  OWNER_CODES.TRASH;

const tradeItemGroupingKey = (item = {}) =>
  JSON.stringify([
    Number(item.INV_ID ?? item.templateId ?? item.ID ?? 0),
    String(item.PERSONAL_PSEU || item.NAME || "").trim(),
    String(item.PERSONAL_DESC || item.DESCRIPTION || "").trim(),
    String(item.DETAILS || item.details || "").trim(),
    String(item.ITEM_CLASS || item.itemClass || ""),
    String(item.ITEM_GENRE || item.itemGenre || ""),
    String(item.IMG_CLASS || item.imgClass || ""),
    Number(
      item.PRICE_OVERRIDE ??
        item.PERSONAL_COST ??
        item.ACTIVE_PRICE ??
        item.PRIZE ??
        0,
    ),
    String(item.CURRENCY || item.currency || ""),
    Number(item.CHARGE || 0),
    [...(item.ATTRIBUTES || [])].map(String).sort(),
    item.WEAPON || {},
  ]);

export const aggregateTradeItems = (items = []) => {
  const groups = new Map();
  (Array.isArray(items) ? items : []).forEach((item) => {
    const key = tradeItemGroupingKey(item);
    const sourceIds = Array.isArray(item.AGGREGATED_ITEM_IDS)
      ? item.AGGREGATED_ITEM_IDS.map(Number).filter(Number.isFinite)
      : [Number(item.ID)].filter(Number.isFinite);
    const quantity =
      item.QUANTITY === null || item.QUANTITY === undefined
        ? null
        : Number(item.QUANTITY);
    const normalizedQuantity = Number.isFinite(quantity)
      ? Math.max(0, Math.round(quantity))
      : null;
    const isItemInstance = Object.prototype.hasOwnProperty.call(
      item,
      "INSTANCE_META",
    );

    if (!groups.has(key)) {
      groups.set(key, {
        ...item,
        AGGREGATED_ITEM_IDS: sourceIds,
        IS_ITEM_INSTANCE: isItemInstance,
        QUANTITY: normalizedQuantity,
      });
      return;
    }

    const group = groups.get(key);
    group.AGGREGATED_ITEM_IDS.push(...sourceIds);
    group.IS_ITEM_INSTANCE = group.IS_ITEM_INSTANCE && isItemInstance;
    group.QUANTITY =
      group.QUANTITY === null || normalizedQuantity === null
        ? null
        : group.QUANTITY + normalizedQuantity;
  });
  return Array.from(groups.values());
};

export const resolveTemplateId = (entry) => {
  if (typeof entry === "number" || typeof entry === "string") {
    const id = Number(entry);
    return Number.isFinite(id) ? id : null;
  }
  if (!entry || typeof entry !== "object") {
    return null;
  }
  const id = Number(entry.INV_ID ?? entry.ID);
  return Number.isFinite(id) ? id : null;
};

export const shopEntriesFor = (shop) => {
  if (Array.isArray(shop?.shopEntries)) {
    return shop.shopEntries;
  }
  if (Array.isArray(shop?.items)) {
    return shop.items;
  }
  if (Array.isArray(shop?.itemIds)) {
    return shop.itemIds;
  }
  return [];
};

export const sumBrass = (items, ids, quantities = {}) =>
  ids.reduce((total, id) => {
    const item = findById(items, id);
    const rawQty = Number(quantities?.[id]);
    const qty = Number.isFinite(rawQty) && rawQty > 0 ? rawQty : 1;
    return total + resolveDisplayedPrice(item) * qty;
  }, 0);

export const buildShopItems = (entries, templates = []) =>
  entries
    .map((entry) => {
      const templateId = resolveTemplateId(entry);
      if (!Number.isFinite(templateId)) {
        return null;
      }
      const template = findById(templates, templateId);
      if (!template) {
        return null;
      }
      if (!entry || typeof entry !== "object") {
        return cloneItem(template);
      }
      return {
        ...cloneItem(template),
        ...cloneItem(entry),
        ID: Number(entry.ID ?? template.ID),
        INV_ID: templateId,
        ITEM_PLACE: resolveItemPlace(entry, SLOT_CODES.STOISKO),
        SLOT: resolveItemPlace(entry, SLOT_CODES.STOISKO),
        PERSONAL_PSEU: entry.PERSONAL_PSEU || entry.NAME || template.NAME || "",
        PERSONAL_DESC: entry.PERSONAL_DESC || template.DESCRIPTION || "",
        PERSONAL_COST: toNonNegativeNumber(
          entry.PERSONAL_COST,
          Number(template.PRIZE || 0),
        ),
        QUANTITY: toNonNegativeNumber(entry.QUANTITY, 1),
        OWNER_OPT: entry.OWNER_OPT || "DEFAULT",
      };
    })
    .filter(Boolean);

export const buildShopEntryFromInventoryItem = (item, templates = []) => {
  const templateId = resolveTemplateId(item);
  const template = findById(templates, templateId) || {};
  return {
    INV_ID: templateId,
    ITEM_PLACE: resolveItemPlace(item, SLOT_CODES.STOISKO),
    SLOT: resolveItemPlace(item, SLOT_CODES.STOISKO),
    PERSONAL_PSEU:
      item.PERSONAL_PSEU ||
      item.NAME ||
      template.NAME ||
      defaultShopkeeperLabel(),
    PERSONAL_DESC: item.PERSONAL_DESC || item.DESCRIPTION || "",
    PERSONAL_COST: toNonNegativeNumber(
      item.PERSONAL_COST,
      Number(item.PRIZE ?? template.PRIZE ?? 0),
    ),
    QUANTITY: 1,
    OWNER_OPT: "DEFAULT",
  };
};

export const normalizeAssortment = (shop) => {
  if (!shop) {
    return;
  }
  if (!shop.assortment) {
    const initialIds = shopEntriesFor(shop)
      .map((entry) => resolveTemplateId(entry))
      .filter((id) => Number.isFinite(id));
    shop.assortment = {
      DEFAULT: initialIds,
      TRASH: [],
      BG1: [],
      BG2: [],
    };
  }
  const baseOwners = ["DEFAULT", "TRASH", "BG1", "BG2"];
  const owners = Array.from(
    new Set([...baseOwners, ...Object.keys(shop.assortment || {})]),
  );
  owners.forEach((owner) => {
    const list = Array.isArray(shop.assortment[owner])
      ? shop.assortment[owner]
      : [];
    shop.assortment[owner] = Array.from(
      new Set(list.map((id) => Number(id)).filter((id) => Number.isFinite(id))),
    );
  });
  const fallbackIds = shopEntriesFor(shop)
    .map((entry) => resolveTemplateId(entry))
    .filter((id) => Number.isFinite(id));
  if (!shop.assortment.DEFAULT?.length && fallbackIds.length) {
    shop.assortment.DEFAULT = Array.from(new Set(fallbackIds));
  }
  shop.itemIds = [...shop.assortment.DEFAULT];
};

export const ownerCodeFromShop = (shop = {}) => {
  if (shop?.ownerCode) {
    return String(shop.ownerCode);
  }
  const entry = shopEntriesFor(shop).find(
    (row) => row && typeof row === "object" && row.OWNER_OPT,
  );
  if (entry?.OWNER_OPT) {
    return String(entry.OWNER_OPT);
  }
  return "BG1";
};

export const isShopActive = (shop = {}) => shop?.isActive !== false;

export const defaultWorldProfileId = () => "standard";

export const createDefaultShopProfile = (shop = {}) => ({
  shopId: Number(shop.id),
  typeId: String(
    shop?.typeId ||
      shop?.type_id ||
      shop?.shopTypeId ||
      shop?.shopType ||
      shop?.type ||
      "",
  ).trim(),
  signboardName: String(shop?.name || ""),
  ownerCode: ownerCodeFromShop(shop),
  ownerName: String(shop?.ownerName || ""),
  signboardAltNames: [],
  categoryTags: [],
  worldProfileId: defaultWorldProfileId(),
  locationType: "miasto",
  legalStatus: "legal",
  wealthTier: "standard",
  reputation: "neutralna",
  seasonality: "caloroczny",
  counterfeitRisk: 10,
  pricingConfig: createDefaultShopPricingConfig(),
  marketSettings: {
    demandLevel: "normal",
    availabilityBias: 0,
    buybackBudget: null,
    maxBuybackItemValue: null,
    expensiveStockLimit: null,
    localCategories: [],
    importedCategories: [],
    reputationByActor: {},
  },
  marketEvents: [],
  customPresets: { profiles: [], policies: [] },
});

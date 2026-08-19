import { normalizeShopPricingConfig } from "@/lib/trade/shopPriceCalculator";
import {
  createDefaultShopProfile,
  defaultWorldProfileId,
  ownerCodeFromShop,
} from "./part2";

export const buildEditorStateFromProfile = (
  profile = {},
  shop = {},
  previous = {},
) => ({
  ...previous,
  typeId: profile?.typeId || "",
  signboardName: profile?.signboardName || shop?.name || "",
  ownerCode: profile?.ownerCode || ownerCodeFromShop(shop),
  ownerName: profile?.ownerName || shop?.ownerName || "",
  signboardAltNamesText: (profile?.signboardAltNames || []).join(", "),
  categoryTagsText: (profile?.categoryTags || []).join(", "),
  worldProfileId: profile?.worldProfileId || defaultWorldProfileId(),
  locationType: profile?.locationType || "miasto",
  legalStatus: profile?.legalStatus || "legal",
  wealthTier: profile?.wealthTier || "standard",
  reputation: profile?.reputation || "neutralna",
  seasonality: profile?.seasonality || "caloroczny",
  counterfeitRisk: Number(profile?.counterfeitRisk || 0),
  pricingConfig: normalizeShopPricingConfig(profile?.pricingConfig),
  marketSettings: profile?.marketSettings || {
    demandLevel: "normal",
    availabilityBias: 0,
    buybackBudget: null,
    maxBuybackItemValue: null,
    expensiveStockLimit: null,
    localCategories: [],
    importedCategories: [],
    reputationByActor: {},
  },
  marketEvents: Array.isArray(profile?.marketEvents)
    ? profile.marketEvents
    : [],
  customPresets: profile?.customPresets || { profiles: [], policies: [] },
  selectedSuggestionIds: [],
});

export const normalizeShopProfile = (profile = {}, shop = {}) => {
  const base = createDefaultShopProfile(shop);
  const altNames = Array.isArray(profile.signboardAltNames)
    ? profile.signboardAltNames
    : String(profile.signboardAltNames || "")
        .split(",")
        .map((entry) => entry.trim())
        .filter(Boolean);
  const categoryTags = Array.isArray(profile.categoryTags)
    ? profile.categoryTags
    : String(profile.categoryTags ?? profile.categoryTagsText ?? "")
        .split(",")
        .map((entry) => entry.trim())
        .filter(Boolean);
  return {
    ...base,
    ...profile,
    shopId: Number(profile.shopId ?? base.shopId),
    ownerCode: String(profile.ownerCode ?? base.ownerCode ?? "BG1"),
    ownerName: String(profile.ownerName ?? base.ownerName ?? ""),
    signboardAltNames: altNames,
    categoryTags,
    counterfeitRisk: Math.max(
      0,
      Math.min(100, Number(profile.counterfeitRisk ?? base.counterfeitRisk)),
    ),
    pricingConfig: normalizeShopPricingConfig(
      profile.pricingConfig ?? base.pricingConfig,
    ),
    marketSettings: {
      ...base.marketSettings,
      ...(profile.marketSettings || {}),
    },
    marketEvents: Array.isArray(profile.marketEvents)
      ? profile.marketEvents
      : [],
    customPresets: {
      profiles: Array.isArray(profile.customPresets?.profiles)
        ? profile.customPresets.profiles
        : [],
      policies: Array.isArray(profile.customPresets?.policies)
        ? profile.customPresets.policies
        : [],
    },
  };
};

export const pricingCollectionCache = new WeakMap();

export const pricedTradeCollection = ({ state, items, mode }) => {
  const list = Array.isArray(items) ? items : [];
  const cacheKey = [
    mode,
    Number(state.activeShopId) || "none",
    state.tradeDataCacheVersion || 0,
    list.length,
    state.templateItems.length,
  ].join("|");
  const collectionCache = pricingCollectionCache.get(list);
  const cached = collectionCache?.get(cacheKey);
  if (cached) {
    return cached;
  }
  // ACTIVE_PRICE and PRICE_BREAKDOWN are produced by the backend pricing engine.
  const priced = list;
  const nextCache = collectionCache || new Map();
  if (nextCache.size > 4) {
    nextCache.clear();
  }
  nextCache.set(cacheKey, priced);
  if (!collectionCache) {
    pricingCollectionCache.set(list, nextCache);
  }
  return priced;
};

export const nextTemplateId = (templates = []) => {
  const ids = templates
    .map((item) => Number(item.ID))
    .filter((id) => Number.isFinite(id));
  return ids.length ? Math.max(...ids) + 1 : 1;
};

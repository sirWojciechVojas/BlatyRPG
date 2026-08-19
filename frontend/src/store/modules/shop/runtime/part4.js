import {
  SLOT_CODES,
  defaultSuggestionLabel,
  findById,
  isDefaultPersonalPseu,
  toNonNegativeNumber,
} from "./part1";

export const nextShopId = (shops = []) => {
  const ids = shops
    .map((shop) => Number(shop?.id))
    .filter((id) => Number.isFinite(id));
  return ids.length ? Math.max(...ids) + 1 : 1;
};

export const clampInteger = (value, min, max) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) {
    return min;
  }
  return Math.max(min, Math.min(max, Math.round(parsed)));
};

export const suggestionVariants = (suggestion) =>
  Array.isArray(suggestion?.personalizedVariants)
    ? suggestion.personalizedVariants.filter(Boolean)
    : [];

export const resolveSuggestionVariant = (suggestion, variantId = null) => {
  const variants = suggestionVariants(suggestion);
  if (!variants.length) {
    return null;
  }
  const requestedId = String(variantId || suggestion?.variantId || "");
  if (requestedId) {
    return (
      variants.find((entry) => String(entry?.variantId) === requestedId) ||
      variants[0]
    );
  }
  return variants[0];
};

export const expandSuggestionForApply = (suggestion, options = {}) => {
  if (!suggestion || typeof suggestion !== "object") {
    return [];
  }
  const explicitVariantId = String(
    options?.variantId ||
      suggestion?.forceVariantId ||
      suggestion?.variantId ||
      "",
  );
  const variants = suggestionVariants(suggestion);
  const quantity = Math.max(1, Number(suggestion?.quantity || 1));

  if (!variants.length) {
    return [{ ...suggestion }];
  }

  if (
    explicitVariantId ||
    options?.forceSingle ||
    suggestion?.forceSingleVariant
  ) {
    const single = resolveSuggestionVariant(suggestion, explicitVariantId);
    return single
      ? [{ ...suggestion, quantity: 1, selectedVariant: single }]
      : [];
  }

  return Array.from({ length: quantity }, (_, index) => ({
    ...suggestion,
    quantity: 1,
    selectedVariant: variants[index % variants.length],
  }));
};

export const isPersonalizedShopEntry = (entry, template = {}) => {
  if (!entry || typeof entry !== "object") {
    return false;
  }
  const templateDesc = String(template?.DESCRIPTION || "").trim();
  const templatePrice = Number(template?.PRIZE || 0);
  const entryDesc = String(entry?.PERSONAL_DESC || "").trim();
  const entryPseu = String(entry?.PERSONAL_PSEU || "").trim();
  const entryPrice = Number(entry?.PERSONAL_COST);
  return (
    (entryPseu && !isDefaultPersonalPseu(entryPseu)) ||
    (entryDesc && templateDesc && entryDesc !== templateDesc) ||
    (Number.isFinite(entryPrice) && entryPrice !== templatePrice)
  );
};

export const buildShopEntryFromSuggestion = (
  state,
  suggestion,
  templateId,
  variant = null,
) => {
  const template = findById(state.templateItems, templateId) || {};
  const selectedVariant = variant || suggestion?.selectedVariant || null;
  if (selectedVariant) {
    return {
      INV_ID: templateId,
      ITEM_PLACE: SLOT_CODES.STOISKO,
      SLOT: SLOT_CODES.STOISKO,
      PERSONAL_PSEU: String(
        selectedVariant.personalPseu || defaultSuggestionLabel(),
      ),
      PERSONAL_DESC: String(
        selectedVariant.personalDesc || template.DESCRIPTION || "",
      ),
      PERSONAL_COST: toNonNegativeNumber(
        selectedVariant.personalCost,
        Number(template.PRIZE || 0),
      ),
      QUANTITY: Math.max(1, Number(selectedVariant.quantity || 1)),
      OWNER_OPT: "DEFAULT",
    };
  }
  return {
    INV_ID: templateId,
    ITEM_PLACE: SLOT_CODES.STOISKO,
    SLOT: SLOT_CODES.STOISKO,
    PERSONAL_PSEU: defaultSuggestionLabel(),
    PERSONAL_DESC: template.DESCRIPTION || "",
    PERSONAL_COST: Number(template.PRIZE || 0),
    QUANTITY: Math.max(1, Number(suggestion?.quantity || 1)),
    OWNER_OPT: "DEFAULT",
  };
};

export const weightedPickWithoutReplacement = (items = [], count = 1) => {
  const pool = [...items];
  const picks = [];
  const target = clampInteger(count, 1, Math.max(1, pool.length));
  while (pool.length && picks.length < target) {
    const weights = pool.map((entry) => Math.max(1, Number(entry?.score || 1)));
    const total = weights.reduce((acc, weight) => acc + weight, 0);
    let cursor = Math.random() * total;
    let index = 0;
    for (let i = 0; i < weights.length; i += 1) {
      cursor -= weights[i];
      if (cursor <= 0) {
        index = i;
        break;
      }
    }
    picks.push(pool[index]);
    pool.splice(index, 1);
  }
  return picks;
};

export const suggestionSortLabel = (entry) =>
  String(
    entry?.label ||
      entry?.displayName ||
      entry?.templateName ||
      entry?.draftTemplate?.NAME ||
      "",
  ).trim();

export const suggestionSortHash = (seed) => {
  let hash = 2166136261;
  const text = String(seed || "");
  for (let i = 0; i < text.length; i += 1) {
    hash ^= text.charCodeAt(i);
    hash = Math.imul(hash, 16777619);
  }
  return hash >>> 0;
};

export const defaultSuggestionTieBreaker = (entry) =>
  ((suggestionSortHash(
    `${String(entry?.suggestionId || "")}:${suggestionSortLabel(entry)}`,
  ) %
    97) +
    1) /
  100;

export const normalizeSuggestionEntryForSort = (entry) => {
  const source = entry && typeof entry === "object" ? entry : {};
  const rawScoreSource = Number(
    Number.isFinite(Number(source.scoreRaw)) ? source.scoreRaw : source.score,
  );
  const scoreRaw = Number.isFinite(rawScoreSource) ? rawScoreSource : 0;
  const scoreTieBreaker = Number(
    Number.isFinite(Number(source.scoreTieBreaker))
      ? source.scoreTieBreaker
      : defaultSuggestionTieBreaker(source),
  );
  const score = Number.isFinite(Number(source.score))
    ? Number(source.score)
    : Number((scoreRaw + scoreTieBreaker).toFixed(2));
  return {
    ...source,
    scoreRaw: Number((scoreRaw || 0).toFixed(4)),
    score: Number(score.toFixed(2)),
    scoreTieBreaker: Number(scoreTieBreaker.toFixed(4)),
  };
};

export const suggestionSortComparator = (left, right) =>
  Number(right?.score || 0) - Number(left?.score || 0) ||
  Number(right?.scoreTieBreaker || 0) - Number(left?.scoreTieBreaker || 0) ||
  suggestionSortLabel(left).localeCompare(suggestionSortLabel(right), "pl");

export const normalizeSuggestionCollection = (entries = []) =>
  (Array.isArray(entries) ? entries : [])
    .map((entry) => normalizeSuggestionEntryForSort(entry))
    .sort(suggestionSortComparator);

export const suggestionByIdMap = (entries = []) =>
  (Array.isArray(entries) ? entries : []).reduce((acc, entry) => {
    const suggestionId = String(entry?.suggestionId || "");
    if (suggestionId) {
      acc[suggestionId] = entry;
    }
    return acc;
  }, {});

export const findSuggestionInCollections = (state, suggestionId) => {
  const id = String(suggestionId || "");
  if (!id) {
    return null;
  }
  const inSuggestions = (state.shopSuggestions || []).find(
    (entry) => String(entry?.suggestionId || "") === id,
  );
  if (inSuggestions) {
    return inSuggestions;
  }
  const inRecommendations = (state.shopTemplateRecommendations || []).find(
    (entry) => String(entry?.suggestionId || "") === id,
  );
  return inRecommendations || null;
};

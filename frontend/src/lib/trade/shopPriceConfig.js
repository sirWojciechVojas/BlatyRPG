const clamp = (value, min, max, fallback = min) => {
  const parsed = Number(value);
  return Math.max(
    min,
    Math.min(max, Number.isFinite(parsed) ? parsed : fallback),
  );
};

export const PRICE_MODIFIER_KEYS = Object.freeze([
  "worldProfile",
  "shopType",
  "location",
  "availability",
  "demand",
  "condition",
  "seasonality",
  "marketEvents",
  "wealth",
  "legality",
  "counterfeitRisk",
  "reputation",
]);

export const SHOP_PRICING_POLICY_IDS = Object.freeze([
  "balanced",
  "friendly",
  "premium",
  "unrestricted",
  "custom",
]);

const defaultGuardrails = () => ({
  enabled: true,
  buyMinMultiplier: 0.25,
  buyMaxMultiplier: 4,
  sellMinMultiplier: 0.05,
  sellMaxMultiplier: 0.95,
  maxBuybackRatio: 0.9,
  maxTemporaryPercent: 100,
  minimumAvailabilityChance: 0,
  maximumAvailabilityChance: 100,
});

export const createDefaultShopPricingConfig = () => ({
  version: 4,
  policyId: null,
  baseMultipliers: { buy: 1, sell: 0.6 },
  priceBands: { cheapMax: 50, midMax: 200, highMax: 800 },
  currencyPolicy: {
    settlementCurrencyCode: "",
    exchangeRates: {},
    buyFeePercent: 0,
    sellFeePercent: 0,
    paymentExchangeFeePercent: 5,
  },
  minimumPrice: 1,
  roundingStep: 1,
  roundingMode: "nearest",
  guardrails: defaultGuardrails(),
  enabledModifiers: Object.fromEntries(
    PRICE_MODIFIER_KEYS.map((key) => [key, true]),
  ),
  rules: [],
});

const normalizeStringList = (value, allowed = null, uppercase = false) => {
  const source = Array.isArray(value) ? value : value ? [value] : [];
  const normalized = source
    .map((entry) => String(entry || "").trim())
    .filter(Boolean)
    .map((entry) => (uppercase ? entry.toUpperCase() : entry.toLowerCase()));
  const unique = [...new Set(normalized)];
  return allowed ? unique.filter((entry) => allowed.includes(entry)) : unique;
};

const normalizeRule = (rule = {}, index = 0) => {
  const match = rule?.match && typeof rule.match === "object" ? rule.match : {};
  const effect =
    rule?.effect && typeof rule.effect === "object" ? rule.effect : {};
  const type = ["multiplier", "additive", "fixed"].includes(effect.type)
    ? effect.type
    : "multiplier";
  const id =
    String(rule.id || "")
      .trim()
      .replace(/[^a-zA-Z0-9_-]/g, "-")
      .slice(0, 80) || `pricing-rule-${index + 1}`;
  return {
    id,
    name: String(rule.name || "")
      .trim()
      .slice(0, 120),
    enabled: rule.enabled !== false,
    priority: Math.round(clamp(rule.priority, -1000, 1000, 0)),
    match: {
      modes: normalizeStringList(match.modes ?? match.mode, ["buy", "sell"])
        .length
        ? normalizeStringList(match.modes ?? match.mode, ["buy", "sell"])
        : ["buy", "sell"],
      templateIds: [...new Set((match.templateIds || []).map(Number))]
        .filter((idValue) => Number.isFinite(idValue) && idValue > 0)
        .slice(0, 100),
      itemClasses: normalizeStringList(match.itemClasses, null, true).slice(
        0,
        50,
      ),
      itemGenres: normalizeStringList(match.itemGenres, null, true).slice(
        0,
        50,
      ),
      currencyCodes: normalizeStringList(match.currencyCodes).slice(0, 50),
      priceTiers: normalizeStringList(match.priceTiers, [
        "cheap",
        "mid",
        "high",
        "luxury",
      ]),
      legalities: normalizeStringList(match.legalities, [
        "legal",
        "grey",
        "illegal",
      ]),
      availabilityBands: normalizeStringList(match.availabilityBands, [
        "none",
        "scarce",
        "low",
        "medium",
        "high",
      ]),
    },
    effect: {
      type,
      value:
        type === "multiplier"
          ? clamp(effect.value, 0, 10, 1)
          : clamp(effect.value, -1000000, 1000000, 0),
      disabledModifiers: normalizeStringList(effect.disabledModifiers)
        .map((key) =>
          PRICE_MODIFIER_KEYS.find(
            (candidate) => candidate.toLowerCase() === key,
          ),
        )
        .filter(Boolean),
      stopProcessing: effect.stopProcessing === true,
      ignoreGuardrails: effect.ignoreGuardrails === true,
    },
  };
};

export const createShopPricingRule = (input = {}) =>
  normalizeRule(
    {
      id: input.id || `pricing-rule-${Date.now()}`,
      name: input.name || "",
      enabled: input.enabled ?? true,
      priority: input.priority ?? 0,
      match: input.match || { modes: ["buy", "sell"] },
      effect: input.effect || { type: "multiplier", value: 1 },
    },
    0,
  );

export const normalizeShopPricingConfig = (input = {}) => {
  const base = input?.baseMultipliers || {};
  const bands = input?.priceBands || {};
  const currency = input?.currencyPolicy || {};
  const guards = input?.guardrails || {};
  const cheapMax = clamp(bands.cheapMax, 0, 100000000, 50);
  const midMax = Math.max(cheapMax + 1, clamp(bands.midMax, 0, 100000000, 200));
  const highMax = Math.max(midMax + 1, clamp(bands.highMax, 0, 100000000, 800));
  const exchangeRates = Object.fromEntries(
    Object.entries(currency.exchangeRates || {})
      .map(([code, rate]) => [
        String(code).trim().toLowerCase().slice(0, 64),
        clamp(rate, 0.000001, 1000000, 1),
      ])
      .filter(([code]) => code),
  );
  const minimumAvailabilityChance = clamp(
    guards.minimumAvailabilityChance,
    0,
    100,
    0,
  );
  const rules = (Array.isArray(input.rules) ? input.rules : [])
    .slice(0, 100)
    .map(normalizeRule);
  return {
    version: Math.max(4, Math.round(clamp(input.version, 4, 999, 4))),
    policyId: SHOP_PRICING_POLICY_IDS.includes(input.policyId)
      ? input.policyId
      : null,
    baseMultipliers: {
      buy: clamp(base.buy, 0, 10, 1),
      sell: clamp(base.sell, 0, 10, 0.6),
    },
    priceBands: { cheapMax, midMax, highMax },
    currencyPolicy: {
      settlementCurrencyCode: String(currency.settlementCurrencyCode || "")
        .trim()
        .toLowerCase()
        .slice(0, 64),
      exchangeRates,
      buyFeePercent: clamp(currency.buyFeePercent, 0, 100, 0),
      sellFeePercent: clamp(currency.sellFeePercent, 0, 100, 0),
      paymentExchangeFeePercent: clamp(
        currency.paymentExchangeFeePercent,
        0,
        100,
        5,
      ),
    },
    minimumPrice: Math.round(clamp(input.minimumPrice, 0, 100000000, 1)),
    roundingStep: Math.round(clamp(input.roundingStep, 1, 1000000, 1)),
    roundingMode: ["nearest", "up", "down"].includes(input.roundingMode)
      ? input.roundingMode
      : "nearest",
    guardrails: {
      enabled: guards.enabled !== false,
      buyMinMultiplier: clamp(guards.buyMinMultiplier, 0, 10, 0.25),
      buyMaxMultiplier: clamp(guards.buyMaxMultiplier, 0, 10, 4),
      sellMinMultiplier: clamp(guards.sellMinMultiplier, 0, 10, 0.05),
      sellMaxMultiplier: clamp(guards.sellMaxMultiplier, 0, 10, 0.95),
      maxBuybackRatio: clamp(guards.maxBuybackRatio, 0, 1, 0.9),
      maxTemporaryPercent: clamp(guards.maxTemporaryPercent, 0, 500, 100),
      minimumAvailabilityChance,
      maximumAvailabilityChance: clamp(
        guards.maximumAvailabilityChance,
        minimumAvailabilityChance,
        100,
        100,
      ),
    },
    enabledModifiers: Object.fromEntries(
      PRICE_MODIFIER_KEYS.map((key) => [
        key,
        input?.enabledModifiers?.[key] !== false,
      ]),
    ),
    rules,
  };
};

const preset = (id) => {
  const config = createDefaultShopPricingConfig();
  if (id === "friendly")
    Object.assign(config.baseMultipliers, { buy: 0.9, sell: 0.68 });
  if (id === "premium")
    Object.assign(config.baseMultipliers, { buy: 1.2, sell: 0.5 });
  if (id === "unrestricted") config.guardrails.enabled = false;
  if (id === "friendly")
    Object.assign(config.guardrails, {
      buyMinMultiplier: 0.2,
      buyMaxMultiplier: 3,
      sellMaxMultiplier: 0.9,
      maxBuybackRatio: 0.92,
    });
  if (id === "premium")
    Object.assign(config.guardrails, {
      buyMinMultiplier: 0.4,
      buyMaxMultiplier: 5,
      sellMaxMultiplier: 0.8,
      maxBuybackRatio: 0.82,
    });
  config.policyId = id === "general" ? null : id;
  return config;
};

export const applyShopPricingPreset = (id, current = {}) => {
  const next = preset(id);
  next.currencyPolicy = normalizeShopPricingConfig(current).currencyPolicy;
  return next;
};

export const resolveDisplayedPrice = (item = {}) =>
  Math.max(
    0,
    Number(
      item?.ACTIVE_PRICE ??
        item?.activePrice ??
        item?.PERSONAL_COST ??
        item?.PRIZE ??
        item?.price ??
        0,
    ) || 0,
  );

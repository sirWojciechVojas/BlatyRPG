import { computed, toRefs } from "vue";
import i18n from "@/i18n";
import {
  GENERIC_CURRENCY_DEFINITION,
  resolveCurrencyDefinition,
} from "@/lib/trade/currency";
import { normalizeShopPricingConfig } from "@/lib/trade/shopPriceCalculator";
import { createPricingFormatters } from "./pricingFormatters";

export const createPricingSectionContext = (props, emit) => {
  const t = (key) => i18n.global.t(key);
  const pricingConfig = computed(() =>
    normalizeShopPricingConfig(props.form?.pricingConfig),
  );
  const presetOptions = computed(() =>
    ["general", "balanced", "friendly", "premium", "unrestricted"].map(
      (id) => ({
        id,
        label: t(`shop.shopEditor.pricing.presets.${id}.label`),
        description: t(`shop.shopEditor.pricing.presets.${id}.description`),
      }),
    ),
  );
  const activePolicyId = computed(
    () => pricingConfig.value.policyId || "general",
  );
  const activePolicyOption = computed(
    () =>
      presetOptions.value.find(
        (option) => option.id === activePolicyId.value,
      ) || {
        id: "custom",
        label: t("shop.shopEditor.pricing.presets.custom.label"),
        description: t("shop.shopEditor.pricing.presets.custom.description"),
      },
  );
  const guardrailFields = computed(() =>
    [
      "buyMinMultiplier",
      "buyMaxMultiplier",
      "sellMinMultiplier",
      "sellMaxMultiplier",
      "maxBuybackRatio",
    ].map((key) => ({
      path: `guardrails.${key}`,
      label: t(`shop.shopEditor.pricing.guardrails.fields.${key}`),
      hint: t(`shop.shopEditor.pricing.guardrails.hints.${key}`),
    })),
  );
  const guardrailDirectFields = computed(() =>
    [
      "maxTemporaryPercent",
      "minimumAvailabilityChance",
      "maximumAvailabilityChance",
    ].map((key) => ({
      path: `guardrails.${key}`,
      label: t(`shop.shopEditor.pricing.guardrails.fields.${key}`),
      hint: t(`shop.shopEditor.pricing.guardrails.hints.${key}`),
    })),
  );
  const priceBandFields = computed(() =>
    ["cheapMax", "midMax", "highMax"].map((key) => ({
      path: `priceBands.${key}`,
      label: t(`shop.shopEditor.pricing.basics.priceBands.${key}`),
      hint: t(`shop.shopEditor.pricing.basics.priceBandHints.${key}`),
    })),
  );
  const modeOptions = computed(() => [
    {
      id: "buy",
      label: t("shop.shopEditor.pricing.exceptions.modes.buy"),
    },
    {
      id: "sell",
      label: t("shop.shopEditor.pricing.exceptions.modes.sell"),
    },
  ]);
  const anyLabel = computed(() => t("shop.shopEditor.pricing.exceptions.any"));
  const optionList = (group, values) =>
    values.map((value) => ({
      value,
      label: t(`shop.shopEditor.pricing.exceptions.${group}.${value}`),
    }));
  const priceTierOptions = computed(() =>
    optionList("priceTiers", ["cheap", "mid", "high", "luxury"]),
  );
  const legalityOptions = computed(() =>
    optionList("legalities", ["legal", "grey", "illegal"]),
  );
  const availabilityOptions = computed(() =>
    optionList("availabilityBands", [
      "none",
      "scarce",
      "low",
      "medium",
      "high",
    ]),
  );
  const currencyList = computed(() =>
    Array.isArray(props.currencyDefinitions?.currencies)
      ? props.currencyDefinitions.currencies
      : [GENERIC_CURRENCY_DEFINITION],
  );
  const previewCurrencyCode = computed(
    () =>
      props.pricePreview?.items?.[0]?.after?.settlementCurrencyCode ||
      pricingConfig.value.currencyPolicy.settlementCurrencyCode ||
      props.currencyDefinitions?.defaultCurrencyCode ||
      "generic",
  );
  const previewCurrencyDefinition = computed(() =>
    resolveCurrencyDefinition(currencyList.value, previewCurrencyCode.value),
  );
  const settlementCurrencyLabel = computed(
    () =>
      props.currencyOptions.find(
        (option) =>
          option.value ===
          pricingConfig.value.currencyPolicy.settlementCurrencyCode,
      )?.label || pricingConfig.value.currencyPolicy.settlementCurrencyCode,
  );
  const pricingFormatters = createPricingFormatters({
    previewCurrencyDefinition,
  });
  const emitPolicyField = (path, value) =>
    emit("update-policy-field", {
      path,
      value,
    });
  const updateNumberField = (path, event, minimum = 0) => {
    const value = Number(event?.target?.value);
    emitPolicyField(
      path,
      Number.isFinite(value) ? Math.max(minimum, value) : minimum,
    );
  };
  const updatePercentField = (path, event) => {
    const value = Number(event?.target?.value);
    emitPolicyField(
      path,
      Number.isFinite(value) ? Math.max(0, value) / 100 : 0,
    );
  };
  const toPercent = (value) => Number((Number(value || 0) * 100).toFixed(2));
  const valueAt = (path) =>
    String(path)
      .split(".")
      .reduce((value, segment) => value?.[segment], pricingConfig.value);
  const exchangeRateFor = (currencyCode) =>
    currencyCode === pricingConfig.value.currencyPolicy.settlementCurrencyCode
      ? 1
      : Number(
          pricingConfig.value.currencyPolicy.exchangeRates?.[currencyCode] ?? 1,
        );
  const hasExchangeRate = (currencyCode) =>
    currencyCode ===
      pricingConfig.value.currencyPolicy.settlementCurrencyCode ||
    Object.prototype.hasOwnProperty.call(
      pricingConfig.value.currencyPolicy.exchangeRates || {},
      currencyCode,
    );
  const toggleExchangeRate = (currencyCode, enabled) => {
    emitPolicyField(
      `currencyPolicy.exchangeRates.${currencyCode}`,
      enabled ? exchangeRateFor(currencyCode) || 1 : null,
    );
  };
  const updateSettlementCurrency = (currencyCode) => {
    const normalized = String(currencyCode || "").toLowerCase();
    if (!normalized) {
      return;
    }
    emitPolicyField("currencyPolicy.settlementCurrencyCode", normalized);
    props.currencyOptions.forEach((option) => {
      const sourceCode = String(option?.value || "").toLowerCase();
      if (!sourceCode || sourceCode === normalized) {
        return;
      }
      const currentRate = Number(
        pricingConfig.value.currencyPolicy.exchangeRates?.[sourceCode],
      );
      if (!Number.isFinite(currentRate) || currentRate <= 0) {
        emitPolicyField(`currencyPolicy.exchangeRates.${sourceCode}`, 1);
      }
    });
  };
  const updateExchangeRate = (currencyCode, rawValue) => {
    const parsed = Number(rawValue);
    emitPolicyField(
      `currencyPolicy.exchangeRates.${currencyCode}`,
      Number.isFinite(parsed) && parsed > 0 ? parsed : 1,
    );
  };
  const firstValue = (values) => (Array.isArray(values) ? values[0] || "" : "");
  const stringList = (value) => (String(value || "").trim() ? [value] : []);
  const numberList = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? [parsed] : [];
  };
  const updateRule = (rule, patch) =>
    emit("update-rule", {
      id: rule.id,
      patch,
    });
  const updateRuleMatch = (rule, key, value) =>
    updateRule(rule, {
      match: {
        [key]: value,
      },
    });
  const updateRuleEffect = (rule, patch) =>
    updateRule(rule, {
      effect: patch,
    });
  const toggleRuleMode = (rule, mode, enabled) => {
    const current = new Set(rule.match.modes || []);
    if (enabled) {
      current.add(mode);
    } else if (current.size > 1) {
      current.delete(mode);
    }
    updateRuleMatch(rule, "modes", Array.from(current));
  };
  const defaultEffectValue = (type) => (type === "multiplier" ? 1 : 0);
  const displayEffectValue = (effect) =>
    effect?.type === "multiplier"
      ? toPercent(effect?.value)
      : Number(effect?.value || 0);
  const updateRuleEffectValue = (rule, rawValue) => {
    const parsed = Number(rawValue);
    const value = Number.isFinite(parsed) ? parsed : 0;
    updateRuleEffect(rule, {
      value:
        rule.effect.type === "multiplier" ? Math.max(0, value) / 100 : value,
    });
  };
  const effectValueLabel = (type) =>
    t(`shop.shopEditor.pricing.exceptions.effectValues.${type}`);
  const toggleDisabledModifier = (rule, modifierKey, disabled) => {
    const values = new Set(rule.effect.disabledModifiers || []);
    if (disabled) {
      values.add(modifierKey);
    } else {
      values.delete(modifierKey);
    }
    updateRuleEffect(rule, {
      disabledModifiers: Array.from(values),
    });
  };
  const isModifierEnabled = (modifierKey) =>
    props.form?.pricingConfig?.enabledModifiers?.[modifierKey] !== false;
  const previewOptionLabel = (option) =>
    option?.stocked
      ? `${option.label} (${t("shop.shopEditor.pricing.stockedInShop")})`
      : option?.label || "";
  const reasonLabel = (row = {}) => {
    const code = String(
      row.applied ? row.reason : row.skippedReason || row.reason || "",
    );
    const key = `shop.shopEditor.pricing.reasonCodes.${code}`;
    return i18n.global.te(key)
      ? t(key)
      : code.replaceAll("_", " ") ||
          t("shop.shopEditor.pricing.simulator.skipped");
  };
  return {
    ...toRefs(props),
    emit,
    t,
    pricingConfig,
    presetOptions,
    activePolicyId,
    activePolicyOption,
    guardrailFields,
    guardrailDirectFields,
    priceBandFields,
    modeOptions,
    anyLabel,
    optionList,
    priceTierOptions,
    legalityOptions,
    availabilityOptions,
    currencyList,
    previewCurrencyCode,
    previewCurrencyDefinition,
    settlementCurrencyLabel,
    ...pricingFormatters,
    emitPolicyField,
    updateNumberField,
    updatePercentField,
    toPercent,
    valueAt,
    exchangeRateFor,
    updateSettlementCurrency,
    updateExchangeRate,
    hasExchangeRate,
    toggleExchangeRate,
    firstValue,
    stringList,
    numberList,
    updateRule,
    updateRuleMatch,
    updateRuleEffect,
    toggleRuleMode,
    defaultEffectValue,
    displayEffectValue,
    updateRuleEffectValue,
    effectValueLabel,
    toggleDisabledModifier,
    isModifierEnabled,
    previewOptionLabel,
    reasonLabel,
  };
};

import { computed } from "vue";
import i18n from "@/i18n";
import { formatCurrencyAmount } from "@/lib/trade/currency";

export const createPricingFormatters = ({ previewCurrencyDefinition }) => {
  const locale = computed(() =>
    typeof i18n.global.locale === "string"
      ? i18n.global.locale
      : i18n.global.locale.value,
  );
  const formatPrice = (value) => {
    const numeric = Number(value || 0);
    const prefix = numeric < 0 ? "-" : "";
    return `${prefix}${formatCurrencyAmount(Math.abs(numeric), previewCurrencyDefinition.value, locale.value)}`;
  };
  const formatDelta = (value) => {
    const numeric = Number(value || 0);
    const prefix = numeric > 0 ? "+" : "";
    return `${prefix}${formatPrice(numeric)}`;
  };
  const formatRowImpact = (row = {}) =>
    row.key === "currencyConversion"
      ? `= ${formatPrice(row.after)}`
      : formatDelta(row.delta);
  const deltaClass = (value) => ({
    "shop-editor-pricing__breakdown-impact--positive": Number(value || 0) > 0,
    "shop-editor-pricing__breakdown-impact--negative": Number(value || 0) < 0,
  });
  const formatEffect = (row = {}) => {
    if (row?.operation === "additive") {
      return formatDelta(row?.additiveDelta ?? row?.delta ?? 0);
    }
    if (row?.operation === "fixed") {
      return `= ${formatPrice(row?.fixedValue ?? row?.after ?? 0)}`;
    }
    const multiplier = Number(row?.multiplier);
    return Number.isFinite(multiplier) ? `x${multiplier.toFixed(2)}` : "";
  };
  const breakdownMeta = (row = {}) =>
    [String(row?.source || "").trim(), formatEffect(row)]
      .filter(Boolean)
      .join(" | ");

  return {
    locale,
    formatPrice,
    formatDelta,
    formatRowImpact,
    deltaClass,
    formatEffect,
    breakdownMeta,
  };
};

import { computed, ref, watch } from "vue";
import i18n from "@/i18n";
import {
  PRICE_MODIFIER_KEYS,
  applyShopPricingPreset,
  createDefaultShopPricingConfig,
  createShopPricingRule,
  normalizeShopPricingConfig,
} from "@/lib/trade/shopPriceCalculator";

/**
 * View-model for the shop editor screen.
 * Keeps UI-only state local while delegating business operations to the shared trade modal context.
 *
 * @param {object} ctx
 */
import { createEditorViewModelPart1 } from "./shop-editor-view-model/part1";
import { createEditorViewModelPart2 } from "./shop-editor-view-model/part2";

/** Composes the state and commands used by the shop editor screen. */
export function useShopEditorViewModel(ctx) {
  const runtime = {
    ctx,
    computed,
    ref,
    watch,
    i18n,
    PRICE_MODIFIER_KEYS,
    applyShopPricingPreset,
    createDefaultShopPricingConfig,
    createShopPricingRule,
    normalizeShopPricingConfig,
  };
  Object.assign(runtime, createEditorViewModelPart1(runtime));
  Object.assign(runtime, createEditorViewModelPart2(runtime));
  return {
    autoTagItems: runtime.autoTagItems,
    counterfeitRisk: runtime.counterfeitRisk,
    counterfeitRiskTone: runtime.counterfeitRiskTone,
    missingSuggestionFieldLabels: runtime.missingSuggestionFieldLabels,
    pricingModifierOptions: runtime.pricingModifierOptions,
    pricingItemClassOptions: runtime.pricingItemClassOptions,
    pricingItemGenreOptions: runtime.pricingItemGenreOptions,
    pricingCurrencyOptions: runtime.pricingCurrencyOptions,
    pricingRules: runtime.pricingRules,
    previewTemplateId: runtime.previewTemplateId,
    previewTemplateOptions: runtime.previewTemplateOptions,
    pricePreview: runtime.pricePreview,
    previewQuantity: runtime.previewQuantity,
    previewCondition: runtime.previewCondition,
    previewReputation: runtime.previewReputation,
    previewMode: runtime.previewMode,
    previewTemporaryModifier: runtime.previewTemporaryModifier,
    previewQuickMode: runtime.previewQuickMode,
    previewLoading: runtime.previewLoading,
    previewError: runtime.previewError,
    shouldShowFieldError: runtime.shouldShowFieldError,
    showValidationBanner: runtime.showValidationBanner,
    changeShop: runtime.changeShop,
    createShop: runtime.createShop,
    generateSuggestions: runtime.generateSuggestions,
    openActivationDialog: runtime.openActivationDialog,
    saveProfile: runtime.saveProfile,
    updateField: runtime.updateField,
    updatePreviewTemplate: runtime.updatePreviewTemplate,
    updatePreviewInput: runtime.updatePreviewInput,
    updatePricingModifier: runtime.updatePricingModifier,
    updatePricingPolicyField: runtime.updatePricingPolicyField,
    applyPricingPreset: runtime.applyPricingPreset,
    addPricingRule: runtime.addPricingRule,
    updatePricingRule: runtime.updatePricingRule,
    removePricingRule: runtime.removePricingRule,
    savePolicyPreset: runtime.savePolicyPreset,
    applyPolicyPreset: runtime.applyPolicyPreset,
    removePolicyPreset: runtime.removePolicyPreset,
  };
}

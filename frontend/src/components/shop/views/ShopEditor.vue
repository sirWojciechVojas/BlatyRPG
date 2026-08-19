<!-- Moduł GM: Sklep - dodaj/edytuj. Ten plik jest wejściem modułu odpowiedzialnego za konfigurację sklepu, jego parametrów oraz profilu cenowego. -->
<template>
  <ShopModeTemplateFrame
    layout-class="shop-base-layout--editor-scroll"
    shell-class="trade-list-shell trade-list-shell--sell p-0"
    content-class="tab-content w-100 text-light bg-transparent shop-editor-content m-0"
    content-id="shopEditorBuy"
    :header-value="$t('shop.shopEditor.header')"
    notification-zone="buy"
  >
    <ShopEditorValidationBanner
      :visible="showValidationBanner"
      :missing-fields="missingSuggestionFieldLabels"
    />

    <div class="col-md-12 shop-editor-layout-shell p-0">
      <div class="shop-editor-layout-stack">
        <ShopEditorBasicDataSection
          :active-shop-id="ctx.activeShopId"
          :form="ctx.shopEditorForm"
          :owner-options="ctx.shopOwnerOptions"
          :shop-options="ctx.shopEditorShopOptions"
          :shop-type-options="ctx.shopTypeOptions"
          :show-active-shop-error="shouldShowFieldError('activeShopId')"
          :show-type-error="shouldShowFieldError('typeId')"
          @shop-change="changeShop"
          @update-field="handleFieldUpdate"
        />

        <ShopEditorParametersSection
          :counterfeit-risk="counterfeitRisk"
          :counterfeit-risk-tone="counterfeitRiskTone"
          :form="ctx.shopEditorForm"
          :legal-status-options="ctx.legalStatusOptions"
          :location-type-options="ctx.locationTypeOptions"
          :reputation-options="ctx.reputationOptions"
          :seasonality-options="ctx.seasonalityOptions"
          :wealth-tier-options="ctx.wealthTierOptions"
          :world-profile-options="ctx.worldProfileOptions"
          @update-field="handleFieldUpdate"
        />

        <ShopEditorPricingSection
          :form="ctx.shopEditorForm"
          :modifier-options="pricingModifierOptions"
          :pricing-rules="pricingRules"
          :item-class-options="pricingItemClassOptions"
          :item-genre-options="pricingItemGenreOptions"
          :currency-options="pricingCurrencyOptions"
          :currency-definitions="ctx.currencyDefinitions"
          :preview-template-id="previewTemplateId"
          :preview-template-options="previewTemplateOptions"
          :price-preview="pricePreview"
          @toggle-modifier="handlePricingModifierToggle"
          @update-policy-field="updatePricingPolicyField"
          @apply-preset="applyPricingPreset"
          @add-rule="addPricingRule"
          @update-rule="updatePricingRule"
          @remove-rule="removePricingRule"
          @update-preview-template="updatePreviewTemplate"
        />
      </div>
    </div>

    <template #summary>
      <ShopEditorSupportSummary
        :auto-tag-items="autoTagItems"
        :signboard-alt-names-text="ctx.shopEditorForm.signboardAltNamesText"
        @update-field="handleFieldUpdate"
      />
    </template>

    <template #actions>
      <ShopEditorActionsBar
        :can-delete-active-shop="ctx.canDeleteActiveShop"
        @create-shop="createShop"
        @delete-shop="ctx.handleDeleteActiveShopForEditor"
        @draw-signboard="ctx.handleRollShopSignboard"
        @generate-suggestions="generateSuggestions"
        @manage-activation="openActivationDialog"
        @save-profile="saveProfile"
      />
    </template>
  </ShopModeTemplateFrame>
</template>

<script setup>
import ShopModeTemplateFrame from "@/components/shop/layouts/ShopModeTemplateFrame.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";
import { useShopEditorViewModel } from "@/components/shop/composables/useShopEditorViewModel";
import ShopEditorActionsBar from "@/components/shop/modules/shop-editor/components/ShopEditorActionsBar.vue";
import ShopEditorBasicDataSection from "@/components/shop/modules/shop-editor/components/ShopEditorBasicDataSection.vue";
import ShopEditorPricingSection from "@/components/shop/modules/shop-editor/components/ShopEditorPricingSection.vue";
import ShopEditorParametersSection from "@/components/shop/modules/shop-editor/components/ShopEditorParametersSection.vue";
import ShopEditorSupportSummary from "@/components/shop/modules/shop-editor/components/ShopEditorSupportSummary.vue";
import ShopEditorValidationBanner from "@/components/shop/modules/shop-editor/components/ShopEditorValidationBanner.vue";

const ctx = useTradeModalContext();

const {
  autoTagItems,
  counterfeitRisk,
  counterfeitRiskTone,
  missingSuggestionFieldLabels,
  pricingModifierOptions,
  pricingItemClassOptions,
  pricingItemGenreOptions,
  pricingCurrencyOptions,
  pricingRules,
  previewTemplateId,
  previewTemplateOptions,
  pricePreview,
  shouldShowFieldError,
  showValidationBanner,
  changeShop,
  createShop,
  generateSuggestions,
  openActivationDialog,
  saveProfile,
  updateField,
  updatePreviewTemplate,
  updatePricingModifier,
  updatePricingPolicyField,
  applyPricingPreset,
  addPricingRule,
  updatePricingRule,
  removePricingRule,
} = useShopEditorViewModel(ctx);

const handleFieldUpdate = ({ field, value }) => {
  updateField(field, value);
};

const handlePricingModifierToggle = ({ key, enabled }) => {
  updatePricingModifier(key, enabled);
};
</script>
<style src="../modules/shop-editor/styles/ShopEditor.css"></style>

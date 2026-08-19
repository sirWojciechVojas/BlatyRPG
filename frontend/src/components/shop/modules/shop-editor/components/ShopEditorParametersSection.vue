<!-- Komponent modułu Sklep - dodaj/edytuj. Ten plik renderuje parametry świata, prawa, sezonowości oraz ryzyka sklepu. -->
<template>
  <ShopEditorSectionCard
    :title="$t('shop.shopEditor.layoutSections.storeParameters')"
  >
    <div class="shop-editor-block-grid shop-editor-block-grid--parameters">
      <ShopEditorField
        for-id="shop-editor-location"
        :label="$t('shop.shopEditor.location')"
        :tooltip="$t('shop.shopEditor.tooltips.location')"
      >
        <select
          id="shop-editor-location"
          class="form-control-sm w-100 trade-input"
          :value="form.locationType || ''"
          @change="emitFieldUpdate('locationType', $event.target.value)"
        >
          <option
            v-for="option in locationTypeOptions"
            :key="`shop-location-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </ShopEditorField>

      <ShopEditorField
        for-id="shop-editor-world"
        :label="$t('shop.shopEditor.worldProfile')"
        :tooltip="$t('shop.shopEditor.tooltips.worldProfile')"
      >
        <select
          id="shop-editor-world"
          class="form-control-sm w-100 trade-input"
          :value="form.worldProfileId || 'standard'"
          @change="emitFieldUpdate('worldProfileId', $event.target.value)"
        >
          <option
            v-for="option in worldProfileOptions"
            :key="`shop-world-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </ShopEditorField>

      <ShopEditorField
        for-id="shop-editor-legal"
        :label="$t('shop.shopEditor.legalStatus')"
        :tooltip="$t('shop.shopEditor.tooltips.legalStatus')"
        tooltip-align="right"
      >
        <select
          id="shop-editor-legal"
          class="form-control-sm w-100 trade-input"
          :value="form.legalStatus || 'legal'"
          @change="emitFieldUpdate('legalStatus', $event.target.value)"
        >
          <option
            v-for="option in legalStatusOptions"
            :key="`shop-legal-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </ShopEditorField>

      <ShopEditorField
        for-id="shop-editor-wealth"
        :label="$t('shop.shopEditor.wealthTier')"
        :tooltip="$t('shop.shopEditor.tooltips.wealthTier')"
      >
        <select
          id="shop-editor-wealth"
          class="form-control-sm w-100 trade-input"
          :value="form.wealthTier || 'standard'"
          @change="emitFieldUpdate('wealthTier', $event.target.value)"
        >
          <option
            v-for="option in wealthTierOptions"
            :key="`shop-wealth-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </ShopEditorField>

      <ShopEditorField
        for-id="shop-editor-reputation"
        :label="$t('shop.shopEditor.reputation')"
        :tooltip="$t('shop.shopEditor.tooltips.reputation')"
      >
        <select
          id="shop-editor-reputation"
          class="form-control-sm w-100 trade-input"
          :value="form.reputation || 'neutralna'"
          @change="emitFieldUpdate('reputation', $event.target.value)"
        >
          <option
            v-for="option in reputationOptions"
            :key="`shop-reputation-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </ShopEditorField>

      <ShopEditorField
        for-id="shop-editor-seasonality"
        :label="$t('shop.shopEditor.seasonality')"
        :tooltip="$t('shop.shopEditor.tooltips.seasonality')"
      >
        <select
          id="shop-editor-seasonality"
          class="form-control-sm w-100 trade-input"
          :value="form.seasonality || 'caloroczny'"
          @change="emitFieldUpdate('seasonality', $event.target.value)"
        >
          <option
            v-for="option in seasonalityOptions"
            :key="`shop-seasonality-${option.value}`"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
      </ShopEditorField>

      <div
        class="shop-editor-field shop-editor-field--full shop-editor-field--risk"
      >
        <div class="shop-editor-risk-card">
          <div class="shop-editor-risk-card__header">
            <div
              class="shop-mode-template-label shop-mode-template-label--tight"
            >
              <label class="trade-label" for="shop-editor-counterfeit">
                {{ $t("shop.shopEditor.counterfeitRisk") }}
              </label>
              <ShopHelpTooltip
                :label="$t('shop.shopEditor.counterfeitRisk')"
                :text="$t('shop.shopEditor.tooltips.counterfeitRisk')"
              />
            </div>
          </div>

          <div class="shop-editor-risk-card__body">
            <input
              id="shop-editor-counterfeit"
              type="range"
              min="0"
              max="100"
              class="form-range shop-editor-range shop-editor-range--featured"
              :value="form.counterfeitRisk || 0"
              @input="
                emitFieldUpdate('counterfeitRisk', Number($event.target.value))
              "
            />
            <span
              class="shop-editor-risk-badge"
              :class="`shop-editor-risk-badge--${counterfeitRiskTone}`"
            >
              {{ counterfeitRisk }}%
            </span>
          </div>
        </div>
      </div>
    </div>
  </ShopEditorSectionCard>
</template>

<script setup>
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import ShopEditorField from "@/components/shop/modules/shop-editor/components/ShopEditorField.vue";
import ShopEditorSectionCard from "@/components/shop/modules/shop-editor/components/ShopEditorSectionCard.vue";

defineProps({
  counterfeitRisk: {
    type: Number,
    required: true,
  },
  counterfeitRiskTone: {
    type: String,
    required: true,
  },
  form: {
    type: Object,
    required: true,
  },
  legalStatusOptions: {
    type: Array,
    default: () => [],
  },
  locationTypeOptions: {
    type: Array,
    default: () => [],
  },
  reputationOptions: {
    type: Array,
    default: () => [],
  },
  seasonalityOptions: {
    type: Array,
    default: () => [],
  },
  wealthTierOptions: {
    type: Array,
    default: () => [],
  },
  worldProfileOptions: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["update-field"]);

const emitFieldUpdate = (field, value) => {
  emit("update-field", { field, value });
};
</script>

<template>
  <header class="shop-editor-pricing__section-header">
    <div>
      <strong>{{ $t("shop.shopEditor.pricing.simulator.title") }}</strong>
      <small>{{ $t("shop.shopEditor.pricing.simulator.description") }}</small>
    </div>
    <label class="shop-editor-pricing__inline-toggle">
      <input
        type="checkbox"
        :checked="previewQuickMode"
        @change="updateSimulator('quickMode', $event.target.checked)"
      />
      <span>{{ $t("shop.shopEditor.pricing.simulator.quickTest") }}</span>
      <ShopHelpTooltip
        :label="$t('shop.shopEditor.pricing.simulator.quickTest')"
        :text="$t('shop.shopEditor.pricing.help.quickTest')"
      />
    </label>
  </header>

  <div class="shop-editor-pricing__simulator-controls">
    <ShopEditorField
      for-id="shop-editor-price-preview-template"
      :label="$t('shop.shopEditor.pricing.previewTemplate')"
      :tooltip="$t('shop.shopEditor.pricing.help.previewTemplate')"
    >
      <select
        id="shop-editor-price-preview-template"
        class="form-control-sm trade-input"
        :value="previewTemplateId ?? ''"
        @change="emit('update-preview-template', $event.target.value)"
      >
        <option
          v-for="option in previewTemplateOptions"
          :key="option.value"
          :value="option.value"
        >
          {{ previewOptionLabel(option) }}
        </option>
      </select>
    </ShopEditorField>
    <ShopEditorField
      for-id="shop-editor-price-preview-mode"
      :label="$t('shop.shopEditor.pricing.simulator.mode')"
      :tooltip="$t('shop.shopEditor.pricing.help.previewMode')"
    >
      <select
        id="shop-editor-price-preview-mode"
        class="form-control-sm trade-input"
        :value="previewMode"
        @change="updateSimulator('mode', $event.target.value)"
      >
        <option value="buy">
          {{ $t("shop.shopEditor.pricing.simulator.playerBuys") }}
        </option>
        <option value="sell">
          {{ $t("shop.shopEditor.pricing.simulator.shopBuys") }}
        </option>
      </select>
    </ShopEditorField>
    <ShopEditorField
      for-id="shop-editor-price-preview-quantity"
      :label="$t('shop.shopEditor.pricing.simulator.quantity')"
      :tooltip="$t('shop.shopEditor.pricing.help.previewQuantity')"
    >
      <input
        id="shop-editor-price-preview-quantity"
        class="form-control-sm trade-input"
        type="number"
        min="1"
        max="9999"
        :value="previewQuantity"
        @input="updateSimulator('quantity', Number($event.target.value))"
      />
    </ShopEditorField>
    <ShopEditorField
      for-id="shop-editor-price-preview-condition"
      :label="$t('shop.shopEditor.pricing.simulator.condition')"
      :tooltip="$t('shop.shopEditor.pricing.help.previewCondition')"
    >
      <select
        id="shop-editor-price-preview-condition"
        class="form-control-sm trade-input"
        :value="previewCondition"
        @change="updateSimulator('condition', $event.target.value)"
      >
        <option v-for="value in conditionOptions" :key="value" :value="value">
          {{ $t(`shop.shopEditor.pricing.simulator.conditions.${value}`) }}
        </option>
      </select>
    </ShopEditorField>
    <ShopEditorField
      for-id="shop-editor-price-preview-reputation"
      :label="$t('shop.shopEditor.pricing.simulator.reputation')"
      :tooltip="$t('shop.shopEditor.pricing.help.previewReputation')"
    >
      <select
        id="shop-editor-price-preview-reputation"
        class="form-control-sm trade-input"
        :value="previewReputation"
        @change="updateSimulator('reputation', $event.target.value)"
      >
        <option v-for="value in reputationValues" :key="value" :value="value">
          {{ $t(`shop.workspace.options.reputation.${value}`) }}
        </option>
      </select>
    </ShopEditorField>
    <ShopEditorField
      for-id="shop-editor-price-preview-temporary"
      :label="$t('shop.shopEditor.pricing.simulator.temporary')"
      :tooltip="$t('shop.shopEditor.pricing.help.previewTemporary')"
    >
      <input
        id="shop-editor-price-preview-temporary"
        class="form-control-sm trade-input"
        type="number"
        min="-100"
        max="500"
        step="1"
        :value="previewTemporaryModifier"
        @input="
          updateSimulator('temporaryModifier', Number($event.target.value))
        "
      />
    </ShopEditorField>
  </div>
</template>

<script>
import ShopEditorField from "@/components/shop/modules/shop-editor/components/ShopEditorField.vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopPricingContext } from "../../shopPricingContext";

export default {
  name: "ShopPricingPreviewControls",
  components: { ShopEditorField, ShopHelpTooltip },
  setup() {
    const context = useShopPricingContext();
    return {
      ...context,
      updateSimulator: (field, value) =>
        context.emit("update-preview-input", { field, value }),
      conditionOptions: ["ruined", "poor", "worn", "good", "excellent"],
      reputationValues: [
        "fatalna",
        "zla",
        "podejrzana",
        "neutralna",
        "dobra",
        "znakomita",
      ],
    };
  },
};
</script>

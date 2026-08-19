<!-- Edytor cen sklepu: ShopPricingPresets. -->
<template>
  <section class="shop-editor-pricing__policy-block">
    <header class="shop-editor-pricing__section-header">
      <div>
        <strong>
          {{ $t("shop.shopEditor.pricing.presets.title") }}
          <ShopHelpTooltip :text="$t('shop.shopEditor.pricing.help.presets')" />
        </strong>
        <small>{{ $t("shop.shopEditor.pricing.presets.description") }}</small>
      </div>
    </header>
    <div
      class="shop-editor-pricing__assignment"
      :class="{
        'shop-editor-pricing__assignment--general':
          activePolicyId === 'general',
        'shop-editor-pricing__assignment--custom': activePolicyId === 'custom',
      }"
      role="status"
      aria-live="polite"
    >
      <span>{{ $t("shop.shopEditor.pricing.assignment.selected") }}</span>
      <strong>{{ activePolicyOption.label }}</strong>
      <small>{{ activePolicyOption.description }}</small>
      <small v-if="form.signboardName">
        {{
          $t("shop.shopEditor.pricing.assignment.shop", {
            shop: form.signboardName,
          })
        }}
      </small>
    </div>
    <div class="shop-editor-pricing__custom-presets">
      <input
        v-model.trim="policyPresetName"
        class="form-control-sm trade-input"
        :placeholder="$t('shop.shopEditor.pricing.presets.customName')"
        maxlength="80"
        @keyup.enter="saveCustomPolicyPreset"
      />
      <button
        type="button"
        class="btn btn-sm btn-outline-light"
        @click="saveCustomPolicyPreset"
      >
        {{ $t("shop.shopEditor.pricing.presets.saveCustom") }}
      </button>
      <span
        v-for="preset in form.customPresets?.policies || []"
        :key="preset.id"
      >
        <button type="button" @click="emit('apply-policy-preset', preset)">
          {{ preset.name }}
        </button>
        <button type="button" @click="emit('remove-policy-preset', preset.id)">
          ×
        </button>
      </span>
    </div>
    <div class="shop-editor-pricing__preset-list">
      <button
        v-for="preset in presetOptions"
        :key="`pricing-preset-${preset.id}`"
        type="button"
        class="btn btn-sm shop-editor-pricing__preset"
        :class="{
          'shop-editor-pricing__preset--selected': activePolicyId === preset.id,
        }"
        :aria-pressed="activePolicyId === preset.id"
        @click="emit('apply-preset', preset.id)"
      >
        <span
          v-if="activePolicyId === preset.id"
          class="shop-editor-pricing__preset-selected"
        >
          ✓ {{ $t("shop.shopEditor.pricing.assignment.active") }}
        </span>
        <strong>{{ preset.label }}</strong>
        <small>{{ preset.description }}</small>
      </button>
      <div
        v-if="activePolicyId === 'custom'"
        class="shop-editor-pricing__preset shop-editor-pricing__preset--selected shop-editor-pricing__preset--readonly"
      >
        <span class="shop-editor-pricing__preset-selected">
          ✓ {{ $t("shop.shopEditor.pricing.assignment.active") }}
        </span>
        <strong>{{ activePolicyOption.label }}</strong>
        <small>{{ activePolicyOption.description }}</small>
      </div>
    </div>
  </section>
</template>
<script>
import { ref } from "vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopPricingContext } from "../../shopPricingContext";
export default {
  name: "ShopPricingPresets",
  components: { ShopHelpTooltip },
  setup() {
    const context = useShopPricingContext();
    const policyPresetName = ref("");
    const saveCustomPolicyPreset = () => {
      const name = policyPresetName.value.trim();
      if (!name) return;
      context.emit("save-policy-preset", name);
      policyPresetName.value = "";
    };
    return { ...context, policyPresetName, saveCustomPolicyPreset };
  },
};
</script>

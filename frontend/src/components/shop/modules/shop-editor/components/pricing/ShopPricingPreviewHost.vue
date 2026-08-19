<template>
  <ShopPricingPreview />
</template>

<script setup>
import { provide } from "vue";
import ShopPricingPreview from "./ShopPricingPreview.vue";
import { createPricingSectionContext } from "../../composables/useShopPricingSection";
import { shopPricingContextKey } from "../../shopPricingContext";

const props = defineProps({
  form: { type: Object, required: true },
  currencyOptions: { type: Array, default: () => [] },
  currencyDefinitions: { type: Object, default: () => ({}) },
  previewTemplateId: { type: [Number, String], default: null },
  previewTemplateOptions: { type: Array, default: () => [] },
  pricePreview: { type: Object, default: null },
  previewQuantity: { type: Number, default: 1 },
  previewCondition: { type: String, default: "good" },
  previewReputation: { type: String, default: "neutralna" },
  previewMode: { type: String, default: "buy" },
  previewTemporaryModifier: { type: Number, default: 0 },
  previewQuickMode: { type: Boolean, default: false },
  previewLoading: { type: Boolean, default: false },
  previewError: { type: String, default: "" },
});
const emit = defineEmits(["update-preview-template", "update-preview-input"]);
provide(shopPricingContextKey, createPricingSectionContext(props, emit));
</script>

<style src="../../styles/ShopEditorPricingSection.1.css"></style>
<style src="../../styles/ShopEditorPricingSection.2.css"></style>
<style src="../../styles/ShopEditorPricingSimulator.css"></style>

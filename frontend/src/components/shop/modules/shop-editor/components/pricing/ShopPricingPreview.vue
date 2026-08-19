<!-- Zwarty, serwerowy symulator cen dla profilu i polityki sklepu. -->
<template>
  <section class="shop-editor-pricing__simulator" aria-live="polite">
    <ShopPricingPreviewControls />
    <ShopPricingPreviewResults />
  </section>
</template>

<script setup>
import ShopPricingPreviewControls from "./ShopPricingPreviewControls.vue";
import ShopPricingPreviewResults from "./ShopPricingPreviewResults.vue";
</script>

<style src="../../styles/ShopPricingPreviewTabs.css"></style>

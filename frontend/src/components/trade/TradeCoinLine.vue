<!-- Responsibility: TradeCoinLine shop interface component. -->
<template>
  <CurrencyDisplay
    :brass="brass"
    :currency-code="currencyCode"
    :aria-label="ariaLabel"
    :variant="variant"
  />
</template>

<script>
import CurrencyDisplay from "@/components/trade/CurrencyDisplay.vue";

export default {
  name: "TradeCoinLine",
  components: {
    CurrencyDisplay,
  },
  props: {
    brass: {
      type: [Number, String],
      default: 0,
    },
    ariaLabel: {
      type: String,
      default: "",
    },
    variant: {
      type: String,
      default: "inline",
    },
    currencyCode: {
      type: String,
      default: "wfrp_empire",
    },
  },
};
</script>

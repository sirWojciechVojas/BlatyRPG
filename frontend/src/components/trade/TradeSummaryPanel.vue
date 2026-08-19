<!-- Responsibility: TradeSummaryPanel shop interface component. -->
<template>
  <div class="trade-modal__summary p-0">
    <span class="trade-modal__summary-label">{{ label }}:</span>
    <TradeCoinLine
      class="tradingBrassLine text-light trade-modal__summary-price"
      :brass="brass"
      :aria-label="label"
      :currency-code="currencyCode"
      variant="summary"
    />
  </div>
</template>

<script>
import TradeCoinLine from "@/components/trade/TradeCoinLine.vue";

export default {
  name: "TradeSummaryPanel",
  components: {
    TradeCoinLine,
  },
  props: {
    label: {
      type: String,
      required: true,
    },
    brass: {
      type: [Number, String],
      default: 0,
    },
    currencyCode: {
      type: String,
      default: "generic",
    },
  },
};
</script>

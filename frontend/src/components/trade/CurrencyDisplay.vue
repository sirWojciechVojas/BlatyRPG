<!-- Responsibility: CurrencyDisplay shop interface component. -->
<template>
  <div
    class="currency-display trade-coin-line"
    :class="[
      `currency-display--${variant}`,
      { 'currency-display--muted': muted },
    ]"
    :aria-label="ariaLabelText"
    role="group"
  >
    <span
      v-for="entry in coinEntries"
      :key="entry.code"
      class="currency-display__pair"
    >
      <img
        v-if="entry.iconSrc"
        class="currency-display__icon-image trade-coin-line__icon"
        :src="entry.iconSrc"
        alt=""
        aria-hidden="true"
      />
      <span v-else class="currency-display__token" aria-hidden="true">
        {{ entry.symbol }}
      </span>
      <span class="currency-display__value trade-coin-line__value">
        {{ entry.amount }}
      </span>
    </span>
  </div>
</template>

<script>
import i18n from "@/i18n";
import crownImg from "@/assets/app-ui/img/brass/mGoldCrowns.jpg";
import shillingImg from "@/assets/app-ui/img/brass/mSilverShillings.jpg";
import brassImg from "@/assets/app-ui/img/brass/mBronzePennies.jpg";
import {
  COC_CURRENCY_DEFINITIONS,
  GENERIC_CURRENCY_DEFINITION,
  WFRP_CURRENCY_DEFINITIONS,
  decomposeCurrencyAmount,
} from "@/lib/trade/currency";

export default {
  name: "CurrencyDisplay",
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
    muted: {
      type: Boolean,
      default: false,
    },
    currencyCode: {
      type: String,
      default: "wfrp_empire",
    },
  },
  computed: {
    locale() {
      return typeof i18n.global.locale === "string"
        ? i18n.global.locale
        : i18n.global.locale.value;
    },
    currencyDefinition() {
      const definitions = [
        ...WFRP_CURRENCY_DEFINITIONS,
        ...COC_CURRENCY_DEFINITIONS,
        GENERIC_CURRENCY_DEFINITION,
      ];
      return (
        definitions.find((entry) => entry.code === this.currencyCode) ||
        GENERIC_CURRENCY_DEFINITION
      );
    },
    coinEntries() {
      const iconAssets = {
        crown: crownImg,
        shilling: shillingImg,
        brass: brassImg,
      };
      const amounts = decomposeCurrencyAmount(
        this.brass,
        this.currencyDefinition,
      );
      return this.currencyDefinition.units.map((unit) => ({
        ...unit,
        amount: amounts[unit.code] || 0,
        symbol: String(this.locale).startsWith("pl")
          ? unit.symbolPl || unit.symbolEn
          : unit.symbolEn || unit.symbolPl,
        iconSrc: iconAssets[unit.icon] || "",
      }));
    },
    ariaLabelText() {
      return (
        this.ariaLabel ||
        this.coinEntries
          .map((entry) => `${entry.amount} ${entry.symbol}`)
          .join(", ")
      );
    },
  },
};
</script>

<style scoped>
.currency-display {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.35rem;
}
.currency-display__pair {
  display: inline-grid;
  grid-template-columns: 1.3rem auto;
  align-items: center;
  gap: 0.15rem;
}
.currency-display__icon-image {
  width: 1.25rem;
  height: 1.25rem;
  border: 0;
  border-radius: 0;
  box-shadow: none;
  object-fit: contain;
}
.currency-display__value {
  min-width: 1ch;
  font-variant-numeric: tabular-nums;
  text-align: right;
}
.currency-display__token {
  display: inline-grid;
  min-width: 1.65rem;
  min-height: 1.35rem;
  place-items: center;
  border: 1px solid rgba(214, 170, 93, 0.65);
  border-radius: 50%;
  background: #5a371b;
  color: #f2d49a;
  font-size: 0.58rem;
  font-weight: 800;
  text-transform: uppercase;
}
.currency-display--row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  justify-content: stretch;
  gap: 0.22rem;
}
.currency-display--row .currency-display__pair {
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
  min-height: 1.55rem;
  grid-template-columns: 1rem minmax(0, 1fr);
  gap: 0.12rem;
  border: 1px solid var(--currency-compartment-border);
  border-radius: 0.15rem;
  background: var(--currency-compartment-bg);
  box-shadow:
    inset 0 1px 1px rgba(255, 237, 190, 0.08),
    inset 0 -2px 4px rgba(0, 0, 0, 0.46);
  padding: 0.16rem;
}
.currency-display--row .currency-display__pair:nth-child(1) {
  --currency-compartment-border: rgba(190, 142, 48, 0.42);
  --currency-compartment-bg: rgba(83, 58, 13, 0.24);
}
.currency-display--row .currency-display__pair:nth-child(2) {
  --currency-compartment-border: rgba(190, 190, 181, 0.34);
  --currency-compartment-bg: rgba(105, 108, 104, 0.16);
}
.currency-display--row .currency-display__pair:nth-child(3) {
  --currency-compartment-border: rgba(177, 105, 55, 0.42);
  --currency-compartment-bg: rgba(101, 48, 24, 0.2);
}
.currency-display--row .currency-display__value {
  width: 100%;
  min-width: 0;
  color: #fff4df;
  font-size: 0.68rem;
  font-weight: 650;
  letter-spacing: -0.015em;
  line-height: 1;
  white-space: nowrap;
}
</style>

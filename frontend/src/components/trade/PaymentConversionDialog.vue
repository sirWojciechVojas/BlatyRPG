<template>
  <div v-if="open" class="payment-conversion" role="dialog" aria-modal="true">
    <section class="payment-conversion__panel">
      <header>
        <div>
          <strong>{{ $t("shop.payment.title") }}</strong>
          <small>{{ $t("shop.payment.description") }}</small>
        </div>
        <button
          type="button"
          :aria-label="$t('actions.close')"
          @click="$emit('close')"
        >
          ×
        </button>
      </header>

      <div class="payment-conversion__summary">
        <span>{{ $t("shop.payment.price") }}</span>
        <CurrencyDisplay
          :brass="quote.price"
          :currency-code="quote.settlementCurrencyCode"
          variant="inline"
        />
      </div>
      <div
        class="payment-conversion__summary payment-conversion__summary--muted"
      >
        <span>{{ $t("shop.payment.directDebit") }}</span>
        <CurrencyDisplay
          :brass="quote.settlementDebit"
          :currency-code="quote.settlementCurrencyCode"
          variant="inline"
        />
      </div>

      <div class="payment-conversion__wallets">
        <label
          v-for="wallet in quote.foreignWallets || []"
          :key="wallet.currencyCode"
        >
          <input
            type="checkbox"
            :checked="wallet.selected"
            :disabled="busy"
            @change="$emit('toggle-currency', wallet.currencyCode)"
          />
          <span class="payment-conversion__wallet-name">{{
            currencyLabel(wallet.currencyCode)
          }}</span>
          <small>
            {{
              $t("shop.payment.rateAndFee", {
                rate: wallet.exchangeRate,
                fee: wallet.feePercent,
              })
            }}
          </small>
          <span>
            <CurrencyDisplay
              :brass="wallet.debit"
              :currency-code="wallet.currencyCode"
              variant="inline"
            />
            →
            <CurrencyDisplay
              :brass="wallet.settlementCovered"
              :currency-code="quote.settlementCurrencyCode"
              variant="inline"
            />
          </span>
        </label>
      </div>

      <p v-if="!quote.canPay" class="payment-conversion__error">
        {{ $t("shop.payment.insufficient") }}
      </p>
      <footer>
        <button
          type="button"
          class="btn"
          :disabled="busy"
          @click="$emit('close')"
        >
          {{ $t("actions.cancel") }}
        </button>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="busy || !quote.canPay"
          @click="$emit('confirm')"
        >
          {{
            busy ? $t("shop.payment.refreshing") : $t("shop.payment.confirm")
          }}
        </button>
      </footer>
    </section>
  </div>
</template>

<script>
import CurrencyDisplay from "@/components/trade/CurrencyDisplay.vue";

export default {
  name: "PaymentConversionDialog",
  components: { CurrencyDisplay },
  props: {
    open: { type: Boolean, default: false },
    quote: { type: Object, default: () => ({}) },
    busy: { type: Boolean, default: false },
    currencyDefinitions: { type: Object, default: () => ({}) },
  },
  emits: ["close", "confirm", "toggle-currency"],
  methods: {
    currencyLabel(code) {
      const definition = (this.currencyDefinitions?.currencies || []).find(
        (entry) =>
          String(entry?.code || "").toLowerCase() ===
          String(code || "").toLowerCase(),
      );
      const isPolish = String(this.$i18n?.locale || "pl").startsWith("pl");
      return (
        (isPolish ? definition?.labelPl : definition?.labelEn) ||
        definition?.labelPl ||
        code
      );
    },
  },
};
</script>

<style scoped>
.payment-conversion {
  position: absolute;
  inset: 0;
  z-index: 120;
  display: grid;
  place-items: center;
  padding: 1rem;
  background: rgb(0 0 0 / 54%);
}
.payment-conversion__panel {
  width: min(31rem, calc(100% - 1rem));
  max-height: calc(100% - 2rem);
  overflow: auto;
  color: #ead9b8;
  border: 1px solid #98733d;
  border-radius: 0.6rem;
  background: #17120d;
  box-shadow: 0 1rem 3rem #000;
  padding: 0.85rem;
}
.payment-conversion__panel header,
.payment-conversion__panel footer,
.payment-conversion__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}
.payment-conversion__panel header {
  border-bottom: 1px solid #5d482d;
  padding-bottom: 0.55rem;
  margin-bottom: 0.6rem;
}
.payment-conversion__panel header div {
  display: grid;
}
.payment-conversion__panel header button {
  border: 0;
  color: inherit;
  background: transparent;
  font-size: 1.4rem;
}
.payment-conversion__summary {
  padding: 0.25rem 0;
}
.payment-conversion__summary--muted {
  color: #c1ad8a;
  font-size: 0.9rem;
}
.payment-conversion__wallets {
  display: grid;
  gap: 0.35rem;
  margin: 0.7rem 0;
}
.payment-conversion__wallets label {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 0.25rem 0.55rem;
  padding: 0.45rem;
  border: 1px solid #4f3d28;
  border-radius: 0.35rem;
  background: #0d0a07;
}
.payment-conversion__wallets small {
  grid-column: 2 / -1;
  color: #ae9b7c;
}
.payment-conversion__wallet-name {
  font-weight: 700;
}
.payment-conversion__error {
  color: #ffad91;
}
.payment-conversion__panel footer {
  border-top: 1px solid #5d482d;
  padding-top: 0.7rem;
}
@media (max-width: 600px) {
  .payment-conversion {
    place-items: end center;
    padding: 0.5rem;
  }
  .payment-conversion__panel {
    max-height: 78%;
  }
}
</style>

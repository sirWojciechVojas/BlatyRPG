<template>
  <fieldset class="character-wallet" :disabled="disabled || saving">
    <legend>{{ $t("characters.sections.wallets") }}</legend>
    <div v-if="loading" class="character-muted" role="status">
      {{ $t("characters.wallet.loading") }}
    </div>
    <template v-else-if="wallets.length">
      <header class="character-wallet__toolbar">
        <label>
          <span>{{ $t("characters.wallet.defaultWallet") }}</span>
          <select v-model="primaryCurrencyCode" @change="ensurePrimaryWallet">
            <option
              v-for="definition in currencies"
              :key="definition.code"
              :value="definition.code"
            >
              {{ currencyLabel(definition) }}
            </option>
          </select>
        </label>
        <details v-if="availableCurrencies.length" ref="addMenu">
          <summary>{{ $t("characters.wallet.add") }}</summary>
          <div>
            <button
              v-for="definition in availableCurrencies"
              :key="definition.code"
              type="button"
              @click="addWallet(definition)"
            >
              {{ currencyLabel(definition) }}
            </button>
          </div>
        </details>
      </header>
      <div class="character-wallet__grid">
        <article
          v-for="wallet in orderedWallets"
          :key="wallet.currencyCode"
          :class="{
            'is-primary': wallet.currencyCode === primaryCurrencyCode,
          }"
        >
          <header>
            <strong>
              {{ currencyLabel(wallet.definition) }}
              <small v-if="wallet.currencyCode === primaryCurrencyCode">
                {{ $t("characters.wallet.defaultBadge") }}
              </small>
            </strong>
            <CurrencyDisplay
              :brass="walletBalance(wallet)"
              :currency-code="wallet.currencyCode"
              variant="row"
            />
            <button
              v-if="wallet.currencyCode !== primaryCurrencyCode"
              type="button"
              class="character-wallet__remove"
              :aria-label="$t('characters.wallet.remove')"
              :title="$t('characters.wallet.remove')"
              @click="removeWallet(wallet)"
            >
              ×
            </button>
          </header>
          <div class="character-wallet__units">
            <label v-for="unit in wallet.definition.units" :key="unit.code">
              <img
                v-if="unitIcon(unit.icon)"
                :src="unitIcon(unit.icon)"
                alt=""
                aria-hidden="true"
              />
              <span v-else class="character-wallet__token" aria-hidden="true">
                {{ unitSymbol(unit) }}
              </span>
              <span>{{ unitLabel(unit) }}</span>
              <input
                v-model.number="wallet.amounts[unit.code]"
                type="number"
                min="0"
                step="1"
                inputmode="numeric"
              />
            </label>
          </div>
        </article>
      </div>
      <small v-if="saving" class="character-muted" role="status">
        {{ $t("characters.wallet.saving") }}
      </small>
      <small v-else-if="saved" class="character-wallet__saved" role="status">
        {{ $t("characters.wallet.saved") }}
      </small>
    </template>
    <p v-else-if="!error" class="character-muted">
      {{ $t("characters.wallet.empty") }}
    </p>
    <p v-if="error" class="character-sheet-error" role="alert">
      {{ $t("characters.wallet.error") }}
    </p>
  </fieldset>
</template>

<script>
import crownImg from "@/assets/app-ui/img/brass/mGoldCrowns.jpg";
import shillingImg from "@/assets/app-ui/img/brass/mSilverShillings.jpg";
import brassImg from "@/assets/app-ui/img/brass/mBronzePennies.jpg";
import CurrencyDisplay from "@/components/trade/CurrencyDisplay.vue";
import { characterApiClient } from "@/lib/character/characterApiClient";
import {
  composeCurrencyAmount,
  decomposeCurrencyAmount,
  localizedCurrencyLabel,
} from "@/lib/trade/currency";

export default {
  name: "CharacterWalletEditor",
  components: { CurrencyDisplay },
  props: {
    campaignId: { type: [Number, String], default: null },
    characterId: { type: [Number, String], default: null },
    disabled: { type: Boolean, default: false },
  },
  emits: ["changed"],
  data: () => ({
    wallets: [],
    currencies: [],
    primaryCurrencyCode: "",
    loading: false,
    saving: false,
    saved: false,
    error: false,
    requestSequence: 0,
  }),
  computed: {
    locale() {
      return this.$i18n.locale;
    },
    availableCurrencies() {
      const active = new Set(this.wallets.map((wallet) => wallet.currencyCode));
      return this.currencies.filter((currency) => !active.has(currency.code));
    },
    orderedWallets() {
      return [...this.wallets].sort((left, right) => {
        if (left.currencyCode === this.primaryCurrencyCode) return -1;
        if (right.currencyCode === this.primaryCurrencyCode) return 1;
        return this.currencyLabel(left.definition).localeCompare(
          this.currencyLabel(right.definition),
          this.locale,
        );
      });
    },
  },
  watch: {
    campaignId: { handler: "load" },
    characterId: { immediate: true, handler: "load" },
  },
  methods: {
    async load() {
      const campaignId = Number(this.campaignId);
      const characterId = Number(this.characterId);
      const sequence = ++this.requestSequence;
      this.wallets = [];
      this.currencies = [];
      this.primaryCurrencyCode = "";
      this.error = false;
      this.saved = false;
      if (!campaignId || !characterId) return;
      this.loading = true;
      try {
        const payload = await characterApiClient.wallets(
          campaignId,
          characterId,
        );
        if (sequence !== this.requestSequence) return;
        const balances = new Map(
          (payload?.wallets || []).map((wallet) => [
            wallet.currencyCode,
            Number(wallet.balance) || 0,
          ]),
        );
        this.currencies = payload?.currencies || [];
        this.primaryCurrencyCode =
          payload?.primaryCurrencyCode || this.currencies[0]?.code || "";
        const activeCodes = new Set([
          ...balances.keys(),
          this.primaryCurrencyCode,
        ]);
        this.wallets = this.currencies
          .filter((definition) => activeCodes.has(definition.code))
          .map((definition) =>
            this.createWallet(definition, balances.get(definition.code) || 0),
          );
      } catch (_error) {
        if (sequence === this.requestSequence) this.error = true;
      } finally {
        if (sequence === this.requestSequence) this.loading = false;
      }
    },
    walletBalance(wallet) {
      return composeCurrencyAmount(wallet.amounts, wallet.definition);
    },
    currencyLabel(definition) {
      return localizedCurrencyLabel(definition, this.locale);
    },
    unitLabel(unit) {
      return String(this.locale).startsWith("pl")
        ? unit.labelPluralPl || unit.labelPl || unit.code
        : unit.labelPluralEn || unit.labelEn || unit.code;
    },
    unitSymbol(unit) {
      return String(this.locale).startsWith("pl")
        ? unit.symbolPl || unit.symbolEn
        : unit.symbolEn || unit.symbolPl;
    },
    unitIcon(icon) {
      return { crown: crownImg, shilling: shillingImg, brass: brassImg }[icon];
    },
    createWallet(definition, balance = 0) {
      return {
        currencyCode: definition.code,
        definition,
        amounts: decomposeCurrencyAmount(balance, definition),
      };
    },
    addWallet(definition) {
      if (
        !definition ||
        this.wallets.some((wallet) => wallet.currencyCode === definition.code)
      )
        return;
      this.wallets.push(this.createWallet(definition));
      this.saved = false;
      if (this.$refs.addMenu) this.$refs.addMenu.open = false;
    },
    ensurePrimaryWallet() {
      const definition = this.currencies.find(
        (currency) => currency.code === this.primaryCurrencyCode,
      );
      if (definition) this.addWallet(definition);
      this.saved = false;
    },
    removeWallet(wallet) {
      if (!wallet || wallet.currencyCode === this.primaryCurrencyCode) return;
      if (
        this.walletBalance(wallet) > 0 &&
        !window.confirm(
          this.$t("characters.wallet.removeConfirm", {
            name: this.currencyLabel(wallet.definition),
          }),
        )
      )
        return;
      this.wallets = this.wallets.filter(
        (item) => item.currencyCode !== wallet.currencyCode,
      );
      this.saved = false;
    },
    async save() {
      if (this.disabled) return true;
      if (this.saving || this.loading || !this.wallets.length) return false;
      this.saving = true;
      this.saved = false;
      this.error = false;
      try {
        const payload = await characterApiClient.updateWallets(
          this.campaignId,
          this.characterId,
          this.wallets.map((wallet) => ({
            currencyCode: wallet.currencyCode,
            balance: this.walletBalance(wallet),
          })),
          this.primaryCurrencyCode,
        );
        const balances = new Map(
          (payload?.wallets || []).map((wallet) => [
            wallet.currencyCode,
            Number(wallet.balance) || 0,
          ]),
        );
        this.wallets.forEach((wallet) => {
          wallet.amounts = decomposeCurrencyAmount(
            balances.get(wallet.currencyCode) || 0,
            wallet.definition,
          );
        });
        this.saved = true;
        this.$emit("changed", payload);
        return payload;
      } catch (_error) {
        this.error = true;
        return false;
      } finally {
        this.saving = false;
      }
    },
  },
};
</script>

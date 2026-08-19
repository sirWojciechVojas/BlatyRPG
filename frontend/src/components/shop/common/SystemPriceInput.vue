<!-- Responsibility: SystemPriceInput shop interface component. -->
<template>
  <details
    ref="root"
    class="system-price-input"
    @keydown.esc.stop.prevent="closePanel(true)"
  >
    <summary :aria-label="t('shop.workspace.currency.editPrice')">
      <span class="system-price-input__summary-coins">
        <span v-for="entry in coinEntries" :key="entry.code">
          <img :src="entry.iconSrc" alt="" aria-hidden="true" />
          <strong>{{ entry.amount }}</strong>
          <small>{{ entry.symbol }}</small>
        </span>
      </span>
      <small class="system-price-input__region">{{ currencyLabel }}</small>
    </summary>
    <div class="system-price-input__panel">
      <label>
        <span>{{ t("shop.workspace.currency.currency") }}</span>
        <select
          class="form-select form-select-sm domain-combobox--currency"
          v-model="selectedCurrencyCode"
          :aria-label="t('shop.workspace.currency.currency')"
        >
          <option
            v-for="definition in normalizedDefinitions"
            :key="definition.code"
            :value="definition.code"
          >
            {{ localizedCurrencyLabel(definition, locale) }}
          </option>
        </select>
      </label>
      <div class="system-price-input__units">
        <label v-for="unit in activeDefinition.units" :key="unit.code">
          <span class="system-price-input__unit-label">
            <img :src="coinAsset(unit)" alt="" aria-hidden="true" />
            <span>{{ unitLabel(unit) }}</span>
            <small>{{ unitSymbol(unit) }}</small>
          </span>
          <input
            type="number"
            min="0"
            step="1"
            :value="amounts[unit.code] || 0"
            :aria-label="unitLabel(unit)"
            @input="updateUnit(unit.code, $event.target.value)"
          />
        </label>
      </div>
      <p>
        {{ t("shop.workspace.currency.canonicalHint") }}:
        <strong>{{ normalizedValue }}</strong>
      </p>
    </div>
  </details>
</template>

<script setup>
import {
  computed,
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch,
} from "vue";
import i18n from "@/i18n";
import crownImg from "@/assets/app-ui/img/brass/mGoldCrowns.jpg";
import shillingImg from "@/assets/app-ui/img/brass/mSilverShillings.jpg";
import brassImg from "@/assets/app-ui/img/brass/mBronzePennies.jpg";
import {
  WFRP_CURRENCY_DEFINITIONS,
  composeCurrencyAmount,
  decomposeCurrencyAmount,
  localizedCurrencyLabel,
  resolveCurrencyDefinition,
} from "@/lib/trade/currency";

const props = defineProps({
  modelValue: { type: [Number, String], default: 0 },
  currencyCode: { type: String, default: "" },
  definitions: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:modelValue", "update:currencyCode"]);
const root = ref(null);
const amounts = reactive({});
const coinAssets = { crown: crownImg, shilling: shillingImg, brass: brassImg };
const t = (key, values = {}) => i18n.global.t(key, values);
const locale = computed(() =>
  typeof i18n.global.locale === "string"
    ? i18n.global.locale
    : i18n.global.locale.value,
);
const normalizedDefinitions = computed(() =>
  props.definitions?.length ? props.definitions : WFRP_CURRENCY_DEFINITIONS,
);
const activeDefinition = computed(() =>
  resolveCurrencyDefinition(normalizedDefinitions.value, props.currencyCode),
);
const activeCode = computed(() => activeDefinition.value.code);
const selectedCurrencyCode = computed({
  get: () => activeCode.value,
  set: (code) => emit("update:currencyCode", code),
});
const normalizedValue = computed(() =>
  Math.max(0, Math.floor(Number(props.modelValue) || 0)),
);
const currencyLabel = computed(() =>
  localizedCurrencyLabel(activeDefinition.value, locale.value),
);
const unitSymbol = (unit) =>
  String(locale.value).startsWith("pl")
    ? unit.symbolPl || unit.symbolEn || unit.code
    : unit.symbolEn || unit.symbolPl || unit.code;
const coinAsset = (unit) => coinAssets[unit.icon] || brassImg;
const coinEntries = computed(() =>
  activeDefinition.value.units.map((unit) => ({
    code: unit.code,
    amount: amounts[unit.code] || 0,
    symbol: unitSymbol(unit),
    iconSrc: coinAsset(unit),
  })),
);

const syncAmounts = () => {
  const next = decomposeCurrencyAmount(
    normalizedValue.value,
    activeDefinition.value,
  );
  Object.keys(amounts).forEach((key) => delete amounts[key]);
  Object.assign(amounts, next);
  if (!props.currencyCode && activeCode.value) {
    emit("update:currencyCode", activeCode.value);
  }
};
const unitLabel = (unit) =>
  String(locale.value).startsWith("pl")
    ? unit.labelPl || unit.labelEn || unit.code
    : unit.labelEn || unit.labelPl || unit.code;
const updateUnit = (code, value) => {
  amounts[code] = Math.max(0, Math.floor(Number(value) || 0));
  emit(
    "update:modelValue",
    composeCurrencyAmount(amounts, activeDefinition.value),
  );
};
const closePanel = (restoreFocus = false) => {
  if (!root.value?.open) return;
  root.value.open = false;
  if (restoreFocus) root.value.querySelector("summary")?.focus();
};
const closeOnOutsidePointer = (event) => {
  if (!root.value?.contains(event.target)) closePanel();
};

onMounted(() =>
  document.addEventListener("pointerdown", closeOnOutsidePointer),
);
onBeforeUnmount(() =>
  document.removeEventListener("pointerdown", closeOnOutsidePointer),
);

watch(
  () => [props.modelValue, props.currencyCode, props.definitions],
  syncAmounts,
  { immediate: true, deep: true },
);
</script>

<style scoped>
.system-price-input {
  position: relative;
  min-width: 0;
}
.system-price-input[open] {
  z-index: 90;
}
.system-price-input summary {
  display: grid;
  min-height: 2rem;
  cursor: pointer;
  list-style: none;
  border: 1px solid rgba(177, 129, 67, 0.55);
  border-radius: 0.25rem;
  background: rgba(16, 10, 6, 0.9);
  padding: 0.16rem 0.35rem;
  color: #f3dfb8;
  font-size: 0.72rem;
  line-height: 1.05;
}
.system-price-input__summary-coins {
  display: grid;
  min-width: 0;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  align-items: center;
  gap: 0.2rem;
}
.system-price-input__summary-coins > span {
  display: inline-grid;
  min-height: 1.45rem;
  min-width: 0;
  grid-template-columns: 1.05rem minmax(0, 1fr);
  align-items: center;
  gap: 0.12rem;
  border: 1px solid var(--currency-compartment-border);
  border-radius: 0.15rem;
  background: var(--currency-compartment-bg);
  box-shadow:
    inset 0 1px 1px rgba(255, 237, 190, 0.08),
    inset 0 -2px 4px rgba(0, 0, 0, 0.46);
  padding: 0.12rem 0.16rem;
}
.system-price-input__summary-coins > span:nth-child(1) {
  --currency-compartment-border: rgba(190, 142, 48, 0.42);
  --currency-compartment-bg: rgba(83, 58, 13, 0.24);
}
.system-price-input__summary-coins > span:nth-child(2) {
  --currency-compartment-border: rgba(190, 190, 181, 0.34);
  --currency-compartment-bg: rgba(105, 108, 104, 0.16);
}
.system-price-input__summary-coins > span:nth-child(3) {
  --currency-compartment-border: rgba(177, 105, 55, 0.42);
  --currency-compartment-bg: rgba(101, 48, 24, 0.2);
}
.system-price-input__summary-coins img,
.system-price-input__unit-label img {
  width: 1.05rem;
  height: 1.05rem;
  border-radius: 50%;
  object-fit: cover;
}
.system-price-input__summary-coins strong {
  min-width: 0;
  color: #fff4df;
  font-size: 0.68rem;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.015em;
  line-height: 1;
  text-align: right;
  white-space: nowrap;
}
.system-price-input__summary-coins small {
  display: none;
}
.system-price-input summary::-webkit-details-marker {
  display: none;
}
.system-price-input__region {
  color: #c7a66f;
  font-size: 0.58rem;
}
.system-price-input__panel {
  position: absolute;
  z-index: 80;
  top: calc(100% + 0.25rem);
  right: 0;
  display: grid;
  width: min(28rem, 80vw);
  gap: 0.45rem;
  border: 1px solid #b78142;
  border-radius: 0.35rem;
  background: #1b1009;
  box-shadow: 0 0.8rem 2rem #000b;
  padding: 0.55rem;
}
.system-price-input__panel label {
  display: grid;
  gap: 0.18rem;
  color: #cdb186;
  font-size: 0.65rem;
}
.system-price-input__unit-label {
  display: grid;
  grid-template-columns: 1.2rem minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.25rem;
}
.system-price-input__unit-label small {
  color: #d9b776;
  font-size: 0.58rem;
  text-transform: uppercase;
}
.system-price-input__panel select,
.system-price-input__panel input {
  min-height: 2rem;
  width: 100%;
  border: 1px solid rgba(177, 129, 67, 0.55);
  border-radius: 0.25rem;
  background: #100a06;
  color: #f4e5ca;
  color-scheme: dark;
  padding: 0.25rem 0.35rem;
}
.system-price-input__panel option {
  background: #21140c;
  color: #f4e5ca;
}
.system-price-input__units {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr));
  gap: 0.35rem;
}
.system-price-input__panel p {
  margin: 0;
  color: #a99472;
  font-size: 0.62rem;
}
</style>

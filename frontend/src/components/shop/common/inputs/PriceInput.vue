<!-- Responsibility: PriceInput shop interface component. -->
<template>
  <div class="field-edit-panel field-edit-panel--price">
    <div class="field-edit-selection-bar">
      {{
        $t("modals.fieldEdit.fields.prize.total", {
          total: totalBrass,
          crown,
          shilling,
          penny,
        })
      }}
    </div>

    <div
      class="tradingBrassLine bg-transparent text-light ih-50 d-flex align-items-center justify-content-end outline trade-modal__row-price field-edit-price-coinbar"
    >
      <div class="crown"></div>
      <div>
        <input type="text" readonly :value="displayCrown" />
      </div>
      <div class="shilling"></div>
      <div>
        <input type="text" readonly :value="displayShilling" />
      </div>
      <div class="brass"></div>
      <div>
        <input type="text" readonly :value="displayPenny" />
      </div>
    </div>

    <div class="field-edit-price-mode-wrap">
      <div class="field-edit-price-mode-label">
        {{ $t("modals.fieldEdit.fields.prize.modeLabel") }}
      </div>
      <div class="field-edit-price-modes" role="radiogroup">
        <button
          v-for="mode in coinModes"
          :key="`prize-mode-${mode}`"
          type="button"
          class="field-edit-price-mode"
          :class="{ active: selectedMode === mode }"
          role="radio"
          :aria-checked="String(selectedMode === mode)"
          :aria-label="$t(`modals.fieldEdit.fields.prize.modes.${mode}`)"
          :data-autofocus="mode === 'crown' ? 'true' : null"
          @click="toggleMode(mode)"
        >
          <span
            class="field-edit-price-mode__coin"
            :class="coinModeClass(mode)"
          ></span>
          <span class="field-edit-price-mode__label">{{
            $t(`modals.fieldEdit.fields.prize.modes.${mode}`)
          }}</span>
        </button>
      </div>
      <div class="field-edit-price-total-mode-note">
        {{ $t("modals.fieldEdit.fields.prize.totalPennyHint") }}
      </div>
    </div>

    <div class="field-edit-price-stepper-wrap">
      <button
        type="button"
        class="field-edit-stepper__btn field-edit-price-stepper-btn"
        :aria-label="$t('modals.fieldEdit.fields.prize.decreaseByStep')"
        @click="applyStep(-1)"
      >
        -
      </button>
      <div class="field-edit-price-stepper-status">
        <div class="field-edit-price-stepper-status__label">
          {{
            $t("modals.fieldEdit.fields.prize.stepLabel", {
              step: formattedStep,
            })
          }}
        </div>
        <div class="field-edit-price-stepper-status__mode">
          {{ activeModeLabel }}
        </div>
      </div>
      <button
        type="button"
        class="field-edit-stepper__btn field-edit-price-stepper-btn"
        :aria-label="$t('modals.fieldEdit.fields.prize.increaseByStep')"
        @click="applyStep(1)"
      >
        +
      </button>
    </div>

    <div class="field-edit-price-presets">
      <button
        v-for="preset in activePresets"
        :key="`price-preset-${modeKey}-${preset}`"
        type="button"
        class="field-edit-suggestion field-edit-price-preset"
        :class="{ active: Number(activeStep) === Number(preset) }"
        :aria-label="
          $t('modals.fieldEdit.fields.prize.selectPresetAria', {
            value: presetLabel(preset),
          })
        "
        @click="selectPreset(preset)"
      >
        <span class="field-edit-price-preset__value">{{
          presetLabel(preset)
        }}</span>
      </button>
    </div>

    <div v-if="showTotalCapHint" class="field-edit-price-cap-note">
      {{ $t("modals.fieldEdit.fields.prize.totalPennyCapHint") }}
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import i18n from "@/i18n";

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: 0,
  },
});

const emit = defineEmits(["update:modelValue"]);
const t = (key, values = {}) => i18n.global.t(key, values);

const PENNY_CAP_TOTAL_MODE = 1000;
const coinModes = ["crown", "shilling", "penny"];
const modePresets = {
  crown: [1, 2, 5, 10, 25, 50, 100],
  shilling: [1, 2, 3, 6, 12, 24, 48],
  penny: [1, 2, 3, 6, 9, 12],
  total: [12, 24, 60, 120, 240, 480, 720, 1000],
};

const selectedMode = ref("");
const activeStep = ref(modePresets.total[0]);
const totalBrass = ref(0);
const showTotalCapHint = ref(false);

const toNonNegativeInteger = (value, fallback = 0) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) {
    return fallback;
  }
  return Math.max(0, Math.floor(parsed));
};

const syncFromModel = (value) => {
  totalBrass.value = toNonNegativeInteger(value, 0);
};

watch(
  () => props.modelValue,
  (value) => {
    syncFromModel(value);
  },
  { immediate: true },
);

watch(totalBrass, (value) => {
  emit("update:modelValue", toNonNegativeInteger(value, 0));
});

const crown = computed(() => Math.floor(totalBrass.value / 240));
const shilling = computed(() => Math.floor((totalBrass.value % 240) / 12));
const penny = computed(() => totalBrass.value % 12);

const displayCrown = computed(
  () => `${crown.value} ${t("modals.fieldEdit.fields.prize.units.crownShort")}`,
);
const displayShilling = computed(
  () =>
    `${shilling.value} ${t("modals.fieldEdit.fields.prize.units.shillingShort")}`,
);
const displayPenny = computed(
  () => `${penny.value} ${t("modals.fieldEdit.fields.prize.units.pennyShort")}`,
);

const modeKey = computed(() =>
  selectedMode.value ? selectedMode.value : "total",
);
const activePresets = computed(() => modePresets[modeKey.value] || []);

const activeModeLabel = computed(() => {
  if (!selectedMode.value) {
    return t("modals.fieldEdit.fields.prize.totalPennyModeLabel");
  }
  return t(`modals.fieldEdit.fields.prize.modes.${selectedMode.value}`);
});

const modeMultiplier = computed(() => {
  if (selectedMode.value === "crown") {
    return 240;
  }
  if (selectedMode.value === "shilling") {
    return 12;
  }
  return 1;
});

const stepUnitLabel = computed(() => {
  if (selectedMode.value === "crown") {
    return t("modals.fieldEdit.fields.prize.crown");
  }
  if (selectedMode.value === "shilling") {
    return t("modals.fieldEdit.fields.prize.shilling");
  }
  return t("modals.fieldEdit.fields.prize.penny");
});

const formattedStep = computed(() =>
  t("modals.fieldEdit.fields.prize.stepValue", {
    value: Number(activeStep.value || 0),
    unit: stepUnitLabel.value,
  }),
);

const stepSizeInPennies = computed(() => {
  return (
    Math.max(1, toNonNegativeInteger(activeStep.value, 1)) *
    modeMultiplier.value
  );
});

const setDefaultStepForMode = () => {
  const presets = activePresets.value;
  if (!presets.length) {
    activeStep.value = 1;
    return;
  }
  activeStep.value = presets[0];
};

const toggleMode = (mode) => {
  if (selectedMode.value === mode) {
    selectedMode.value = "";
  } else {
    selectedMode.value = mode;
  }
  showTotalCapHint.value = false;
  setDefaultStepForMode();
};

const selectPreset = (value) => {
  activeStep.value = Math.max(1, toNonNegativeInteger(value, 1));
  showTotalCapHint.value = false;
};

const applyStep = (direction) => {
  const delta = Math.sign(direction) * stepSizeInPennies.value;
  if (!delta) {
    return;
  }
  let nextValue = toNonNegativeInteger(
    totalBrass.value + delta,
    totalBrass.value,
  );
  if (!selectedMode.value && delta > 0 && nextValue > PENNY_CAP_TOTAL_MODE) {
    nextValue = PENNY_CAP_TOTAL_MODE;
    showTotalCapHint.value = true;
  } else {
    showTotalCapHint.value = false;
  }
  totalBrass.value = Math.max(0, nextValue);
};

const presetLabel = (value) => {
  const amount = toNonNegativeInteger(value, 0);
  let unit = t("modals.fieldEdit.fields.prize.penny");
  if (selectedMode.value === "crown") {
    unit = t("modals.fieldEdit.fields.prize.crown");
  } else if (selectedMode.value === "shilling") {
    unit = t("modals.fieldEdit.fields.prize.shilling");
  }
  return t("modals.fieldEdit.fields.prize.presetValue", {
    value: amount,
    unit,
  });
};

const coinModeClass = (mode) => {
  if (mode === "penny") {
    return "brass";
  }
  return mode;
};

setDefaultStepForMode();
</script>

<!-- Responsibility: ChargeInput shop interface component. -->
<template>
  <div class="field-edit-panel field-edit-panel--charge">
    <div
      class="field-edit-charge-hero"
      :class="`field-edit-charge-hero--${weightTier.key}`"
    >
      <span class="field-edit-charge-hero__shine"></span>
      <div class="field-edit-charge-hero__badge">
        <span class="bi" :class="weightTier.icon" aria-hidden="true"></span>
      </div>
      <div class="field-edit-charge-hero__content">
        <div class="field-edit-charge-hero__title">
          {{ $t("modals.fieldEdit.fields.charge.heroTitle") }}
        </div>
        <div class="field-edit-charge-hero__value">
          {{
            $t("modals.fieldEdit.fields.charge.currentValue", {
              value: chargeValue,
              unit: unitLabel,
            })
          }}
        </div>
        <div class="field-edit-charge-hero__status">
          {{ weightTier.label }}
        </div>
        <div class="field-edit-charge-hero__description">
          {{ weightTier.description }}
        </div>
      </div>
    </div>

    <div class="field-edit-selection-bar field-edit-selection-bar--charge">
      {{
        $t("modals.fieldEdit.fields.charge.stepLabel", {
          step: $t("modals.fieldEdit.fields.charge.stepValue", {
            value: activeStep,
            unit: unitLabel,
          }),
        })
      }}
    </div>

    <div class="field-edit-charge-controls">
      <label class="trade-label" for="field-edit-charge-value">{{
        $t("modals.fieldEdit.fields.charge.value")
      }}</label>
      <div class="field-edit-stepper field-edit-charge-stepper">
        <button
          type="button"
          class="field-edit-stepper__btn"
          :aria-label="$t('modals.fieldEdit.fields.charge.decreaseByStep')"
          @click="applyStep(-1)"
        >
          -
        </button>
        <input
          id="field-edit-charge-value"
          v-model.number="chargeValue"
          type="number"
          min="0"
          step="1"
          class="form-control-sm trade-input field-edit-stepper__input"
          data-autofocus="true"
          @input="normalizeInput"
        />
        <button
          type="button"
          class="field-edit-stepper__btn"
          :aria-label="$t('modals.fieldEdit.fields.charge.increaseByStep')"
          @click="applyStep(1)"
        >
          +
        </button>
      </div>
    </div>

    <div class="field-edit-price-presets field-edit-charge-presets">
      <button
        v-for="preset in chargePresets"
        :key="`charge-preset-${preset}`"
        type="button"
        class="field-edit-suggestion field-edit-charge-preset"
        :class="{ active: Number(activeStep) === Number(preset) }"
        :aria-label="
          $t('modals.fieldEdit.fields.charge.selectPresetAria', {
            value: preset,
            unit: unitLabel,
          })
        "
        @click="selectPreset(preset)"
      >
        <span class="field-edit-charge-preset__value">{{
          $t("modals.fieldEdit.fields.charge.presetLabel", {
            value: preset,
            unit: unitLabel,
          })
        }}</span>
      </button>
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

const chargePresets = [1, 3, 5, 10, 20, 30, 50, 75, 100, 150, 200];
const chargeValue = ref(0);
const activeStep = ref(chargePresets[0]);

const toNonNegativeInteger = (value, fallback = 0) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) {
    return fallback;
  }
  return Math.max(0, Math.floor(parsed));
};

watch(
  () => props.modelValue,
  (value) => {
    chargeValue.value = toNonNegativeInteger(value, 0);
  },
  { immediate: true },
);

watch(chargeValue, (value) => {
  emit("update:modelValue", toNonNegativeInteger(value, 0));
});

const unitLabel = computed(() => t("modals.fieldEdit.fields.charge.unit"));

const weightTier = computed(() => {
  const value = toNonNegativeInteger(chargeValue.value, 0);
  if (value <= 10) {
    return {
      key: "light",
      icon: "bi-feather",
      label: t("modals.fieldEdit.fields.charge.tiers.light"),
      description: t("modals.fieldEdit.fields.charge.tiersDesc.light"),
    };
  }
  if (value <= 50) {
    return {
      key: "medium",
      icon: "bi-briefcase-fill",
      label: t("modals.fieldEdit.fields.charge.tiers.medium"),
      description: t("modals.fieldEdit.fields.charge.tiersDesc.medium"),
    };
  }
  if (value <= 100) {
    return {
      key: "heavy",
      icon: "bi-box-fill",
      label: t("modals.fieldEdit.fields.charge.tiers.heavy"),
      description: t("modals.fieldEdit.fields.charge.tiersDesc.heavy"),
    };
  }
  return {
    key: "veryHeavy",
    icon: "bi-exclamation-triangle-fill",
    label: t("modals.fieldEdit.fields.charge.tiers.veryHeavy"),
    description: t("modals.fieldEdit.fields.charge.tiersDesc.veryHeavy"),
  };
});

const normalizeInput = () => {
  chargeValue.value = toNonNegativeInteger(chargeValue.value, 0);
};

const selectPreset = (value) => {
  activeStep.value = Math.max(1, toNonNegativeInteger(value, 1));
};

const applyStep = (direction) => {
  const step = Math.max(1, toNonNegativeInteger(activeStep.value, 1));
  const next =
    toNonNegativeInteger(chargeValue.value, 0) + Math.sign(direction) * step;
  chargeValue.value = Math.max(0, next);
};
</script>

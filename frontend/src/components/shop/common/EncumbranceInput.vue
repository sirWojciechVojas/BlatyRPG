<!-- Responsibility: EncumbranceInput shop interface component. -->
<template>
  <div class="input-group input-group-sm encumbrance-input">
    <span class="input-group-text" aria-hidden="true">⚖</span>
    <select
      class="form-select form-select-sm"
      :value="selectedPreset"
      :aria-label="t('shop.workspace.encumbrance.preset')"
      @change="selectPreset($event.target.value)"
    >
      <option
        v-for="preset in presets"
        :key="preset.value"
        :value="preset.value"
      >
        {{ presetLabel(preset) }} — {{ preset.value }} {{ unitShort }}
      </option>
      <option value="custom">
        {{ t("shop.workspace.encumbrance.custom") }}
      </option>
    </select>
    <input
      v-if="selectedPreset === 'custom'"
      class="form-control form-control-sm"
      type="number"
      min="0"
      step="1"
      :value="normalizedValue"
      :aria-label="t('shop.workspace.encumbrance.customValue')"
      @input="updateCustom($event.target.value)"
    />
  </div>
</template>

<script setup>
import { computed } from "vue";
import i18n from "@/i18n";

const props = defineProps({
  modelValue: { type: [Number, String], default: 0 },
  definition: { type: Object, default: () => ({ presets: [] }) },
});
const emit = defineEmits(["update:modelValue"]);
const t = (key, values = {}) => i18n.global.t(key, values);
const locale = computed(() =>
  typeof i18n.global.locale === "string"
    ? i18n.global.locale
    : i18n.global.locale.value,
);
const normalizedValue = computed(() =>
  Math.max(0, Math.round(Number(props.modelValue) || 0)),
);
const presets = computed(() => props.definition?.presets || []);
const unitShort = computed(() => props.definition?.unitShort || "");
const selectedPreset = computed(() =>
  presets.value.some((preset) => Number(preset.value) === normalizedValue.value)
    ? String(normalizedValue.value)
    : "custom",
);
const presetLabel = (preset) =>
  String(locale.value).startsWith("pl")
    ? preset.labelPl || preset.labelEn
    : preset.labelEn || preset.labelPl;
const selectPreset = (value) => {
  if (value !== "custom") emit("update:modelValue", Number(value));
};
const updateCustom = (value) =>
  emit("update:modelValue", Math.max(0, Math.round(Number(value) || 0)));
</script>

<style scoped>
.encumbrance-input {
  flex-wrap: nowrap;
}
.encumbrance-input .input-group-text {
  border-color: #638b5c;
  background: #2c4428;
  color: #dceccf;
}
.encumbrance-input select,
.encumbrance-input input {
  min-height: 2rem;
  border-color: #638b5c;
  background-color: #111a0f;
  color: #e9f3df;
  color-scheme: dark;
  font-size: 0.68rem;
}
.encumbrance-input input {
  max-width: 5rem;
}
</style>

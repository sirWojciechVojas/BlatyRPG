<!-- Responsibility: DomainCombobox shop interface component. -->
<template>
  <select
    class="form-select form-select-sm domain-combobox"
    :class="`domain-combobox--${tone}`"
    :value="modelValue"
    v-bind="$attrs"
    @change="$emit('update:modelValue', $event.target.value)"
  >
    <option v-if="placeholder" value="">{{ placeholder }}</option>
    <option
      v-for="entry in options"
      :key="entry.code ?? entry.value"
      :value="entry.code ?? entry.value"
    >
      {{ optionLabel(entry) }}
    </option>
  </select>
</template>

<script setup>
import { computed } from "vue";
import i18n from "@/i18n";

defineOptions({ inheritAttrs: false });
const props = defineProps({
  modelValue: { type: [String, Number], default: "" },
  options: { type: Array, default: () => [] },
  tone: { type: String, default: "amber" },
  placeholder: { type: String, default: "" },
  includeCode: { type: Boolean, default: true },
});
defineEmits(["update:modelValue"]);
const locale = computed(() =>
  typeof i18n.global.locale === "string"
    ? i18n.global.locale
    : i18n.global.locale.value,
);
const optionLabel = (entry) => {
  const code = String(entry.code ?? entry.value ?? "");
  const label = String(locale.value).startsWith("pl")
    ? entry.labelPl || entry.labelEn || entry.label || code
    : entry.labelEn || entry.labelPl || entry.label || code;
  return props.includeCode && code && label !== code
    ? `${label} (${code})`
    : label;
};
</script>

<style scoped>
.domain-combobox {
  min-height: 2rem;
  border-width: 1px;
  background-color: #160d08;
  color: #f6e7ca;
  color-scheme: dark;
  font-size: 0.72rem;
}
.domain-combobox option {
  background: #21140c;
  color: #f4e3c5;
}
.domain-combobox--class {
  border-color: #d49b43;
  box-shadow: inset 3px 0 #d49b43;
}
.domain-combobox--genre {
  border-color: #4ca6bc;
  box-shadow: inset 3px 0 #4ca6bc;
}
.domain-combobox--attribute {
  border-color: #9d70c7;
  box-shadow: inset 3px 0 #9d70c7;
}
.domain-combobox--location {
  border-color: #6ca365;
  box-shadow: inset 3px 0 #6ca365;
}
.domain-combobox--currency {
  border-color: #d1aa55;
  box-shadow: inset 3px 0 #d1aa55;
}
.domain-combobox:focus {
  border-color: #f1ca7a;
  box-shadow: 0 0 0 0.14rem rgba(224, 175, 93, 0.25);
}
</style>

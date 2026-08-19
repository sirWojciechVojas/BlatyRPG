<!-- Pole wyboru pojedynczego kryterium wyjątku polityki cenowej w edytorze sklepu GM. -->
<template>
  <label class="shop-editor-pricing__field">
    <span>
      {{ label }}
      <ShopHelpTooltip v-if="tooltip" :label="label" :text="tooltip" />
    </span>
    <select
      class="form-control-sm trade-input"
      :value="modelValue"
      @change="emit('update:modelValue', $event.target.value)"
    >
      <option value="">{{ anyLabel }}</option>
      <option
        v-for="option in normalizedOptions"
        :key="String(option.value)"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>
  </label>
</template>

<script setup>
import { computed } from "vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  modelValue: {
    type: [String, Number],
    default: "",
  },
  options: {
    type: Array,
    default: () => [],
  },
  anyLabel: {
    type: String,
    required: true,
  },
  tooltip: {
    type: String,
    default: "",
  },
});

const emit = defineEmits(["update:modelValue"]);
const normalizedOptions = computed(() =>
  props.options.map((option) =>
    option && typeof option === "object"
      ? option
      : { value: option, label: String(option) },
  ),
);
</script>

<!-- Responsibility: QuantityControl shop interface component. -->
<template>
  <div :class="wrapperClass">
    <label class="assort-label" :for="id">{{ resolvedLabel }}</label>
    <input
      :id="id"
      type="number"
      :min="min"
      :class="inputClass"
      :value="modelValue"
      @input="handleInput"
    />
  </div>
</template>

<script>
export default {
  name: "QuantityControl",
  props: {
    modelValue: {
      type: [Number, String],
      default: 1,
    },
    id: {
      type: String,
      required: true,
    },
    label: {
      type: String,
      default: "",
    },
    min: {
      type: Number,
      default: 1,
    },
    inputClass: {
      type: String,
      default: "assort-input",
    },
    wrapperClass: {
      type: String,
      default: "assort-field",
    },
  },
  emits: ["update:modelValue", "qty-change"],
  computed: {
    resolvedLabel() {
      return this.label || this.$t("shop.common.quantity");
    },
  },
  methods: {
    handleInput(event) {
      const rawValue = event?.target?.value ?? "";
      const parsed = Number(rawValue);
      const next = Number.isNaN(parsed) ? rawValue : parsed;
      this.$emit("update:modelValue", next);
      this.$emit("qty-change", next);
    },
  },
};
</script>

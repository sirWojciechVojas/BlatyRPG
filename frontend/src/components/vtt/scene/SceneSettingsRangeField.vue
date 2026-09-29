<template>
  <div class="scene-settings__field">
    <label :for="id">{{ label }}</label>
    <div class="scene-settings__range">
      <input
        :id="id"
        :value="modelValue"
        type="range"
        :min="minimum"
        :max="maximum"
        :step="step"
        @input="$emit('update:modelValue', Number($event.target.value))"
      />
      <div v-if="percent" class="scene-settings__percentage-input">
        <input
          :id="`${id}-percent`"
          :value="percentValue"
          type="number"
          inputmode="decimal"
          :min="percentMinimum"
          :max="percentMaximum"
          :step="percentStep"
          :aria-label="`${label} (%)`"
          @input="updatePercent"
          @blur="normalizePercentInput"
        />
        <span aria-hidden="true">%</span>
      </div>
      <output v-else :for="id">{{ output }}</output>
    </div>
    <p v-if="error" class="scene-settings__field-error" role="alert">
      {{ error }}
    </p>
  </div>
</template>

<script>
export default {
  name: "SceneSettingsRangeField",
  props: {
    id: { type: String, required: true },
    modelValue: { type: Number, required: true },
    label: { type: String, required: true },
    minimum: { type: Number, required: true },
    maximum: { type: Number, required: true },
    step: { type: Number, required: true },
    suffix: { type: String, default: "" },
    percent: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  emits: ["update:modelValue"],
  computed: {
    percentValue() {
      return Math.round(this.modelValue * 100);
    },
    percentMinimum() {
      return this.minimum * 100;
    },
    percentMaximum() {
      return this.maximum * 100;
    },
    percentStep() {
      return this.step * 100;
    },
    output() {
      return `${this.modelValue}${this.suffix ? ` ${this.suffix}` : ""}`;
    },
  },
  methods: {
    updatePercent(event) {
      const value = event.target.valueAsNumber;
      if (!Number.isFinite(value)) return;
      const clamped = Math.min(
        this.percentMaximum,
        Math.max(this.percentMinimum, value),
      );
      event.target.value = String(clamped);
      this.$emit("update:modelValue", clamped / 100);
    },
    normalizePercentInput(event) {
      event.target.value = String(this.percentValue);
    },
  },
};
</script>

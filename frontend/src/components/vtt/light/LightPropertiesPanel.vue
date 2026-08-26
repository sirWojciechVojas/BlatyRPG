<template>
  <form class="light-properties" @pointerdown.stop @submit.prevent="save">
    <header>
      <strong>{{ $t("vtt.light.properties") }}</strong>
      <button
        type="button"
        :title="$t('vtt.light.close')"
        @click="$emit('close')"
      >
        ×
      </button>
    </header>
    <div class="light-properties__grid">
      <label>
        <span>{{ $t("vtt.light.sourceType") }}</span>
        <select v-model="form.sourceType">
          <option value="light">{{ $t("vtt.light.types.light") }}</option>
          <option value="darkness">{{ $t("vtt.light.types.darkness") }}</option>
        </select>
      </label>
      <label>
        <span>{{ $t("vtt.light.color") }}</span>
        <input v-model="form.color" type="color" />
      </label>
      <label v-for="field in numberFields" :key="field.key">
        <span>{{ $t(field.label) }}</span>
        <input
          v-model.number="form[field.key]"
          type="number"
          :min="field.min"
          :max="field.max"
          :step="field.step"
          required
        />
      </label>
      <label>
        <span>{{ $t("vtt.light.animation") }}</span>
        <select v-model="form.animation">
          <option
            v-for="animation in animations"
            :key="animation"
            :value="animation"
          >
            {{ $t(`vtt.light.animations.${animation}`) }}
          </option>
        </select>
      </label>
    </div>
    <div class="light-properties__toggles">
      <label v-for="field in booleanFields" :key="field.key">
        <input v-model="form[field.key]" type="checkbox" />
        <span>{{ $t(field.label) }}</span>
      </label>
    </div>
    <footer>
      <button type="button" :disabled="busy" @click="reset">
        {{ $t("vtt.light.cancel") }}
      </button>
      <button type="submit" :disabled="busy">{{ $t("vtt.light.save") }}</button>
    </footer>
  </form>
</template>

<script>
const EDIT_FIELDS = [
  "brightRadius",
  "dimRadius",
  "color",
  "intensity",
  "opacity",
  "softness",
  "gradualIllumination",
  "darknessMin",
  "darknessMax",
  "sourceType",
  "providesVision",
  "constrainedByWalls",
  "animation",
  "animationSpeed",
  "animationIntensity",
  "elevation",
  "enabled",
];

export default {
  name: "LightPropertiesPanel",
  props: {
    light: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["close", "save"],
  data: () => ({
    form: {},
    animations: ["none", "flicker", "pulse", "vortex"],
    numberFields: [
      {
        key: "brightRadius",
        label: "vtt.light.bright",
        min: 0,
        max: 100000,
        step: 1,
      },
      {
        key: "dimRadius",
        label: "vtt.light.dim",
        min: 0,
        max: 100000,
        step: 1,
      },
      {
        key: "intensity",
        label: "vtt.light.intensity",
        min: 0,
        max: 1,
        step: 0.05,
      },
      {
        key: "opacity",
        label: "vtt.light.opacity",
        min: 0,
        max: 1,
        step: 0.05,
      },
      {
        key: "softness",
        label: "vtt.light.softness",
        min: 0,
        max: 1,
        step: 0.05,
      },
      {
        key: "darknessMin",
        label: "vtt.light.darknessMin",
        min: 0,
        max: 1,
        step: 0.05,
      },
      {
        key: "darknessMax",
        label: "vtt.light.darknessMax",
        min: 0,
        max: 1,
        step: 0.05,
      },
      {
        key: "animationSpeed",
        label: "vtt.light.animationSpeed",
        min: 0.1,
        max: 10,
        step: 0.1,
      },
      {
        key: "animationIntensity",
        label: "vtt.light.animationIntensity",
        min: 0,
        max: 1,
        step: 0.05,
      },
      {
        key: "elevation",
        label: "vtt.light.elevation",
        min: -1000000,
        max: 1000000,
        step: 1,
      },
    ],
    booleanFields: [
      { key: "enabled", label: "vtt.light.enabled" },
      { key: "gradualIllumination", label: "vtt.light.gradual" },
      { key: "providesVision", label: "vtt.light.providesVision" },
      { key: "constrainedByWalls", label: "vtt.light.constrained" },
    ],
  }),
  watch: {
    "light.id": {
      immediate: true,
      handler() {
        this.reset();
      },
    },
  },
  methods: {
    reset() {
      this.form = Object.fromEntries(
        EDIT_FIELDS.map((field) => [field, this.light[field]]),
      );
    },
    save() {
      const changes = { ...this.form };
      changes.brightRadius = Math.min(changes.brightRadius, changes.dimRadius);
      changes.darknessMin = Math.min(changes.darknessMin, changes.darknessMax);
      this.$emit("save", changes);
    },
  },
};
</script>

<template>
  <form class="light-properties" @pointerdown.stop @submit.prevent="save">
    <header>
      <strong>{{ $t("vtt.light.properties") }}</strong>
      <button type="button" :title="$t('vtt.light.close')" @click="$emit('close')">×</button>
    </header>
    <nav class="light-properties__groups">
      <button
        v-for="group in groups" :key="group" type="button"
        :class="{ active: activeGroup === group }"
        @click="activeGroup = group"
      >{{ $t(`vtt.light.groups.${group}`) }}</button>
    </nav>
    <section class="light-properties__grid">
      <label v-if="activeGroup === 'basic'" class="light-properties__wide">
        <span>{{ $t("vtt.light.name") }}</span>
        <input v-model.trim="form.name" type="text" maxlength="100" required />
      </label>
      <label v-if="activeGroup === 'basic'">
        <span>{{ $t("vtt.light.sourceType") }}</span>
        <select v-model="form.sourceType">
          <option v-for="type in types" :key="type" :value="type">
            {{ $t(`vtt.light.types.${type}`) }}
          </option>
        </select>
      </label>
      <label v-if="activeGroup === 'light'">
        <span>{{ $t("vtt.light.color") }}</span>
        <input v-model="form.color" type="color" />
      </label>
      <label v-if="activeGroup === 'animation'">
        <span>{{ $t("vtt.light.animation") }}</span>
        <select v-model="form.animation">
          <option v-for="animation in animations" :key="animation" :value="animation">
            {{ $t(`vtt.light.animations.${animation}`) }}
          </option>
        </select>
      </label>
      <label v-for="field in activeNumberFields" :key="field.key">
        <span>{{ $t(field.label) }}</span>
        <input
          v-model.number="form[field.key]" type="number"
          :min="field.min" :max="field.max" :step="field.step" required
        />
      </label>
    </section>
    <div v-if="activeBooleanFields.length" class="light-properties__toggles">
      <label v-for="field in activeBooleanFields" :key="field.key">
        <input v-model="form[field.key]" type="checkbox" />
        <span>{{ $t(field.label) }}</span>
      </label>
    </div>
    <p v-if="activeGroup === 'light'" class="light-properties__hint">
      {{ $t("vtt.light.lumensHint") }}
    </p>
    <footer>
      <button type="button" :disabled="busy" @click="reset">
        {{ $t("vtt.light.cancel") }}
      </button>
      <button type="submit" :disabled="busy">{{ $t("vtt.light.save") }}</button>
    </footer>
  </form>
</template>

<script>
import {
  LIGHT_ANIMATIONS,
  LIGHT_SETTING_GROUPS,
  LIGHT_TYPES,
} from "@/lib/vtt/lightOptions";

const EDIT_FIELDS = [
  "name", "sourceType", "lumens", "direction", "angle", "areaWidth",
  "areaHeight", "brightRadius", "dimRadius", "color", "opacity", "softness",
  "gradualIllumination", "darknessMin", "darknessMax", "providesVision",
  "constrainedByWalls", "animation", "animationSpeed", "animationIntensity",
  "elevation", "enabled", "hidden",
];

const numberFields = [
  ["geometry", "brightRadius", "vtt.light.bright", 0, 100000, 1, "radial"],
  ["geometry", "dimRadius", "vtt.light.dim", 0, 100000, 1, "radial"],
  ["geometry", "direction", "vtt.light.direction", 0, 360, 1, "directed"],
  ["geometry", "angle", "vtt.light.angle", 1, 360, 1, "directed"],
  ["geometry", "areaWidth", "vtt.light.areaWidth", 1, 100000, 1, "area"],
  ["geometry", "areaHeight", "vtt.light.areaHeight", 1, 100000, 1, "area"],
  ["light", "lumens", "vtt.light.lumens", 0, 1000000, 50],
  ["light", "softness", "vtt.light.softness", 0, 1, 0.05, "gradual"],
  ["animation", "animationSpeed", "vtt.light.animationSpeed", 0.1, 10, 0.1, "animated"],
  ["animation", "animationIntensity", "vtt.light.animationIntensity", 0, 1, 0.05, "animated"],
  ["advanced", "opacity", "vtt.light.opacity", 0, 1, 0.05],
  ["advanced", "darknessMin", "vtt.light.darknessMin", 0, 1, 0.05],
  ["advanced", "darknessMax", "vtt.light.darknessMax", 0, 1, 0.05],
  ["advanced", "elevation", "vtt.light.elevation", -1000000, 1000000, 1],
].map(([group, key, label, min, max, step, when]) => ({
  group, key, label, min, max, step, when,
}));

const booleanFields = [
  ["basic", "enabled", "vtt.light.enabled"],
  ["light", "gradualIllumination", "vtt.light.gradual"],
  ["vision", "providesVision", "vtt.light.providesVision"],
  ["vision", "constrainedByWalls", "vtt.light.constrained"],
  ["advanced", "hidden", "vtt.light.hidden"],
].map(([group, key, label]) => ({ group, key, label }));

export default {
  name: "LightPropertiesPanel",
  props: {
    light: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["close", "save"],
  data: () => ({
    form: {}, activeGroup: "basic", groups: LIGHT_SETTING_GROUPS,
    types: LIGHT_TYPES, animations: LIGHT_ANIMATIONS,
  }),
  computed: {
    activeNumberFields() {
      return numberFields.filter((field) =>
        field.group === this.activeGroup && this.visible(field.when),
      );
    },
    activeBooleanFields() {
      return booleanFields.filter((field) => field.group === this.activeGroup);
    },
  },
  watch: {
    "light.id": { immediate: true, handler: "reset" },
  },
  methods: {
    visible(condition) {
      if (!condition) return true;
      if (condition === "radial") return this.form.sourceType !== "area";
      if (condition === "directed") return ["directional", "cone"].includes(this.form.sourceType);
      if (condition === "area") return this.form.sourceType === "area";
      if (condition === "gradual") return this.form.gradualIllumination;
      return condition !== "animated" || this.form.animation !== "none";
    },
    reset() {
      this.form = Object.fromEntries(EDIT_FIELDS.map((field) => [field, this.light[field]]));
    },
    save() {
      const changes = { ...this.form, lumens: Math.round(this.form.lumens) };
      changes.brightRadius = Math.min(changes.brightRadius, changes.dimRadius);
      changes.darknessMin = Math.min(changes.darknessMin, changes.darknessMax);
      this.$emit("save", changes);
    },
  },
};
</script>

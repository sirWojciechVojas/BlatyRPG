<template>
  <form
    class="light-properties"
    :class="{ 'light-properties--embedded': floating }"
    @pointerdown.stop
    @submit.prevent="save"
  >
    <header v-if="!floating">
      <strong>{{ $t("vtt.light.properties") }}</strong>
      <button
        type="button"
        :title="$t('vtt.light.close')"
        @click="$emit('close')"
      >
        ×
      </button>
    </header>
    <nav class="light-properties__groups">
      <button
        v-for="group in groups"
        :key="group"
        type="button"
        :class="{ active: activeGroup === group }"
        @click="activeGroup = group"
      >
        {{ $t(`vtt.light.groups.${group}`) }}
      </button>
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
          <option
            v-for="animation in animations"
            :key="animation"
            :value="animation"
          >
            {{ $t(`vtt.light.animations.${animation}`) }}
          </option>
        </select>
      </label>
      <label v-if="activeGroup === 'advanced'" class="light-properties__wide">
        <span>{{ $t("vtt.light.assetUrl") }}</span>
        <input
          v-model.trim="form.assetUrl"
          type="url"
          maxlength="2048"
          :placeholder="$t('vtt.light.assetUrlPlaceholder')"
        />
      </label>
      <label v-for="field in activeNumberFields" :key="field.key">
        <span>{{
          field.key === "clarity" ? field.label : $t(field.label)
        }}</span>
        <input
          v-model.number="form[field.key]"
          type="number"
          :min="field.min"
          :max="field.max"
          :step="field.step"
          required
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
    <p
      v-if="status !== 'idle'"
      class="light-properties__status"
      :class="`light-properties__status--${status}`"
      role="status"
    >
      {{ statusMessage }}
    </p>
    <footer>
      <button type="button" :disabled="saving" @click="reset">
        {{ $t("vtt.light.cancel") }}
      </button>
      <button type="submit" :disabled="saving">
        {{ saving ? $t("vtt.light.saving") : $t("vtt.light.save") }}
      </button>
    </footer>
  </form>
</template>

<script>
import {
  LIGHT_ANIMATIONS,
  LIGHT_SETTING_GROUPS,
  LIGHT_TYPES,
} from "@/lib/vtt/lightOptions";
import {
  changedLightProperties,
  lightPropertiesSnapshot,
  normalizeLightProperties,
} from "./lightPropertiesDraft";

const numberFields = [
  ["geometry", "brightRadius", "vtt.light.bright", 0, 100000, 1, "radial"],
  ["geometry", "dimRadius", "vtt.light.dim", 0, 100000, 1, "radial"],
  ["geometry", "direction", "vtt.light.direction", 0, 360, 1, "directed"],
  ["geometry", "angle", "vtt.light.angle", 1, 360, 1, "directed"],
  ["geometry", "areaWidth", "vtt.light.areaWidth", 1, 100000, 1, "area"],
  ["geometry", "areaHeight", "vtt.light.areaHeight", 1, 100000, 1, "area"],
  ["light", "lumens", "vtt.light.lumens", 0, 1000000, 50],
  ["light", "clarity", "Clarity", 0, 1, 0.05],
  ["light", "softness", "vtt.light.softness", 0, 1, 0.05, "gradual"],
  [
    "animation",
    "animationSpeed",
    "vtt.light.animationSpeed",
    0.1,
    10,
    0.1,
    "animated",
  ],
  [
    "animation",
    "animationIntensity",
    "vtt.light.animationIntensity",
    0,
    1,
    0.05,
    "animated",
  ],
  ["advanced", "opacity", "vtt.light.opacity", 0, 1, 0.05],
  ["advanced", "brightness", "vtt.light.brightness", 0, 2, 0.05],
  ["advanced", "saturation", "vtt.light.saturation", 0, 2, 0.05],
  ["advanced", "contrast", "vtt.light.contrast", 0, 2, 0.05],
  ["advanced", "edgeSoftness", "vtt.light.edgeSoftness", 0, 1, 0.05],
  ["advanced", "transitionRatio", "vtt.light.transitionRatio", 0, 1, 0.05],
  ["advanced", "darknessMin", "vtt.light.darknessMin", 0, 1, 0.05],
  ["advanced", "darknessMax", "vtt.light.darknessMax", 0, 1, 0.05],
  ["advanced", "elevation", "vtt.light.elevation", -1000000, 1000000, 1],
].map(([group, key, label, min, max, step, when]) => ({
  group,
  key,
  label,
  min,
  max,
  step,
  when,
}));

const booleanFields = [
  ["basic", "enabled", "vtt.light.enabled"],
  ["light", "gradualIllumination", "vtt.light.gradual"],
  ["vision", "providesVision", "vtt.light.providesVision"],
  ["vision", "constrainedByWalls", "vtt.light.constrained"],
  ["animation", "animationReverse", "vtt.light.animationReverse"],
  ["advanced", "hidden", "vtt.light.hidden"],
].map(([group, key, label]) => ({ group, key, label }));

export default {
  name: "LightPropertiesPanel",
  props: {
    light: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    status: { type: String, default: "idle" },
    error: { type: String, default: "" },
    floating: { type: Boolean, default: false },
  },
  emits: ["close", "save", "preview", "unchanged"],
  data: () => ({
    form: {},
    initial: {},
    ready: false,
    activeGroup: "basic",
    groups: LIGHT_SETTING_GROUPS,
    types: LIGHT_TYPES,
    animations: LIGHT_ANIMATIONS,
  }),
  computed: {
    saving() {
      return this.busy || this.status === "saving";
    },
    statusMessage() {
      if (this.status === "error") {
        return this.error || this.$t("vtt.light.saveError");
      }
      return this.$t(`vtt.light.${this.status}`);
    },
    activeNumberFields() {
      return numberFields.filter(
        (field) => field.group === this.activeGroup && this.visible(field.when),
      );
    },
    activeBooleanFields() {
      return booleanFields.filter((field) => field.group === this.activeGroup);
    },
  },
  watch: {
    "light.id": { immediate: true, handler: "reset" },
    form: {
      deep: true,
      handler() {
        if (this.ready) this.$emit("preview", this.changedFields());
      },
    },
  },
  methods: {
    visible(condition) {
      if (!condition) return true;
      if (condition === "radial") return this.form.sourceType !== "area";
      if (condition === "directed")
        return ["directional", "cone"].includes(this.form.sourceType);
      if (condition === "area") return this.form.sourceType === "area";
      if (condition === "gradual") return this.form.gradualIllumination;
      return condition !== "animated" || this.form.animation !== "none";
    },
    reset() {
      this.ready = false;
      this.initial = lightPropertiesSnapshot(this.light);
      this.form = { ...this.initial };
      this.$nextTick(() => {
        this.ready = true;
        this.$emit("preview", {});
      });
    },
    changedFields() {
      return changedLightProperties(this.initial, this.form);
    },
    save() {
      this.form = normalizeLightProperties(this.form);
      const changes = this.changedFields();
      if (!Object.keys(changes).length) {
        this.$emit("unchanged");
        return;
      }
      this.$emit("save", changes);
    },
  },
};
</script>

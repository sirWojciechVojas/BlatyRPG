<template>
  <form class="region-properties" @submit.prevent="save">
    <label>
      <span>{{ $t("vtt.region.name") }}</span>
      <input v-model.trim="form.name" maxlength="150" required />
    </label>
    <label>
      <span>{{ $t("vtt.region.darknessMode") }}</span>
      <select v-model="form.darknessMode">
        <option v-for="mode in modes" :key="mode" :value="mode">
          {{ $t(`vtt.region.modes.${mode}`) }}
        </option>
      </select>
    </label>
    <label>
      <span>{{ $t("vtt.region.darknessValue") }}</span>
      <input
        v-model.number="form.darknessValue"
        type="range"
        min="0"
        max="1"
        step="0.01"
      />
      <output>{{ Math.round(form.darknessValue * 100) }}%</output>
    </label>
    <label>
      <span>{{ $t("vtt.region.color") }}</span>
      <input v-model="form.color" type="color" />
    </label>
    <label class="region-properties__check">
      <input v-model="form.disableGlobalIllumination" type="checkbox" />
      <span>{{ $t("vtt.region.disableGlobalIllumination") }}</span>
    </label>
    <label class="region-properties__check">
      <input v-model="form.enabled" type="checkbox" />
      <span>{{ $t("vtt.region.enabled") }}</span>
    </label>
    <label class="region-properties__check">
      <input v-model="form.hidden" type="checkbox" />
      <span>{{ $t("vtt.region.hidden") }}</span>
    </label>
    <footer>
      <button type="button" @click="$emit('close')">
        {{ $t("vtt.region.cancel") }}
      </button>
      <button type="submit" :disabled="busy">
        {{ busy ? $t("vtt.region.saving") : $t("vtt.region.save") }}
      </button>
    </footer>
  </form>
</template>

<script>
export default {
  name: "RegionPropertiesPanel",
  props: {
    region: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["save", "close"],
  data() {
    return {
      modes: ["add", "subtract", "override"],
      form: this.snapshot(),
    };
  },
  watch: {
    "region.id"() {
      this.form = this.snapshot();
    },
  },
  methods: {
    snapshot() {
      return {
        name: this.region.name,
        darknessMode: this.region.darknessMode,
        darknessValue: Number(this.region.darknessValue) || 0,
        disableGlobalIllumination:
          this.region.disableGlobalIllumination === true,
        color: this.region.color || "#8B5CF6",
        enabled: this.region.enabled !== false,
        hidden: this.region.hidden === true,
      };
    },
    save() {
      const changes = Object.fromEntries(
        Object.entries(this.form).filter(
          ([key, value]) => !Object.is(value, this.region[key]),
        ),
      );
      if (Object.keys(changes).length) this.$emit("save", changes);
      else this.$emit("close");
    },
  },
};
</script>

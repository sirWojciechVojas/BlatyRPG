<template>
  <aside class="light-hud" @pointerdown.stop>
    <input
      type="color"
      :value="light.color.slice(0, 7)"
      :title="$t('vtt.light.color')"
      :disabled="busy"
      @change="$emit('update', { color: $event.target.value })"
    />
    <button
      type="button"
      :title="$t('vtt.light.copy')"
      :disabled="busy"
      @click="$emit('copy')"
    >
      ⧉
    </button>
    <button
      type="button"
      :title="$t('vtt.light.edit')"
      :disabled="busy"
      @click="$emit('edit')"
    >
      ⚙
    </button>
    <button
      v-for="control in radiusControls"
      :key="control.label"
      type="button"
      :title="$t(control.title)"
      :disabled="busy"
      @click="changeRadius(control.field, control.delta)"
    >
      {{ control.label }}
    </button>
    <button
      type="button"
      :class="{ active: light.enabled }"
      :title="$t('vtt.light.enabled')"
      :disabled="busy"
      @click="$emit('update', { enabled: !light.enabled })"
    >
      ◉
    </button>
    <button
      type="button"
      :class="{ active: light.hidden }"
      :title="$t('vtt.light.hidden')"
      :disabled="busy"
      @click="$emit('update', { hidden: !light.hidden })"
    >
      H
    </button>
    <label :title="$t('vtt.light.intensity')">
      <input
        type="range"
        min="0"
        max="1"
        step="0.1"
        :value="light.intensity"
        :disabled="busy"
        @change="$emit('update', { intensity: Number($event.target.value) })"
      />
    </label>
    <button
      type="button"
      class="light-hud__danger"
      :title="$t('vtt.light.delete')"
      :disabled="busy"
      @click="$emit('delete')"
    >
      ×
    </button>
  </aside>
</template>

<script>
export default {
  name: "LightHud",
  props: {
    light: { type: Object, required: true },
    gridSize: { type: Number, default: 100 },
    busy: { type: Boolean, default: false },
  },
  emits: ["update", "copy", "edit", "delete"],
  computed: {
    radiusControls() {
      return [
        {
          field: "brightRadius",
          delta: -1,
          label: "B−",
          title: "vtt.light.bright",
        },
        {
          field: "brightRadius",
          delta: 1,
          label: "B+",
          title: "vtt.light.bright",
        },
        { field: "dimRadius", delta: -1, label: "D−", title: "vtt.light.dim" },
        { field: "dimRadius", delta: 1, label: "D+", title: "vtt.light.dim" },
      ];
    },
  },
  methods: {
    changeRadius(field, direction) {
      const step = Math.max(1, Number(this.gridSize) || 100);
      let value = Math.max(0, Number(this.light[field]) + step * direction);
      if (field === "brightRadius")
        value = Math.min(value, this.light.dimRadius);
      if (field === "dimRadius")
        value = Math.max(value, this.light.brightRadius);
      this.$emit("update", { [field]: value });
    },
  },
};
</script>

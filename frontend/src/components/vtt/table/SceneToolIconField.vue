<template>
  <label
    ref="trigger"
    class="scene-tool-icon-field"
    :class="{
      'scene-tool-icon-field--active': active,
      'scene-tool-icon-field--disabled': disabled,
    }"
    @mouseenter="scheduleOpen"
    @mouseleave="closeTooltip"
    @focusin="scheduleOpen"
    @focusout="closeTooltip"
  >
    <TableRailIcon :name="icon" />
    <select
      v-if="kind === 'select'"
      :value="modelValue"
      :disabled="disabled"
      :aria-label="accessibleLabel"
      @change="$emit('change', $event.target.value)"
    >
      <option
        v-for="option in options"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>
    <input
      v-else
      type="color"
      :value="modelValue"
      :disabled="disabled"
      :aria-label="accessibleLabel"
      @change="$emit('change', $event.target.value)"
    />
    <span
      v-if="kind === 'color'"
      class="scene-tool-icon-field__swatch"
      :style="{ backgroundColor: modelValue }"
    />
  </label>
  <Teleport to="body">
    <Transition name="scene-tool-tooltip">
      <span
        v-if="open"
        class="scene-tool-tooltip"
        :style="tooltipStyle"
        role="tooltip"
      >
        <strong>{{ label }}</strong>
        <small v-if="description">{{ description }}</small>
      </span>
    </Transition>
  </Teleport>
</template>

<script>
import TableRailIcon from "./TableRailIcon.vue";

export default {
  name: "SceneToolIconField",
  components: { TableRailIcon },
  props: {
    icon: { type: String, required: true },
    label: { type: String, required: true },
    description: { type: String, default: "" },
    kind: { type: String, default: "select" },
    modelValue: { type: [String, Number], default: "" },
    options: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    active: { type: Boolean, default: false },
  },
  emits: ["change"],
  data: () => ({ open: false, openTimer: null, tooltipStyle: {} }),
  computed: {
    accessibleLabel() {
      return this.description
        ? `${this.label}. ${this.description}`
        : this.label;
    },
  },
  beforeUnmount() {
    window.clearTimeout(this.openTimer);
  },
  methods: {
    scheduleOpen() {
      window.clearTimeout(this.openTimer);
      this.openTimer = window.setTimeout(() => {
        const bounds = this.$refs.trigger?.getBoundingClientRect?.();
        if (!bounds) return;
        this.tooltipStyle = {
          left: `${Math.max(8, Math.min(window.innerWidth - 310, bounds.right + 12))}px`,
          top: `${Math.max(8, Math.min(window.innerHeight - 110, bounds.top + bounds.height / 2))}px`,
        };
        this.open = true;
      }, 180);
    },
    closeTooltip() {
      window.clearTimeout(this.openTimer);
      this.open = false;
    },
  },
};
</script>

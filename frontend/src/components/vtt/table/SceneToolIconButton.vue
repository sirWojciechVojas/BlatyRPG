<template>
  <button
    ref="trigger"
    type="button"
    class="scene-tool-icon-button"
    :class="{
      'scene-tool-icon-button--active': active,
      'scene-tool-icon-button--danger': danger,
    }"
    :aria-disabled="disabled ? 'true' : undefined"
    :aria-label="accessibleLabel"
    :aria-pressed="pressed"
    :aria-describedby="open ? tooltipId : undefined"
    @mouseenter="scheduleOpen"
    @mouseleave="closeTooltip"
    @focus="scheduleOpen"
    @blur="closeTooltip"
    @click="activate"
  >
    <TableRailIcon :name="icon" />
    <span
      v-if="badge !== null && badge !== undefined"
      class="scene-tool-icon-button__badge"
    >
      {{ badge }}
    </span>
  </button>
  <Teleport to="body">
    <Transition name="scene-tool-tooltip">
      <span
        v-if="open"
        :id="tooltipId"
        class="scene-tool-tooltip"
        :style="tooltipStyle"
        role="tooltip"
      >
        <strong>{{ label }}</strong>
        <small v-if="description">{{ description }}</small>
        <kbd v-if="shortcut">{{ shortcut }}</kbd>
      </span>
    </Transition>
  </Teleport>
</template>

<script>
import TableRailIcon from "./TableRailIcon.vue";

let tooltipSequence = 0;

export default {
  name: "SceneToolIconButton",
  components: { TableRailIcon },
  props: {
    icon: { type: String, required: true },
    label: { type: String, required: true },
    description: { type: String, default: "" },
    shortcut: { type: String, default: "" },
    badge: { type: [String, Number], default: null },
    active: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    danger: { type: Boolean, default: false },
    toggle: { type: Boolean, default: false },
  },
  emits: ["click"],
  data() {
    tooltipSequence += 1;
    return {
      open: false,
      openTimer: null,
      tooltipStyle: {},
      tooltipId: `scene-tool-tip-${tooltipSequence}`,
    };
  },
  computed: {
    accessibleLabel() {
      return this.description
        ? `${this.label}. ${this.description}`
        : this.label;
    },
    pressed() {
      return this.toggle ? this.active : undefined;
    },
  },
  beforeUnmount() {
    window.clearTimeout(this.openTimer);
  },
  methods: {
    activate(event) {
      if (this.disabled) {
        event.preventDefault();
        return;
      }
      this.$emit("click", event);
    },
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

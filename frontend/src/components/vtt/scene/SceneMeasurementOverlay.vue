<template>
  <div
    ref="overlay"
    class="scene-measurement"
    :class="{ 'scene-measurement--active': active }"
    tabindex="-1"
    @pointerdown="begin"
    @pointermove="move"
    @pointerup="finish"
    @pointercancel="finish"
    @contextmenu.prevent="clear"
    @keydown.esc="clear"
  >
    <nav v-if="activeTool === 'templates'" class="scene-measurement__types">
      <button
        v-for="type in templateTypes"
        :key="type"
        type="button"
        :class="{ active: templateType === type }"
        :aria-pressed="templateType === type"
        :title="$t(`vtt.measurement.${type}`)"
        @pointerdown.stop
        @click.stop="templateType = type"
      >
        {{ symbols[type] }}
      </button>
    </nav>
    <svg v-if="gesture" aria-hidden="true">
      <line
        v-if="activeTool === 'measure'"
        :x1="gesture.start.x"
        :y1="gesture.start.y"
        :x2="gesture.end.x"
        :y2="gesture.end.y"
      />
      <circle
        v-else-if="templateType === 'circle'"
        :cx="gesture.start.x"
        :cy="gesture.start.y"
        :r="radius"
      />
      <rect
        v-else-if="templateType === 'rectangle'"
        :x="rectangle.x"
        :y="rectangle.y"
        :width="rectangle.width"
        :height="rectangle.height"
      />
      <path v-else :d="cone" />
    </svg>
    <output v-if="gesture" :style="labelStyle">{{ distanceLabel }}</output>
  </div>
</template>

<script>
import {
  conePath,
  formatDistance,
  measuredDistance,
} from "@/lib/vtt/measurement";

export default {
  name: "SceneMeasurementOverlay",
  props: {
    scene: { type: Object, default: null },
    activeTool: { type: String, default: "select" },
    scale: { type: Number, default: 1 },
  },
  data: () => ({
    gesture: null,
    pointerId: null,
    templateType: "circle",
    templateTypes: ["circle", "cone", "rectangle"],
    symbols: { circle: "○", cone: "◁", rectangle: "□" },
  }),
  computed: {
    active() {
      return ["measure", "templates"].includes(this.activeTool);
    },
    radius() {
      if (!this.gesture) return 0;
      return Math.hypot(
        this.gesture.end.x - this.gesture.start.x,
        this.gesture.end.y - this.gesture.start.y,
      );
    },
    rectangle() {
      const { start, end } = this.gesture;
      return {
        x: Math.min(start.x, end.x),
        y: Math.min(start.y, end.y),
        width: Math.abs(end.x - start.x),
        height: Math.abs(end.y - start.y),
      };
    },
    cone() {
      return conePath(this.gesture.start, this.gesture.end);
    },
    distanceLabel() {
      const distance = measuredDistance(
        this.scene,
        this.gesture.start,
        this.gesture.end,
        this.scale,
      );
      return formatDistance(distance, this.scene?.gridUnit);
    },
    labelStyle() {
      return {
        left: `${this.gesture.end.x + 10}px`,
        top: `${this.gesture.end.y + 10}px`,
      };
    },
  },
  watch: {
    active(value) {
      if (!value) this.clear();
    },
    "scene.id"() {
      this.clear();
    },
  },
  methods: {
    point(event) {
      const rect = this.$refs.overlay.getBoundingClientRect();
      return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    },
    begin(event) {
      if (!this.active || event.button !== 0) return;
      const point = this.point(event);
      this.gesture = { start: point, end: point };
      this.pointerId = event.pointerId;
      event.currentTarget.setPointerCapture?.(event.pointerId);
      event.currentTarget.focus?.({ preventScroll: true });
    },
    move(event) {
      if (!this.gesture || event.pointerId !== this.pointerId) return;
      this.gesture = { ...this.gesture, end: this.point(event) };
    },
    finish(event) {
      if (event.pointerId !== this.pointerId) return;
      this.move(event);
      this.pointerId = null;
      event.currentTarget.releasePointerCapture?.(event.pointerId);
    },
    clear() {
      this.gesture = null;
      this.pointerId = null;
    },
  },
};
</script>

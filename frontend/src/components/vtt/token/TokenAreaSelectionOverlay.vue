<template>
  <svg
    v-if="selection"
    class="token-area-selection"
    :width="scene.width"
    :height="scene.height"
    :viewBox="`0 0 ${scene.width} ${scene.height}`"
    aria-live="polite"
  >
    <title>{{ summary }}</title>
    <g class="token-area-selection__candidates">
      <rect
        v-for="token in candidates"
        :key="token.id"
        :x="token.x - candidatePadding"
        :y="token.y - candidatePadding"
        :width="Number(token.width) + candidatePadding * 2"
        :height="Number(token.height) + candidatePadding * 2"
        :rx="candidateRadius(token)"
        vector-effect="non-scaling-stroke"
      />
    </g>
    <rect
      v-if="selection.type === 'rectangle'"
      class="token-area-selection__shape"
      v-bind="bounds"
      vector-effect="non-scaling-stroke"
    />
    <circle
      v-else-if="selection.type === 'circle'"
      class="token-area-selection__shape"
      :cx="selection.start.x"
      :cy="selection.start.y"
      :r="radius"
      vector-effect="non-scaling-stroke"
    />
    <polygon
      v-else
      class="token-area-selection__shape"
      :points="polygonPoints"
      vector-effect="non-scaling-stroke"
    />
    <g v-if="selection.type === 'polygon'" class="token-area-selection__nodes">
      <circle
        v-for="(point, index) in selection.points"
        :key="index"
        :cx="point.x"
        :cy="point.y"
        :r="4 / safeScale"
        vector-effect="non-scaling-stroke"
      />
    </g>
    <g
      class="token-area-selection__count"
      :transform="`translate(${badge.x} ${badge.y}) scale(${inverseScale})`"
    >
      <circle r="13" />
      <text text-anchor="middle" dominant-baseline="central">
        {{ candidates.length }}
      </text>
    </g>
  </svg>
</template>

<script>
import {
  selectableTokensInArea,
  tokenSelectionBounds,
  tokenSelectionPolygonPoints,
  tokenSelectionRadius,
} from "@/lib/vtt/tokenAreaSelection";

export default {
  name: "TokenAreaSelectionOverlay",
  props: {
    scene: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    selection: { type: Object, default: null },
    scale: { type: Number, default: 1 },
  },
  computed: {
    safeScale() {
      return Math.max(0.05, Number(this.scale) || 1);
    },
    inverseScale() {
      return 1 / this.safeScale;
    },
    candidatePadding() {
      return 4 / this.safeScale;
    },
    candidates() {
      return selectableTokensInArea(this.tokens, this.selection);
    },
    bounds() {
      return tokenSelectionBounds(this.selection);
    },
    radius() {
      return tokenSelectionRadius(this.selection);
    },
    polygonPoints() {
      return tokenSelectionPolygonPoints(this.selection)
        .map(({ x, y }) => `${x},${y}`)
        .join(" ");
    },
    badge() {
      return (
        this.selection.current ||
        this.selection.points?.at(-1) ||
        this.selection.start || { x: 0, y: 0 }
      );
    },
    summary() {
      return this.$t("vtt.table.tools.tokenSelection.count", {
        count: this.candidates.length,
      });
    },
  },
  methods: {
    candidateRadius(token) {
      return Math.min(Number(token.width), Number(token.height)) / 2;
    },
  },
};
</script>

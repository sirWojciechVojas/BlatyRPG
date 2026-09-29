<template>
  <div
    v-if="indicator"
    class="token-drag-indicator"
    :class="{ 'token-drag-indicator--exceeded': indicator.exceeded }"
    aria-hidden="true"
  >
    <svg :viewBox="`0 0 ${scene.width} ${scene.height}`">
      <defs>
        <filter :id="glowId" x="-60%" y="-60%" width="220%" height="220%">
          <feGaussianBlur stdDeviation="5" result="blur" />
          <feMerge>
            <feMergeNode in="blur" />
            <feMergeNode in="SourceGraphic" />
          </feMerge>
        </filter>
      </defs>
      <polyline
        class="token-drag-indicator__trail-glow"
        :points="indicator.polyline"
      />
      <polyline
        class="token-drag-indicator__trail"
        :points="indicator.polyline"
      />
      <circle
        v-for="(waypoint, index) in indicator.waypoints"
        :key="index"
        class="token-drag-indicator__waypoint"
        :cx="waypoint.x"
        :cy="waypoint.y"
        :r="Math.max(6, indicator.radius * 0.12)"
      />
      <circle
        class="token-drag-indicator__origin-ring"
        :cx="indicator.start.x"
        :cy="indicator.start.y"
        :r="indicator.radius"
      />
      <g
        class="token-drag-indicator__target"
        :filter="`url(#${glowId})`"
        :transform="`translate(${indicator.end.x} ${indicator.end.y})`"
      >
        <circle
          class="token-drag-indicator__target-pulse"
          :r="indicator.radius"
        />
        <circle
          class="token-drag-indicator__target-core"
          :r="indicator.radius * 0.66"
        />
        <path :d="crosshairPath" />
      </g>
    </svg>
    <div class="token-drag-indicator__ghost" :style="ghostStyle">
      <img v-if="indicator.imageUrl" :src="indicator.imageUrl" alt="" />
      <span v-else>{{ indicator.initials }}</span>
    </div>
    <output :style="labelStyle">
      {{
        $t(
          indicator.groupCount > 1
            ? "vtt.token.draggingGroup"
            : "vtt.token.dragging",
          indicator,
        )
      }}
    </output>
  </div>
</template>

<script>
import { getCurrentInstance } from "vue";
import { tokenGhostStyle } from "./tokenDragIndicator";

export default {
  name: "TokenDragIndicator",
  props: {
    scene: { type: Object, required: true },
    indicator: { type: Object, default: null },
    scale: { type: Number, default: 1 },
  },
  data() {
    return { glowId: `token-drag-glow-${getCurrentInstance().uid}` };
  },
  computed: {
    crosshairPath() {
      const radius = this.indicator?.radius || 0;
      const inner = radius * 0.78;
      const outer = radius * 1.18;
      return `M ${-outer} 0 H ${-inner} M ${inner} 0 H ${outer} M 0 ${-outer} V ${-inner} M 0 ${inner} V ${outer}`;
    },
    ghostStyle() {
      return tokenGhostStyle(this.indicator);
    },
    labelStyle() {
      const inverse = 1 / Math.max(0.05, this.scale);
      return {
        left: `${this.indicator.end.x}px`,
        top: `${this.indicator.end.y - this.indicator.radius - 12}px`,
        transform: `translate(-50%, -100%) scale(${inverse})`,
      };
    },
  },
};
</script>

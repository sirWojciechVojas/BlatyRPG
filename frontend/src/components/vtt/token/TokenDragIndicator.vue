<template>
  <div v-if="indicator" class="token-drag-indicator" aria-hidden="true">
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
      <line
        class="token-drag-indicator__trail-glow"
        :x1="indicator.start.x"
        :y1="indicator.start.y"
        :x2="indicator.end.x"
        :y2="indicator.end.y"
      />
      <line
        class="token-drag-indicator__trail"
        :x1="indicator.start.x"
        :y1="indicator.start.y"
        :x2="indicator.end.x"
        :y2="indicator.end.y"
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
      {{ $t("vtt.token.dragging", indicator) }}
    </output>
  </div>
</template>

<script>
import { getCurrentInstance } from "vue";

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
      const value = this.indicator;
      return {
        left: `${value.start.x - value.width / 2}px`,
        top: `${value.start.y - value.height / 2}px`,
        width: `${value.width}px`,
        height: `${value.height}px`,
      };
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

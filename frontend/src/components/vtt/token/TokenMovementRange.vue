<template>
  <div
    v-if="geometry"
    class="token-movement-range"
    :class="{ 'token-movement-range--depleted': geometry.remaining <= 0 }"
    aria-hidden="true"
  >
    <svg :viewBox="`0 0 ${scene.width} ${scene.height}`">
      <defs>
        <mask
          :id="maskId"
          maskUnits="userSpaceOnUse"
          x="0"
          y="0"
          :width="scene.width"
          :height="scene.height"
        >
          <rect :width="scene.width" :height="scene.height" fill="white" />
          <path
            v-if="geometry.path"
            :d="geometry.path"
            fill="black"
            stroke="black"
            stroke-width="1"
          />
        </mask>
      </defs>
      <rect
        class="token-movement-range__shade"
        :width="scene.width"
        :height="scene.height"
        :mask="`url(#${maskId})`"
      />
      <path
        v-if="geometry.path"
        class="token-movement-range__reachable"
        :d="geometry.path"
      />
    </svg>
    <output :style="badgeStyle">
      {{
        $t("vtt.token.movement.rangePreview", {
          remaining: geometry.remaining,
        })
      }}
    </output>
  </div>
</template>

<script>
import { getCurrentInstance } from "vue";
import { buildTokenMovementRange } from "@/lib/vtt/tokenMovementRange";

export default {
  name: "TokenMovementRange",
  props: {
    scene: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    selectedIds: { type: Array, default: () => [] },
    scale: { type: Number, default: 1 },
    enabled: { type: Boolean, default: true },
  },
  data() {
    return { maskId: `token-movement-mask-${getCurrentInstance().uid}` };
  },
  computed: {
    selectedToken() {
      const ids = this.selectedIds.length
        ? this.selectedIds
        : this.selectedId === null
          ? []
          : [this.selectedId];
      if (!this.enabled || ids.length !== 1) return null;
      return (
        this.tokens.find((token) => String(token.id) === String(ids[0])) || null
      );
    },
    geometry() {
      const token = this.selectedToken;
      if (
        !token ||
        token.locked ||
        token.disabled ||
        !token.capabilities?.canControl
      ) {
        return null;
      }
      return buildTokenMovementRange(this.scene, token);
    },
    badgeStyle() {
      if (!this.geometry) return {};
      const inverse = 1 / Math.max(0.05, Number(this.scale) || 1);
      const tokenHeight = Number(this.selectedToken?.height) || 0;
      return {
        left: `${this.geometry.origin.x}px`,
        top: `${this.geometry.origin.y - tokenHeight / 2 - 13}px`,
        transform: `translate(-50%, -100%) scale(${inverse})`,
      };
    },
  },
};
</script>

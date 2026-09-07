<template>
  <div v-if="shapes.length" class="token-vision-overlay" aria-hidden="true">
    <svg :viewBox="`0 0 ${scene.width} ${scene.height}`">
      <path
        v-for="shape in shapes"
        :key="shape.id"
        class="token-vision-overlay__shape"
        :d="shape.path"
        :fill="shape.fill"
        :fill-opacity="shape.fillOpacity"
        :stroke="shape.border"
        :stroke-opacity="shape.borderOpacity"
      />
    </svg>
  </div>
</template>

<script>
import { lightPolygonPath, tokenVisionSource } from "@/lib/vtt/lightGeometry";

const opacity = (value, fallback) => {
  const number = Number(value);
  return Number.isFinite(number) ? Math.min(1, Math.max(0, number)) : fallback;
};

const color = (value, fallback) =>
  /^#[0-9a-f]{6}$/iu.test(String(value || "")) ? value : fallback;

export default {
  name: "TokenVisionOverlay",
  props: {
    scene: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    walls: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
  },
  computed: {
    shapes() {
      return this.tokens
        .filter(
          (token) =>
            token.vision?.enabled === true && token.vision?.showShape === true,
        )
        .map((token) => {
          const source = tokenVisionSource(
            this.canManage
              ? {
                  ...token,
                  capabilities: { ...token.capabilities, canControl: true },
                }
              : token,
            this.scene,
          );
          if (!source) return null;
          return {
            id: token.id,
            path: lightPolygonPath(source, this.walls, this.scene, "sight"),
            border: color(token.vision.shapeBorderColor, "#65d7ff"),
            borderOpacity: opacity(token.vision.shapeBorderOpacity, 0.8),
            fill: color(token.vision.shapeFillColor, "#65d7ff"),
            fillOpacity: opacity(token.vision.shapeFillOpacity, 0.12),
          };
        })
        .filter((shape) => shape?.path);
    },
  },
};
</script>

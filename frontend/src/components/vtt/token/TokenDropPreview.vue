<template>
  <div v-if="preview" class="token-drop-preview" aria-hidden="true">
    <svg :viewBox="`0 0 ${scene.width} ${scene.height}`">
      <line
        :x1="preview.start.x"
        :y1="preview.start.y"
        :x2="targetCenter.x"
        :y2="targetCenter.y"
      />
    </svg>
    <div class="token-drop-preview__target" :style="targetStyle">
      <i class="token-drop-preview__orbit token-drop-preview__orbit--outer" />
      <i class="token-drop-preview__orbit token-drop-preview__orbit--inner" />
      <div class="token-drop-preview__portrait">
        <img
          v-if="preview.actor.imageUrl"
          :src="preview.actor.imageUrl"
          alt=""
        />
        <span v-else>{{ initials }}</span>
      </div>
      <strong :style="labelStyle">
        {{ $t("vtt.token.dropActor", { name: preview.actor.name }) }}
      </strong>
    </div>
  </div>
</template>

<script>
import { snapTokenPosition } from "@/lib/vtt/grid";

export default {
  name: "TokenDropPreview",
  props: {
    scene: { type: Object, required: true },
    preview: { type: Object, default: null },
    scale: { type: Number, default: 1 },
  },
  computed: {
    size() {
      return Math.max(1, Number(this.scene.gridSize) || 100);
    },
    snappedPosition() {
      return snapTokenPosition(
        this.scene,
        {
          x: this.preview.x - this.size / 2,
          y: this.preview.y - this.size / 2,
        },
        { width: this.size, height: this.size },
      );
    },
    targetCenter() {
      return {
        x: this.snappedPosition.x + this.size / 2,
        y: this.snappedPosition.y + this.size / 2,
      };
    },
    targetStyle() {
      return {
        left: `${this.snappedPosition.x}px`,
        top: `${this.snappedPosition.y}px`,
        width: `${this.size}px`,
        height: `${this.size}px`,
      };
    },
    labelStyle() {
      return { transform: `translateX(-50%) scale(${1 / this.scale})` };
    },
    initials() {
      return String(this.preview?.actor?.name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
  },
};
</script>

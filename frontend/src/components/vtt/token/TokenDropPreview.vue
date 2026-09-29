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
        <AuthenticatedImage
          v-if="source.imageUrl"
          :src="source.imageUrl"
          alt=""
          draggable="false"
        />
        <span v-else>{{ initials }}</span>
      </div>
      <strong :style="labelStyle">
        {{ $t(labelKey, { name: source.name }) }}
      </strong>
    </div>
  </div>
</template>

<script>
import { snapTokenPosition } from "@/lib/vtt/grid";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";

export default {
  name: "TokenDropPreview",
  components: { AuthenticatedImage },
  props: {
    scene: { type: Object, required: true },
    preview: { type: Object, default: null },
    scale: { type: Number, default: 1 },
  },
  computed: {
    source() {
      return this.preview?.template || this.preview?.actor || {};
    },
    labelKey() {
      return this.preview?.template
        ? "vtt.tokenTemplates.drop"
        : "vtt.token.dropActor";
    },
    size() {
      return Math.max(1, Number(this.scene.gridSize) || 100);
    },
    width() {
      return this.size * Math.max(0.25, Number(this.source.widthCells) || 1);
    },
    height() {
      return this.size * Math.max(0.25, Number(this.source.heightCells) || 1);
    },
    snappedPosition() {
      return snapTokenPosition(
        this.scene,
        {
          x: this.preview.x - this.width / 2,
          y: this.preview.y - this.height / 2,
        },
        { width: this.width, height: this.height },
      );
    },
    targetCenter() {
      return {
        x: this.snappedPosition.x + this.width / 2,
        y: this.snappedPosition.y + this.height / 2,
      };
    },
    targetStyle() {
      return {
        left: `${this.snappedPosition.x}px`,
        top: `${this.snappedPosition.y}px`,
        width: `${this.width}px`,
        height: `${this.height}px`,
      };
    },
    labelStyle() {
      return { transform: `translateX(-50%) scale(${1 / this.scale})` };
    },
    initials() {
      return String(this.source.name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
  },
};
</script>

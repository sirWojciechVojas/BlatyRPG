<template>
  <div v-if="preview" class="token-drop-preview" aria-hidden="true">
    <svg :viewBox="`0 0 ${scene.width} ${scene.height}`">
      <line
        :x1="preview.start.x"
        :y1="preview.start.y"
        :x2="preview.x"
        :y2="preview.y"
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
export default {
  name: "TokenDropPreview",
  props: {
    scene: { type: Object, required: true },
    preview: { type: Object, default: null },
    scale: { type: Number, default: 1 },
  },
  computed: {
    size() {
      return Math.max(8, Number(this.scene.gridSize) || 100);
    },
    targetStyle() {
      return {
        left: `${this.preview.x}px`,
        top: `${this.preview.y}px`,
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

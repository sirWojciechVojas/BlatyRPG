<template>
  <div class="scene-token-layer">
    <TokenDragIndicator
      :scene="scene"
      :indicator="dragIndicator"
      :scale="scale"
    />
    <div
      v-for="token in tokens"
      :key="token.id"
      class="scene-token-wrap"
      :class="{
        'scene-token-wrap--dragging': drag?.token.id === token.id,
      }"
      :style="tokenStyle(token)"
    >
      <button
        type="button"
        class="scene-token"
        :class="[
          `scene-token--${token.disposition}`,
          {
            'scene-token--selected': token.id === selectedId,
            'scene-token--hidden': token.hidden,
            'scene-token--locked': token.locked,
          },
        ]"
        :style="{ transform: `rotate(${token.rotation}deg)` }"
        :aria-label="token.name"
        :aria-pressed="token.id === selectedId"
        :aria-disabled="!token.capabilities.canControl || token.locked"
        @pointerdown.stop="startDrag($event, token)"
        @click.stop="$emit('select', token.id)"
      >
        <img
          v-if="token.imageUrl"
          :src="token.imageUrl"
          alt=""
          draggable="false"
        />
        <span v-else>{{ initials(token.name) }}</span>
        <small>{{ token.name }}</small>
      </button>
      <TokenHud
        v-if="token.id === selectedId"
        :token="token"
        :busy="busy"
        @pointerdown.stop
        @rotate="
          $emit('update', {
            token,
            changes: { rotation: token.rotation + $event },
          })
        "
        @open-actor="$emit('open-actor', $event)"
        @delete="$emit('delete', token)"
      />
    </div>
  </div>
</template>

<script>
import TokenHud from "./TokenHud.vue";
import TokenDragIndicator from "./TokenDragIndicator.vue";
import { tokenDragMethods } from "./tokenDragMethods";
import { buildTokenDragIndicator } from "./tokenDragIndicator";

export default {
  name: "SceneTokenLayer",
  components: { TokenDragIndicator, TokenHud },
  props: {
    scene: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    scale: { type: Number, default: 1 },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "move", "update", "delete", "open-actor"],
  data: () => ({ drag: null, preview: {} }),
  computed: {
    dragIndicator() {
      if (!this.drag) return null;
      return buildTokenDragIndicator(
        this.scene,
        this.drag.token,
        this.preview[this.drag.token.id] || this.drag.token,
      );
    },
  },
  beforeUnmount() {
    this.cancelDrag();
  },
  methods: {
    ...tokenDragMethods,
    tokenStyle(token) {
      const position = this.preview[token.id] || token;
      return {
        width: `${token.width}px`,
        height: `${token.height}px`,
        transform: `translate(${position.x}px, ${position.y}px)`,
        zIndex: String(100 + Math.round(token.elevation || 0)),
      };
    },
    initials(name) {
      return String(name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
  },
};
</script>

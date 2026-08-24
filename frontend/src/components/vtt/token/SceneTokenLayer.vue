<template>
  <div class="scene-token-layer">
    <div
      v-for="token in tokens"
      :key="token.id"
      class="scene-token-wrap"
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

export default {
  name: "SceneTokenLayer",
  components: { TokenHud },
  props: {
    tokens: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    scale: { type: Number, default: 1 },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "move", "update", "delete", "open-actor"],
  data: () => ({ drag: null, preview: {} }),
  methods: {
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
    startDrag(event, token) {
      this.$emit("select", token.id);
      if (!token.capabilities.canControl || token.locked || event.button !== 0)
        return;
      this.drag = {
        id: event.pointerId,
        token,
        clientX: event.clientX,
        clientY: event.clientY,
      };
      event.currentTarget.setPointerCapture?.(event.pointerId);
      event.currentTarget.addEventListener("pointermove", this.moveDrag);
      event.currentTarget.addEventListener("pointerup", this.endDrag, {
        once: true,
      });
      event.currentTarget.addEventListener("pointercancel", this.endDrag, {
        once: true,
      });
    },
    moveDrag(event) {
      if (!this.drag || event.pointerId !== this.drag.id) return;
      const scale = Math.max(0.05, this.scale);
      this.preview = {
        ...this.preview,
        [this.drag.token.id]: {
          x: this.drag.token.x + (event.clientX - this.drag.clientX) / scale,
          y: this.drag.token.y + (event.clientY - this.drag.clientY) / scale,
        },
      };
    },
    endDrag(event) {
      if (!this.drag || event.pointerId !== this.drag.id) return;
      const token = this.drag.token;
      const position = this.preview[token.id];
      event.currentTarget.removeEventListener("pointermove", this.moveDrag);
      this.drag = null;
      const next = { ...this.preview };
      delete next[token.id];
      this.preview = next;
      if (position && (position.x !== token.x || position.y !== token.y)) {
        this.$emit("move", { token, x: position.x, y: position.y });
      }
    },
  },
};
</script>

<template>
  <div
    v-if="available"
    class="token-rotation-handles"
    :style="{ '--token-handle-scale': inverseScale }"
  >
    <span
      v-if="token.facingHandleEnabled"
      class="token-rotation-handle token-rotation-handle--facing"
      :style="orbitStyle(token.facing)"
    >
      <button
        type="button"
        :title="$t('vtt.token.rotationHandles.facing')"
        :aria-label="$t('vtt.token.rotationHandles.facing')"
        @pointerdown.stop="start($event, 'facing')"
      >
        ▲
      </button>
    </span>
    <span
      v-if="token.rotationHandleEnabled"
      class="token-rotation-handle token-rotation-handle--artwork"
      :style="orbitStyle(token.rotation)"
    >
      <button
        type="button"
        :title="$t('vtt.token.rotationHandles.rotation')"
        :aria-label="$t('vtt.token.rotationHandles.rotation')"
        @pointerdown.stop="start($event, 'rotation')"
      >
        ↻
      </button>
    </span>
    <output v-if="drag">{{ Math.round(drag.value) }}°</output>
  </div>
</template>

<script>
import { tokenPointerAngle } from "@/lib/vtt/tokenRotation";

export default {
  name: "TokenRotationHandles",
  props: {
    token: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    scale: { type: Number, default: 1 },
  },
  emits: ["preview", "commit", "cancel"],
  data: () => ({ drag: null }),
  computed: {
    inverseScale() {
      return 1 / Math.max(0.1, Number(this.scale) || 1);
    },
    available() {
      return (
        !this.disabled &&
        !this.token.locked &&
        this.token.capabilities?.canControl &&
        (this.token.rotationHandleEnabled || this.token.facingHandleEnabled)
      );
    },
  },
  beforeUnmount() {
    this.clear();
  },
  methods: {
    orbitStyle(angle) {
      return { transform: `rotate(${Number(angle) || 0}deg)` };
    },
    start(event, field) {
      if (event.button !== 0 || !this.available) return;
      this.clear();
      const rect = event.currentTarget
        .closest(".scene-token-wrap")
        .getBoundingClientRect();
      this.drag = {
        id: event.pointerId,
        field,
        value: Number(this.token[field]) || 0,
        center: {
          x: rect.left + rect.width / 2,
          y: rect.top + rect.height / 2,
        },
      };
      event.currentTarget.setPointerCapture?.(event.pointerId);
      window.addEventListener("pointermove", this.move);
      window.addEventListener("pointerup", this.finish);
      window.addEventListener("pointercancel", this.cancel);
      event.preventDefault();
    },
    move(event) {
      if (!this.drag || event.pointerId !== this.drag.id) return;
      this.drag.value = tokenPointerAngle(this.drag.center, event);
      this.$emit("preview", { field: this.drag.field, value: this.drag.value });
    },
    finish(event) {
      if (!this.drag || event.pointerId !== this.drag.id) return;
      this.move(event);
      const result = { field: this.drag.field, value: this.drag.value };
      this.clear();
      this.$emit("commit", result);
    },
    cancel(event) {
      if (event && this.drag && event.pointerId !== this.drag.id) return;
      this.clear();
      this.$emit("cancel");
    },
    clear() {
      window.removeEventListener("pointermove", this.move);
      window.removeEventListener("pointerup", this.finish);
      window.removeEventListener("pointercancel", this.cancel);
      this.drag = null;
    },
  },
};
</script>

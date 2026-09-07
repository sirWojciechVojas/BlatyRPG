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
        @click.stop.prevent="handleClick('facing', token.facing, $event.detail)"
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
        @click.stop.prevent="
          handleClick('rotation', token.rotation, $event.detail)
        "
      >
        ↻
      </button>
    </span>
    <output v-if="drag">{{ Math.round(drag.value) }}°</output>
  </div>
</template>

<script>
import { tokenPointerAngle } from "@/lib/vtt/tokenRotation";

const DOUBLE_CLICK_DELAY = 800;
const BASE_ANGLES = Object.freeze({ facing: 270, rotation: 0 });

const movedFromStart = (drag, event) =>
  Math.hypot(
    Number(event.clientX) - drag.start.x,
    Number(event.clientY) - drag.start.y,
  ) >= 10;

export default {
  name: "TokenRotationHandles",
  props: {
    token: { type: Object, required: true },
    disabled: { type: Boolean, default: false },
    scale: { type: Number, default: 1 },
  },
  emits: ["preview", "commit", "cancel"],
  data: () => ({
    drag: null,
    clicks: {},
    clickSerial: 0,
    ignoreClickUntil: 0,
  }),
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
    Object.values(this.clicks).forEach((click) => {
      if (click?.timer) window.clearTimeout(click.timer);
    });
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
        moved: false,
        start: { x: Number(event.clientX), y: Number(event.clientY) },
        center: {
          x: rect.left + rect.width / 2,
          y: rect.top + rect.height / 2,
        },
      };
      event.currentTarget.setPointerCapture?.(event.pointerId);
      window.addEventListener("pointermove", this.move);
      window.addEventListener("pointerup", this.finish);
      window.addEventListener("pointercancel", this.cancel);
    },
    reset(field) {
      if (!this.available) return;
      this.clearClick(field);
      this.clear();
      this.$emit("cancel");
      this.$emit("commit", { field, value: BASE_ANGLES[field] });
    },
    clearClick(field) {
      const click = this.clicks[field];
      if (click?.timer) window.clearTimeout(click.timer);
      this.clicks = { ...this.clicks, [field]: null };
    },
    handleClick(field, value, clickCount = 1) {
      if (this.ignoreClickUntil > Date.now()) return;
      if (clickCount >= 2) {
        this.reset(field);
        return;
      }
      const previous = this.clicks[field];
      const now = Date.now();
      if (previous && now - previous.at <= DOUBLE_CLICK_DELAY) {
        this.reset(field);
        return;
      }
      this.clearClick(field);
      const click = { id: ++this.clickSerial, at: now, timer: null };
      click.timer = window.setTimeout(() => {
        if (this.clicks[field]?.id !== click.id) return;
        this.clicks = { ...this.clicks, [field]: null };
        if (field === "rotation") {
          this.$emit("commit", { field, value: (value + 90) % 360 });
        } else if (field === "facing") {
          this.$emit("commit", {
            field: "rotation",
            value: (Number(value) + 90) % 360,
          });
        }
      }, DOUBLE_CLICK_DELAY);
      this.clicks = { ...this.clicks, [field]: click };
    },
    move(event) {
      if (!this.drag || event.pointerId !== this.drag.id) return;
      if (!this.drag.moved && !movedFromStart(this.drag, event)) return;
      this.drag.moved = true;
      this.clearClick(this.drag.field);
      this.drag.value = tokenPointerAngle(this.drag.center, event);
      this.$emit("preview", { field: this.drag.field, value: this.drag.value });
    },
    finish(event) {
      if (!this.drag || event.pointerId !== this.drag.id) return;
      this.move(event);
      const result = { field: this.drag.field, value: this.drag.value };
      const changed = this.drag.moved;
      this.clear();
      if (changed) {
        this.ignoreClickUntil = Date.now() + 250;
        this.$emit("commit", result);
      }
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

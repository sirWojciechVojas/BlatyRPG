<template>
  <aside
    class="table-floating-window"
    :class="{ 'table-floating-window--minimized': model.minimized }"
    :style="windowStyle"
    :aria-labelledby="headingId"
    @pointerdown="$emit('focus', model.id)"
  >
    <header class="table-floating-window__header" @pointerdown="startDrag">
      <TableRailIcon :name="icon" />
      <h2 :id="headingId">{{ title }}</h2>
      <button
        type="button"
        :aria-label="
          $t(
            model.minimized
              ? 'vtt.table.window.restore'
              : 'vtt.table.window.minimize',
          )
        "
        @pointerdown.stop
        @click="$emit('minimize', model.id)"
      >
        {{ model.minimized ? "□" : "—" }}
      </button>
      <button
        type="button"
        :aria-label="$t('vtt.table.window.close')"
        @pointerdown.stop
        @click="$emit('close', model.id)"
      >
        ×
      </button>
    </header>
    <div v-show="!model.minimized" class="table-floating-window__body">
      <slot />
    </div>
  </aside>
</template>

<script>
import TableRailIcon from "./TableRailIcon.vue";

export default {
  name: "TableFloatingWindow",
  components: { TableRailIcon },
  props: {
    model: { type: Object, required: true },
    title: { type: String, required: true },
    icon: { type: String, required: true },
  },
  emits: ["move", "focus", "minimize", "close"],
  data: () => ({ drag: null }),
  computed: {
    headingId() {
      return `table-window-${this.model.id}`;
    },
    windowStyle() {
      return {
        left: `${this.model.x}px`,
        top: `${this.model.y}px`,
        width: `${this.model.width}px`,
        height: this.model.minimized ? "auto" : `${this.model.height}px`,
        zIndex: this.model.z,
      };
    },
  },
  beforeUnmount() {
    this.stopDrag();
  },
  methods: {
    startDrag(event) {
      if (event.button !== 0 || event.target.closest("button")) return;
      this.drag = {
        pointerId: event.pointerId,
        clientX: event.clientX,
        clientY: event.clientY,
        x: this.model.x,
        y: this.model.y,
      };
      this.$emit("focus", this.model.id);
      window.addEventListener("pointermove", this.moveDrag);
      window.addEventListener("pointerup", this.stopDrag, { once: true });
      event.preventDefault();
    },
    moveDrag(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      const width = this.model.width;
      const headerHeight = 38;
      const x = Math.min(
        Math.max(0, this.drag.x + event.clientX - this.drag.clientX),
        Math.max(0, window.innerWidth - width),
      );
      const y = Math.min(
        Math.max(0, this.drag.y + event.clientY - this.drag.clientY),
        Math.max(0, window.innerHeight - headerHeight),
      );
      this.$emit("move", { id: this.model.id, x, y });
    },
    stopDrag() {
      this.drag = null;
      window.removeEventListener("pointermove", this.moveDrag);
    },
  },
};
</script>

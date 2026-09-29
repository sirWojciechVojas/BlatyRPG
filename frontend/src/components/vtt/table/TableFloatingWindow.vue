<template>
  <aside
    class="table-floating-window"
    :class="{
      'table-floating-window--minimized': model.minimized,
      'table-floating-window--maximized': model.maximized && !model.minimized,
      [`table-floating-window--${model.windowType}`]: model.windowType,
      [`table-floating-window--source-${model.windowSource}`]:
        model.windowSource,
    }"
    :style="windowStyle"
    :aria-labelledby="headingId"
    :aria-expanded="String(!model.minimized)"
    role="dialog"
    @pointerdown="focusWindow"
  >
    <header
      class="table-floating-window__header"
      @pointerdown="startDrag"
      @dblclick="requestHeaderAction"
    >
      <TableRailIcon :name="icon" />
      <div class="table-floating-window__heading">
        <h2 :id="headingId">{{ title }}</h2>
        <small v-if="subtitle">{{ subtitle }}</small>
      </div>
      <span
        v-if="status && !model.minimized"
        class="table-floating-window__status"
        :class="`table-floating-window__status--${model.status || 'saved'}`"
      >
        {{ status }}
      </span>
      <button
        type="button"
        class="table-floating-window__control table-floating-window__control--minimize"
        :aria-label="
          $t(
            model.minimized
              ? 'vtt.table.window.restore'
              : 'vtt.table.window.minimize',
          )
        "
        @pointerdown.stop
        @dblclick.stop
        @click="requestMinimize"
      >
        {{ model.minimized ? "□" : "—" }}
      </button>
      <button
        v-if="model.maximizable && !model.minimized"
        type="button"
        class="table-floating-window__control table-floating-window__control--maximize"
        :aria-label="
          $t(
            model.maximized
              ? 'vtt.table.window.restoreSize'
              : 'vtt.table.window.maximize',
          )
        "
        @pointerdown.stop
        @dblclick.stop
        @click="requestMaximize"
      >
        {{ model.maximized ? "❐" : "□" }}
      </button>
      <button
        type="button"
        class="table-floating-window__control table-floating-window__control--close"
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
    <footer
      v-if="!model.minimized && ($slots.footer || model.footerText)"
      class="table-floating-window__footer"
    >
      <slot name="footer">{{ model.footerText }}</slot>
    </footer>
    <button
      v-if="model.resizable && !model.minimized && !model.maximized"
      type="button"
      class="table-floating-window__resize"
      :aria-label="$t('vtt.table.window.resize')"
      @pointerdown.stop="startResize"
    />
  </aside>
</template>

<script>
import TableRailIcon from "./TableRailIcon.vue";
import {
  TABLE_WINDOW_Z_INDEX_START,
  registerTableWindow,
} from "./tableWindowLayers";

export default {
  name: "TableFloatingWindow",
  components: { TableRailIcon },
  props: {
    model: { type: Object, required: true },
    title: { type: String, required: true },
    icon: { type: String, required: true },
    subtitle: { type: String, default: "" },
    status: { type: String, default: "" },
  },
  emits: ["move", "resize", "layer-change", "minimize", "maximize", "close"],
  data: () => ({
    drag: null,
    resize: null,
    layerRegistration: null,
    layerZ: TABLE_WINDOW_Z_INDEX_START,
    viewportWidth: window.innerWidth,
    viewportHeight: window.innerHeight,
  }),
  computed: {
    headingId() {
      return `table-window-${this.model.id}`;
    },
    windowStyle() {
      const minimized = this.model.minimized === true;
      const constrained = this.model.constrainToViewport === true;
      const maximized = this.model.maximized && !minimized;
      const widthRatio = Number(this.model.maxViewportWidthRatio);
      const heightRatio = Number(this.model.maxViewportHeightRatio);
      const widthLimit = Math.max(
        1,
        Math.min(
          this.viewportWidth - 16,
          widthRatio > 0
            ? Math.floor(this.viewportWidth * widthRatio)
            : this.viewportWidth - 16,
        ),
      );
      const heightLimit = Math.max(
        1,
        Math.min(
          this.viewportHeight - 16,
          heightRatio > 0
            ? Math.floor(this.viewportHeight * heightRatio)
            : this.viewportHeight - 16,
        ),
      );
      let regularWidth = maximized
        ? widthLimit
        : constrained
          ? Math.min(Number(this.model.width) || 280, widthLimit)
          : Number(this.model.width) || 280;
      let height = maximized
        ? heightLimit
        : constrained
          ? Math.min(Number(this.model.height) || 260, heightLimit)
          : Number(this.model.height) || 260;
      const aspectRatio = Number(this.model.aspectRatio);
      if (!maximized && !minimized && aspectRatio > 0) {
        if (height * aspectRatio <= regularWidth) {
          regularWidth = height * aspectRatio;
        } else {
          height = regularWidth / aspectRatio;
        }
      }
      const width = minimized
        ? Math.min(192, Math.max(1, this.viewportWidth - 16))
        : regularWidth;
      const visibleHeight = constrained && !minimized ? height : 30;
      const left = maximized
        ? 8
        : Math.min(
            Math.max(0, this.model.x),
            Math.max(0, this.viewportWidth - width),
          );
      const top = maximized
        ? 8
        : Math.min(
            Math.max(0, this.model.y),
            Math.max(0, this.viewportHeight - visibleHeight),
          );
      return {
        left: `${left}px`,
        top: `${top}px`,
        width: `${width}px`,
        height: minimized ? "auto" : `${height}px`,
        zIndex: this.layerZ,
        maxWidth: `${widthLimit}px`,
        maxHeight: `${heightLimit}px`,
      };
    },
  },
  mounted() {
    this.layerRegistration = registerTableWindow(this.model.id, (z) => {
      this.layerZ = z;
      this.$emit("layer-change", { id: this.model.id, z });
    });
    window.addEventListener("resize", this.readViewport);
  },
  beforeUnmount() {
    this.stopDrag();
    this.stopResize();
    this.layerRegistration?.unregister();
    window.removeEventListener("resize", this.readViewport);
  },
  methods: {
    readViewport() {
      this.viewportWidth = window.innerWidth;
      this.viewportHeight = window.innerHeight;
    },
    focusWindow() {
      this.layerRegistration?.focus();
    },
    requestHeaderAction(event) {
      if (event.target.closest("button")) return;
      this.focusWindow();
      if (this.model.minimized) {
        this.$emit("minimize", this.model.id);
        return;
      }
      if (!this.model.maximizable) return;
      this.$emit("maximize", this.model.id);
    },
    requestMinimize() {
      this.focusWindow();
      this.$emit("minimize", this.model.id);
    },
    requestMaximize() {
      this.focusWindow();
      this.$emit("maximize", this.model.id);
    },
    startDrag(event) {
      if (
        event.button !== 0 ||
        event.target.closest("button") ||
        this.model.maximized
      )
        return;
      this.drag = {
        pointerId: event.pointerId,
        clientX: event.clientX,
        clientY: event.clientY,
        x: Number.parseFloat(this.windowStyle.left) || 0,
        y: Number.parseFloat(this.windowStyle.top) || 0,
      };
      this.focusWindow();
      window.addEventListener("pointermove", this.moveDrag);
      window.addEventListener("pointerup", this.stopDrag, { once: true });
      event.preventDefault();
    },
    moveDrag(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      const width = Math.min(
        Number(this.$el?.offsetWidth) || this.model.width,
        window.innerWidth,
      );
      const headerHeight =
        Number(this.$el?.querySelector("header")?.offsetHeight) || 38;
      const x = Math.min(
        Math.max(0, this.drag.x + event.clientX - this.drag.clientX),
        Math.max(0, window.innerWidth - width),
      );
      const visibleHeight =
        this.model.constrainToViewport === true && !this.model.minimized
          ? Math.min(this.model.height, window.innerHeight)
          : headerHeight;
      const y = Math.min(
        Math.max(0, this.drag.y + event.clientY - this.drag.clientY),
        Math.max(0, window.innerHeight - visibleHeight),
      );
      this.$emit("move", { id: this.model.id, x, y });
    },
    stopDrag() {
      this.drag = null;
      window.removeEventListener("pointermove", this.moveDrag);
    },
    startResize(event) {
      if (event.button !== 0 || this.model.maximized) return;
      this.resize = {
        pointerId: event.pointerId,
        clientX: event.clientX,
        clientY: event.clientY,
        width: this.model.width,
        height: this.model.height,
      };
      this.focusWindow();
      window.addEventListener("pointermove", this.moveResize);
      window.addEventListener("pointerup", this.stopResize, { once: true });
      event.preventDefault();
    },
    moveResize(event) {
      if (!this.resize || event.pointerId !== this.resize.pointerId) return;
      const widthRatio = Number(this.model.maxViewportWidthRatio);
      const heightRatio = Number(this.model.maxViewportHeightRatio);
      const viewportWidth = Math.max(
        280,
        Math.min(
          window.innerWidth - this.model.x - 8,
          widthRatio > 0
            ? Math.floor(window.innerWidth * widthRatio)
            : window.innerWidth,
        ),
      );
      const viewportHeight = Math.max(
        260,
        Math.min(
          window.innerHeight - this.model.y - 8,
          heightRatio > 0
            ? Math.floor(window.innerHeight * heightRatio)
            : window.innerHeight,
        ),
      );
      const minimumWidth = Math.min(
        Number(this.model.minWidth) || 280,
        viewportWidth,
      );
      const minimumHeight = Math.min(
        Number(this.model.minHeight) || 260,
        viewportHeight,
      );
      const maximumWidth = Math.min(
        Number(this.model.maxWidth) || viewportWidth,
        viewportWidth,
      );
      const maximumHeight = Math.min(
        Number(this.model.maxHeight) || viewportHeight,
        viewportHeight,
      );
      const width = Math.max(
        minimumWidth,
        Math.min(
          maximumWidth,
          this.resize.width + event.clientX - this.resize.clientX,
        ),
      );
      const height = Math.max(
        minimumHeight,
        Math.min(
          maximumHeight,
          this.resize.height + event.clientY - this.resize.clientY,
        ),
      );
      this.$emit("resize", {
        id: this.model.id,
        width: Math.round(width),
        height: Math.round(height),
        x: this.model.x,
        y: this.model.y,
      });
    },
    stopResize() {
      this.resize = null;
      window.removeEventListener("pointermove", this.moveResize);
    },
  },
};
</script>

<template>
  <section
    ref="viewport"
    class="scene-canvas"
    :class="{ 'scene-canvas--dragging': dragging }"
    tabindex="0"
    role="region"
    :aria-label="
      $t('vtt.scene.workspace.canvasLabel', { name: scene?.name || '' })
    "
    @wheel.prevent="onWheel"
    @keydown="onKeydown"
    @pointerdown="startPan"
    @pointermove="movePan"
    @pointerup="endPan"
    @pointercancel="endPan"
  >
    <p v-if="!scene" class="scene-canvas__empty">
      {{ $t("vtt.scene.workspace.noScene") }}
    </p>
    <div v-else class="scene-canvas__map" :style="mapStyle">
      <div class="scene-canvas__content" :style="contentStyle">
        <img
          v-if="scene.backgroundUrl && !backgroundFailed"
          class="scene-canvas__background"
          :src="scene.backgroundUrl"
          :alt="scene.name"
          draggable="false"
          @error="backgroundFailed = true"
        />
        <svg
          v-if="pattern"
          class="scene-canvas__grid"
          :width="scene.width"
          :height="scene.height"
          :viewBox="`0 0 ${scene.width} ${scene.height}`"
          aria-hidden="true"
        >
          <defs>
            <pattern
              :id="patternId"
              patternUnits="userSpaceOnUse"
              :x="pattern.offsetX"
              :y="pattern.offsetY"
              :width="pattern.width"
              :height="pattern.height"
            >
              <path
                :d="pattern.path"
                fill="none"
                :stroke="pattern.color"
                :stroke-opacity="pattern.opacity"
                vector-effect="non-scaling-stroke"
              />
            </pattern>
          </defs>
          <rect width="100%" height="100%" :fill="`url(#${patternId})`" />
        </svg>
        <p v-if="backgroundFailed" class="scene-canvas__image-error">
          {{ $t("vtt.scene.workspace.backgroundError") }}
        </p>
      </div>
    </div>
  </section>
</template>

<script>
import { getCurrentInstance, nextTick } from "vue";
import { buildGridPattern, clamp } from "@/lib/vtt/grid";

export default {
  name: "SceneCanvas",
  props: { scene: { type: Object, default: null } },
  emits: ["camera-change"],
  data() {
    return {
      camera: { x: 0, y: 0, scale: 1 },
      dragging: false,
      pointer: null,
      backgroundFailed: false,
      resizeObserver: null,
      hasFitted: false,
      viewportSize: { width: 0, height: 0 },
      patternId: `scene-grid-${getCurrentInstance().uid}`,
    };
  },
  computed: {
    pattern() {
      return this.scene ? buildGridPattern(this.scene) : null;
    },
    mapDimensions() {
      if (!this.scene) return { width: 0, height: 0, padding: 0 };
      const padding = Math.max(0, Number(this.scene.padding) || 0);
      return {
        width: this.scene.width + padding * 2,
        height: this.scene.height + padding * 2,
        padding,
      };
    },
    mapStyle() {
      if (!this.scene) return {};
      return {
        width: `${this.mapDimensions.width}px`,
        height: `${this.mapDimensions.height}px`,
        backgroundColor: this.scene.backgroundColor,
        transform: `translate(${this.camera.x}px, ${this.camera.y}px) scale(${this.camera.scale})`,
      };
    },
    contentStyle() {
      return {
        top: `${this.mapDimensions.padding}px`,
        left: `${this.mapDimensions.padding}px`,
        width: `${this.scene?.width || 0}px`,
        height: `${this.scene?.height || 0}px`,
      };
    },
  },
  watch: {
    "scene.id"() {
      this.backgroundFailed = false;
      this.hasFitted = false;
      nextTick(this.fit);
    },
    "scene.backgroundUrl"() {
      this.backgroundFailed = false;
    },
  },
  mounted() {
    if (typeof ResizeObserver !== "undefined") {
      this.resizeObserver = new ResizeObserver(([entry]) => {
        this.resizeViewport(entry.contentRect.width, entry.contentRect.height);
      });
      this.resizeObserver.observe(this.$refs.viewport);
    }
    nextTick(this.fit);
  },
  beforeUnmount() {
    this.resizeObserver?.disconnect();
  },
  methods: {
    emitCamera() {
      this.$emit("camera-change", {
        zoomPercent: Math.round(this.camera.scale * 100),
      });
    },
    resizeViewport(width, height) {
      if (!this.hasFitted) {
        this.viewportSize = { width, height };
        nextTick(this.fit);
        return;
      }
      this.camera.x += (width - this.viewportSize.width) / 2;
      this.camera.y += (height - this.viewportSize.height) / 2;
      this.viewportSize = { width, height };
      this.emitCamera();
    },
    fit() {
      if (!this.scene || !this.$refs.viewport) return;
      const { clientWidth, clientHeight } = this.$refs.viewport;
      const { width, height } = this.mapDimensions;
      const scale = clamp(
        Math.min(
          Math.max(1, clientWidth - 48) / width,
          Math.max(1, clientHeight - 48) / height,
        ),
        0.05,
        2,
      );
      this.camera = {
        scale,
        x: (clientWidth - width * scale) / 2,
        y: (clientHeight - height * scale) / 2,
      };
      this.viewportSize = { width: clientWidth, height: clientHeight };
      this.hasFitted = true;
      this.emitCamera();
    },
    zoomBy(factor, origin) {
      if (!this.scene || !this.$refs.viewport) return;
      const rect = this.$refs.viewport.getBoundingClientRect();
      const point = origin || {
        x: rect.width / 2,
        y: rect.height / 2,
      };
      const nextScale = clamp(this.camera.scale * factor, 0.05, 4);
      const mapX = (point.x - this.camera.x) / this.camera.scale;
      const mapY = (point.y - this.camera.y) / this.camera.scale;
      this.camera = {
        scale: nextScale,
        x: point.x - mapX * nextScale,
        y: point.y - mapY * nextScale,
      };
      this.emitCamera();
    },
    onWheel(event) {
      const rect = this.$refs.viewport.getBoundingClientRect();
      this.zoomBy(event.deltaY < 0 ? 1.12 : 1 / 1.12, {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top,
      });
    },
    onKeydown(event) {
      if (!this.scene) return;
      const pan = {
        ArrowLeft: [40, 0],
        ArrowRight: [-40, 0],
        ArrowUp: [0, 40],
        ArrowDown: [0, -40],
      }[event.key];
      if (pan) {
        event.preventDefault();
        this.camera.x += pan[0];
        this.camera.y += pan[1];
        this.emitCamera();
        return;
      }
      if (["+", "="].includes(event.key)) {
        event.preventDefault();
        this.zoomBy(1.2);
      } else if (["-", "_"].includes(event.key)) {
        event.preventDefault();
        this.zoomBy(1 / 1.2);
      } else if (event.key === "0" || event.key === "Home") {
        event.preventDefault();
        this.fit();
      }
    },
    startPan(event) {
      if (!this.scene || ![0, 1].includes(event.button)) return;
      this.dragging = true;
      this.pointer = {
        id: event.pointerId,
        x: event.clientX,
        y: event.clientY,
        cameraX: this.camera.x,
        cameraY: this.camera.y,
      };
      event.currentTarget.setPointerCapture?.(event.pointerId);
    },
    movePan(event) {
      if (!this.dragging || this.pointer?.id !== event.pointerId) return;
      this.camera.x = this.pointer.cameraX + event.clientX - this.pointer.x;
      this.camera.y = this.pointer.cameraY + event.clientY - this.pointer.y;
    },
    endPan(event) {
      if (this.pointer?.id !== event.pointerId) return;
      this.dragging = false;
      this.pointer = null;
      event.currentTarget.releasePointerCapture?.(event.pointerId);
      this.emitCamera();
    },
  },
};
</script>

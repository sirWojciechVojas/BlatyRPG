<template>
  <section
    ref="viewport"
    class="scene-canvas"
    :class="{
      'scene-canvas--dragging': dragging,
      [`scene-canvas--tool-${activeTool}`]: true,
    }"
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
    @dragover.prevent
    @drop.prevent="dropActor"
  >
    <SceneMeasurementOverlay
      :scene="scene"
      :active-tool="activeTool"
      :scale="camera.scale"
    />
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
        <SceneTokenLayer
          :tokens="tokens"
          :selected-id="selectedTokenId"
          :scale="camera.scale"
          :busy="tokenBusy"
          @select="$emit('token-select', $event)"
          @move="$emit('token-move', $event)"
          @update="$emit('token-update', $event)"
          @delete="$emit('token-delete', $event)"
          @open-actor="$emit('open-actor', $event)"
        />
      </div>
    </div>
  </section>
</template>

<script>
import { getCurrentInstance, nextTick } from "vue";
import { buildGridPattern } from "@/lib/vtt/grid";
import { canvasDropPosition, readDroppedActor } from "@/lib/vtt/tokenDrop";
import SceneTokenLayer from "@/components/vtt/token/SceneTokenLayer.vue";
import SceneMeasurementOverlay from "./SceneMeasurementOverlay.vue";
import { sceneCanvasCameraMethods } from "./sceneCanvasCameraMethods";

export default {
  name: "SceneCanvas",
  components: { SceneMeasurementOverlay, SceneTokenLayer },
  props: {
    scene: { type: Object, default: null },
    activeTool: { type: String, default: "select" },
    tokens: { type: Array, default: () => [] },
    selectedTokenId: { type: [Number, String], default: null },
    tokenBusy: { type: Boolean, default: false },
    canCreateToken: { type: Boolean, default: false },
  },
  emits: [
    "camera-change",
    "token-select",
    "token-move",
    "token-update",
    "token-delete",
    "token-create",
    "open-actor",
  ],
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
    ...sceneCanvasCameraMethods,
    dropActor(event) {
      if (!this.scene || !this.canCreateToken) return;
      const actor = readDroppedActor(event.dataTransfer);
      if (!actor) return;
      const position = canvasDropPosition(
        event,
        this.$refs.viewport,
        this.camera,
        this.mapDimensions.padding,
      );
      this.$emit("token-create", { actor, ...position });
    },
  },
};
</script>

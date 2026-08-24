<template>
  <div
    class="scene-light-layer"
    :class="{ 'scene-light-layer--active': active }"
  >
    <svg
      class="scene-light-layer__visual"
      :viewBox="viewBox"
      aria-hidden="true"
    >
      <defs>
        <template v-for="light in activeLights" :key="`defs-${light.id}`">
          <radialGradient
            :id="maskId(light)"
            gradientUnits="userSpaceOnUse"
            :cx="light.x"
            :cy="light.y"
            :r="light.dimRadius"
          >
            <stop
              offset="0%"
              stop-color="#000"
              :stop-opacity="light.intensity"
            />
            <stop
              :offset="brightOffset(light)"
              stop-color="#000"
              :stop-opacity="light.intensity"
            />
            <stop offset="100%" stop-color="#fff" stop-opacity="0" />
          </radialGradient>
          <radialGradient
            :id="glowId(light)"
            gradientUnits="userSpaceOnUse"
            :cx="light.x"
            :cy="light.y"
            :r="light.dimRadius"
          >
            <stop
              offset="0%"
              :stop-color="light.color"
              :stop-opacity="light.intensity * 0.38"
            />
            <stop offset="100%" :stop-color="light.color" stop-opacity="0" />
          </radialGradient>
        </template>
        <mask :id="darknessMaskId" mask-type="luminance">
          <rect width="100%" height="100%" fill="#fff" />
          <path
            v-for="light in activeLights"
            :key="`mask-${light.id}`"
            :d="lightPath(light)"
            :fill="`url(#${maskId(light)})`"
          />
        </mask>
      </defs>
      <rect
        width="100%"
        height="100%"
        fill="#020307"
        :fill-opacity="darkness"
        :mask="`url(#${darknessMaskId})`"
      />
      <path
        v-for="light in activeLights"
        :key="`glow-${light.id}`"
        :d="lightPath(light)"
        :fill="`url(#${glowId(light)})`"
      />
    </svg>
    <svg
      v-if="canManage && active"
      ref="editor"
      class="scene-light-layer__editor"
      :viewBox="viewBox"
      @pointermove="move"
      @pointerup="finish"
      @pointercancel="cancel"
    >
      <rect width="100%" height="100%" fill="transparent" @click="create" />
      <g v-for="light in lights" :key="light.id" :class="lightClasses(light)">
        <circle
          :cx="display(light).x"
          :cy="display(light).y"
          :r="light.dimRadius"
        />
        <circle
          class="scene-light__bright"
          :cx="display(light).x"
          :cy="display(light).y"
          :r="light.brightRadius"
        />
        <circle
          class="scene-light__source"
          :cx="display(light).x"
          :cy="display(light).y"
          r="11"
          @pointerdown.stop="startMove($event, light)"
          @click.stop="$emit('select', light.id)"
        />
      </g>
    </svg>
    <LightHud
      v-if="canManage && active && selectedLight"
      :light="selectedLight"
      :grid-size="scene.gridSize"
      :busy="busy"
      :style="hudStyle"
      @update="$emit('update', { light: selectedLight, changes: $event })"
      @delete="$emit('delete', selectedLight)"
    />
  </div>
</template>

<script>
import { getCurrentInstance } from "vue";
import { wallPoint } from "@/lib/vtt/wallGeometry";
import { lightPolygonPath } from "@/lib/vtt/lightGeometry";
import LightHud from "./LightHud.vue";

export default {
  name: "SceneLightLayer",
  components: { LightHud },
  props: {
    scene: { type: Object, required: true },
    lights: { type: Array, default: () => [] },
    walls: { type: Array, default: () => [] },
    activeTool: { type: String, default: "select" },
    selectedId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "create", "update", "delete"],
  data() {
    return { uid: getCurrentInstance().uid, drag: null, preview: null };
  },
  computed: {
    active() {
      return this.activeTool === "lights";
    },
    activeLights() {
      return this.lights.filter((light) => light.enabled);
    },
    lightPaths() {
      return Object.fromEntries(
        this.activeLights.map((light) => [
          light.id,
          lightPolygonPath(light, this.walls, this.scene),
        ]),
      );
    },
    selectedLight() {
      return this.lights.find((light) => light.id === this.selectedId) || null;
    },
    darkness() {
      return Math.min(1, Math.max(0, Number(this.scene.darknessLevel) || 0));
    },
    viewBox() {
      return `0 0 ${this.scene.width} ${this.scene.height}`;
    },
    darknessMaskId() {
      return `scene-darkness-${this.uid}`;
    },
    hudStyle() {
      return {
        left: `${this.selectedLight.x}px`,
        top: `${this.selectedLight.y}px`,
      };
    },
  },
  methods: {
    maskId(light) {
      return `light-mask-${this.uid}-${light.id}`;
    },
    glowId(light) {
      return `light-glow-${this.uid}-${light.id}`;
    },
    lightPath(light) {
      return this.lightPaths[light.id] || "";
    },
    brightOffset(light) {
      return `${Math.min(100, (light.brightRadius / Math.max(1, light.dimRadius)) * 100)}%`;
    },
    point(event) {
      return wallPoint(event, this.$refs.editor, this.scene, !event.altKey);
    },
    create(event) {
      if (this.busy) return;
      const point = this.point(event);
      const grid = Math.max(1, Number(this.scene.gridSize) || 100);
      this.$emit("create", {
        ...point,
        brightRadius: grid * 2,
        dimRadius: grid * 4,
      });
    },
    startMove(event, light) {
      if (this.busy || event.button !== 0) return;
      this.$emit("select", light.id);
      this.drag = { pointerId: event.pointerId, light };
      this.preview = { x: light.x, y: light.y };
      event.currentTarget.setPointerCapture?.(event.pointerId);
    },
    move(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      this.preview = this.point(event);
    },
    finish(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      const { light } = this.drag;
      const point = this.preview;
      this.cancel();
      if (point.x !== light.x || point.y !== light.y) {
        this.$emit("update", { light, changes: point });
      }
    },
    cancel() {
      this.drag = null;
      this.preview = null;
    },
    display(light) {
      return this.drag?.light.id === light.id ? this.preview : light;
    },
    lightClasses(light) {
      return [
        "scene-light",
        {
          "scene-light--selected": light.id === this.selectedId,
          "scene-light--disabled": !light.enabled,
        },
      ];
    },
  },
};
</script>

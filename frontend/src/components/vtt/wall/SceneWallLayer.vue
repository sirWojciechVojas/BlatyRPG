<template>
  <div
    v-if="canManage"
    class="scene-wall-layer"
    :class="{ 'scene-wall-layer--active': active }"
  >
    <svg
      ref="surface"
      :width="scene.width"
      :height="scene.height"
      :viewBox="`0 0 ${scene.width} ${scene.height}`"
      @pointerdown="begin"
      @pointermove="move"
      @pointerup="finish"
      @pointercancel="cancel"
      @contextmenu.prevent="cancel"
    >
      <g v-for="wall in walls" :key="wall.id" :class="wallClasses(wall)">
        <line :x1="wall.x1" :y1="wall.y1" :x2="wall.x2" :y2="wall.y2" />
        <line
          class="scene-wall__hit"
          :x1="wall.x1"
          :y1="wall.y1"
          :x2="wall.x2"
          :y2="wall.y2"
          @pointerdown.stop="$emit('select', wall.id)"
          @dblclick.stop="toggleDoor(wall)"
        />
        <template v-if="wall.id === selectedId">
          <circle :cx="wall.x1" :cy="wall.y1" r="5" />
          <circle :cx="wall.x2" :cy="wall.y2" r="5" />
        </template>
      </g>
      <line
        v-if="draft"
        class="scene-wall scene-wall--draft"
        :x1="draft.x1"
        :y1="draft.y1"
        :x2="draft.x2"
        :y2="draft.y2"
      />
    </svg>
    <WallHud
      v-if="selectedWall"
      :wall="selectedWall"
      :busy="busy"
      :style="hudStyle"
      @update="$emit('update', { wall: selectedWall, changes: $event })"
      @delete="$emit('delete', selectedWall)"
    />
  </div>
</template>

<script>
import { wallLength, wallMidpoint, wallPoint } from "@/lib/vtt/wallGeometry";
import WallHud from "./WallHud.vue";

export default {
  name: "SceneWallLayer",
  components: { WallHud },
  props: {
    scene: { type: Object, required: true },
    walls: { type: Array, default: () => [] },
    activeTool: { type: String, default: "select" },
    selectedId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ["select", "create", "update", "delete"],
  data: () => ({ draft: null, pointerId: null }),
  computed: {
    active() {
      return ["walls", "doors"].includes(this.activeTool);
    },
    selectedWall() {
      return this.walls.find((wall) => wall.id === this.selectedId) || null;
    },
    hudStyle() {
      const point = wallMidpoint(this.selectedWall);
      return { left: `${point.x}px`, top: `${point.y}px` };
    },
  },
  watch: {
    active(value) {
      if (!value) this.cancel();
    },
  },
  methods: {
    point(event) {
      return wallPoint(event, this.$refs.surface, this.scene, !event.altKey);
    },
    begin(event) {
      if (!this.active || this.busy || event.button !== 0) return;
      const start = this.point(event);
      this.$emit("select", null);
      this.draft = { x1: start.x, y1: start.y, x2: start.x, y2: start.y };
      this.pointerId = event.pointerId;
      event.currentTarget.setPointerCapture?.(event.pointerId);
    },
    move(event) {
      if (!this.draft || event.pointerId !== this.pointerId) return;
      const end = this.point(event);
      this.draft = { ...this.draft, x2: end.x, y2: end.y };
    },
    finish(event) {
      if (!this.draft || event.pointerId !== this.pointerId) return;
      this.move(event);
      const draft = this.draft;
      this.cancel(event);
      if (wallLength(draft) < 2) return;
      this.$emit("create", {
        ...draft,
        type: this.activeTool === "doors" ? "door" : "wall",
      });
    },
    cancel(event) {
      if (event && this.pointerId !== null) {
        event.currentTarget.releasePointerCapture?.(this.pointerId);
      }
      this.draft = null;
      this.pointerId = null;
    },
    wallClasses(wall) {
      return [
        "scene-wall",
        `scene-wall--${wall.type}`,
        `scene-wall--${wall.doorState || "solid"}`,
        { "scene-wall--selected": wall.id === this.selectedId },
      ];
    },
    toggleDoor(wall) {
      if (wall.type === "wall") return;
      this.$emit("update", {
        wall,
        changes: { doorState: wall.doorState === "open" ? "closed" : "open" },
      });
    },
  },
};
</script>

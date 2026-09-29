<template>
  <div
    v-if="canManage"
    class="scene-wall-layer"
    :class="{
      'scene-wall-layer--active': active,
      'scene-wall-layer--inserting': ['door', 'window'].includes(
        interactionMode,
      ),
    }"
    @pointerdown.capture="handleLayerPointerDown"
    @contextmenu="handleContextMenu"
  >
    <svg
      v-if="active"
      ref="surface"
      :width="scene.width"
      :height="scene.height"
      :viewBox="`0 0 ${scene.width} ${scene.height}`"
      tabindex="0"
      @auxclick="handleSurfaceAuxClick"
      @contextmenu.capture.prevent.stop="handleContextMenu"
      @mousedown.right.prevent.stop="handleContextMenu"
      @mouseup.right.prevent.stop="handleContextMenu"
      @pointerdown.stop="beginCanvas"
      @pointermove="move"
      @pointerup="finish"
      @pointercancel="cancel"
      @keydown="keyboard"
    >
      <rect class="scene-wall-layer__surface" width="100%" height="100%" />
      <g
        v-for="wall in visibleWalls"
        :key="wall.id"
        :class="wallClasses(wall)"
        :style="{ '--wall-color': color(wall) }"
      >
        <line
          :x1="display(wall).x1"
          :y1="display(wall).y1"
          :x2="display(wall).x2"
          :y2="display(wall).y2"
        />
        <line
          class="scene-wall__hit"
          :x1="display(wall).x1"
          :y1="display(wall).y1"
          :x2="display(wall).x2"
          :y2="display(wall).y2"
          @pointerdown.stop="startSegmentMove($event, wall)"
          @dblclick.stop="toggleDoor(wall)"
        />
        <template v-if="wall.id === selectedId">
          <circle
            class="scene-wall__point"
            :cx="display(wall).x1"
            :cy="display(wall).y1"
            r="6"
            @pointerdown.stop="startEndpointMove($event, wall, 'start')"
          />
          <circle
            class="scene-wall__point"
            :cx="display(wall).x2"
            :cy="display(wall).y2"
            r="6"
            @pointerdown.stop="startEndpointMove($event, wall, 'end')"
          />
        </template>
      </g>
      <polyline
        v-if="drawPointList"
        class="scene-wall-path--draft"
        :points="drawPointList"
        :style="{ '--wall-color': color({ type: 'wall' }) }"
      />
      <circle
        v-if="drawPoints.length"
        class="scene-wall-draw__origin"
        :cx="drawPoints[0].x"
        :cy="drawPoints[0].y"
        r="8"
        @pointerdown.stop="addDrawPoint"
      />
      <circle
        v-if="connectPoints && snapCandidate"
        class="scene-wall-connect__candidate"
        :cx="snapCandidate.x"
        :cy="snapCandidate.y"
        r="10"
      />
    </svg>
    <Teleport v-if="active && toolbarTarget" :to="toolbarTarget">
      <WallToolToolbar
        :wall="selectedWall"
        :interaction-mode="interactionMode"
        :connect-points="connectPoints"
        :snap-to-grid="snapToGrid"
        :count="walls.length"
        :list-open="listOpen"
        :all-enabled="allEnabled"
        :all-visible="allVisible"
        :busy="busy"
        :drawing="drawPoints.length > 1"
        @mode="setInteractionMode"
        @finish-drawing="finishDrawing"
        @connect="connectPoints = $event"
        @snap="snapToGrid = $event"
        @update="updateSelected"
        @toggle-all-enabled="toggleAllEnabled"
        @toggle-all-visible="toggleAllVisible"
        @toggle-list="toggleManager"
        @edit="openProperties"
        @delete="$emit('delete', selectedWall)"
      />
    </Teleport>
    <Teleport v-if="active && listOpen" to="body">
      <WallManagementPanel
        :walls="walls"
        :selected-id="selectedId"
        :all-enabled="allEnabled"
        :all-visible="allVisible"
        :busy="busy"
        @select="$emit('select', $event)"
        @focus="focusWall"
        @add="addDefault"
        @update="$emit('update', $event)"
        @edit="editWall"
        @delete="$emit('delete', $event)"
        @toggle-all-enabled="toggleAllEnabled"
        @toggle-all-visible="toggleAllVisible"
      />
    </Teleport>
    <Teleport v-if="active && selectedWall && propertiesOpen" to="body">
      <WallPropertiesPanel
        :key="selectedWall.id"
        :wall="selectedWall"
        :busy="busy"
        @save="saveProperties"
        @close="propertiesOpen = false"
      />
    </Teleport>
  </div>
</template>

<script>
import {
  connectedWallEndpoints,
  nearestWallEndpoint,
  resolveWallPoint,
  splitWallForOpening,
  wallColor,
  wallLength,
  wallPoint,
} from "@/lib/vtt/wallGeometry";
import WallManagementPanel from "./WallManagementPanel.vue";
import WallPropertiesPanel from "./WallPropertiesPanel.vue";
import WallToolToolbar from "./WallToolToolbar.vue";

export default {
  name: "SceneWallLayer",
  components: {
    WallManagementPanel,
    WallPropertiesPanel,
    WallToolToolbar,
  },
  props: {
    scene: { type: Object, required: true },
    walls: { type: Array, default: () => [] },
    activeTool: { type: String, default: "select" },
    selectedId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    toolbarTarget: { type: String, default: "" },
  },
  emits: [
    "select",
    "create",
    "create-many",
    "insert-opening",
    "update",
    "update-many",
    "delete",
  ],
  data: () => ({
    drag: null,
    preview: null,
    drawPoints: [],
    hoverPoint: null,
    snapCandidate: null,
    interactionMode: "draw",
    connectPoints: true,
    snapToGrid: true,
    listOpen: true,
    propertiesOpen: false,
  }),
  computed: {
    active() {
      return this.activeTool === "walls";
    },
    selectedWall() {
      return this.walls.find((wall) => wall.id === this.selectedId) || null;
    },
    visibleWalls() {
      return this.walls.filter(
        (wall) => !wall.hidden || wall.id === this.selectedId,
      );
    },
    allEnabled() {
      return (
        this.walls.length === 0 || this.walls.every((wall) => wall.enabled)
      );
    },
    allVisible() {
      return (
        this.walls.length === 0 || this.walls.every((wall) => !wall.hidden)
      );
    },
    drawPointList() {
      if (!this.drawPoints.length) return "";
      const points = this.hoverPoint
        ? [...this.drawPoints, this.hoverPoint]
        : this.drawPoints;
      return points.map(({ x, y }) => `${x},${y}`).join(" ");
    },
  },
  watch: {
    active(value) {
      if (!value) this.cancel();
    },
    selectedWall(value) {
      if (!value) this.propertiesOpen = false;
    },
  },
  mounted() {
    window.addEventListener(
      "pointerdown",
      this.handleDocumentPointerDown,
      true,
    );
    window.addEventListener(
      "contextmenu",
      this.handleDocumentContextMenu,
      true,
    );
  },
  beforeUnmount() {
    window.removeEventListener(
      "pointerdown",
      this.handleDocumentPointerDown,
      true,
    );
    window.removeEventListener(
      "contextmenu",
      this.handleDocumentContextMenu,
      true,
    );
  },
  methods: {
    color: wallColor,
    point(event, excludedWallId = null, connect = true) {
      const resolved = resolveWallPoint(
        event,
        this.$refs.surface,
        this.scene,
        this.walls,
        {
          snapToGrid: this.snapToGrid && !event.altKey,
          connect: connect && this.connectPoints && !event.altKey,
          tolerance: this.connectionTolerance(),
          excludedWallId,
        },
      );
      this.snapCandidate = resolved.connection
        ? {
            x: resolved.connection.x,
            y: resolved.connection.y,
            wallId: resolved.connection.wall.id,
          }
        : null;
      return resolved.point;
    },
    connectionTolerance() {
      const rect = this.$refs.surface?.getBoundingClientRect?.();
      if (!rect?.width || !rect?.height) return 14;
      const horizontal = Number(this.scene.width) / rect.width;
      const vertical = Number(this.scene.height) / rect.height;
      return Math.max(4, Math.max(horizontal, vertical) * 14);
    },
    beginCanvas(event) {
      if (
        !this.active ||
        this.busy ||
        event.button !== 0 ||
        !event.target.classList.contains("scene-wall-layer__surface")
      )
        return;
      if (this.interactionMode === "select") {
        this.$emit("select", null);
        return;
      }
      if (["door", "window"].includes(this.interactionMode)) return;
      if (this.interactionMode === "draw") {
        this.addDrawPoint(event);
        return;
      }
    },
    handleLayerPointerDown(event) {
      if (
        !this.active ||
        event.button !== 2 ||
        this.interactionMode !== "draw"
      ) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      if (this.drawPoints.length) this.finishDrawing();
    },
    isWallSurfaceEvent(event) {
      return Boolean(
        this.active &&
        this.$refs.surface &&
        event.target instanceof Node &&
        this.$refs.surface.contains(event.target),
      );
    },
    finishDrawingFromPointer(event) {
      if (this.interactionMode !== "draw" || !this.isWallSurfaceEvent(event)) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      if (this.drawPoints.length) this.finishDrawing();
    },
    handleDocumentPointerDown(event) {
      if (event.button === 2) this.finishDrawingFromPointer(event);
    },
    handleDocumentContextMenu(event) {
      this.finishDrawingFromPointer(event);
    },
    handleSurfaceAuxClick(event) {
      if (event.button === 2) this.finishDrawingFromPointer(event);
    },
    handleContextMenu(event) {
      this.finishDrawingFromPointer(event);
    },
    startSegmentMove(event, wall) {
      if (this.busy || event.button !== 0) return;
      if (["door", "window"].includes(this.interactionMode)) {
        this.insertOpening(event, wall, this.interactionMode);
        return;
      }
      if (this.interactionMode === "draw") {
        this.addDrawPoint(event);
        return;
      }
      this.interactionMode = "select";
      this.$emit("select", wall.id);
      this.drag = {
        type: "segment",
        pointerId: event.pointerId,
        wall,
        origin: wallPoint(event, this.$refs.surface, this.scene, false),
        snappedOrigin: wallPoint(event, this.$refs.surface, this.scene, true),
      };
      this.preview = { x1: wall.x1, y1: wall.y1, x2: wall.x2, y2: wall.y2 };
      this.capture(event.pointerId);
    },
    startEndpointMove(event, wall, endpoint) {
      if (this.busy || event.button !== 0) return;
      if (["door", "window"].includes(this.interactionMode)) {
        this.insertOpening(event, wall, this.interactionMode);
        return;
      }
      if (this.interactionMode === "draw") {
        this.addDrawPoint(event);
        return;
      }
      const original =
        endpoint === "start"
          ? { x: wall.x1, y: wall.y1 }
          : { x: wall.x2, y: wall.y2 };
      this.drag = {
        type: "endpoint",
        pointerId: event.pointerId,
        wall,
        endpoint,
        original,
        connected: this.connectPoints
          ? connectedWallEndpoints(this.walls, original)
          : [],
      };
      this.preview = { x1: wall.x1, y1: wall.y1, x2: wall.x2, y2: wall.y2 };
      this.capture(event.pointerId);
    },
    move(event) {
      if (
        !this.drag &&
        this.interactionMode === "draw" &&
        this.drawPoints.length
      ) {
        this.hoverPoint = this.drawPreviewPoint(event);
        return;
      }
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      if (this.drag.type === "endpoint") {
        const point = this.point(event, this.drag.wall.id);
        const prefix = this.drag.endpoint === "start" ? "1" : "2";
        this.preview = {
          ...this.preview,
          [`x${prefix}`]: point.x,
          [`y${prefix}`]: point.y,
        };
        return;
      }
      const snapped = this.snapToGrid && !event.altKey;
      const point = wallPoint(event, this.$refs.surface, this.scene, snapped);
      const wall = this.drag.wall;
      const origin = snapped ? this.drag.snappedOrigin : this.drag.origin;
      let dx = point.x - origin.x;
      let dy = point.y - origin.y;
      dx = Math.max(-Math.min(wall.x1, wall.x2), dx);
      dx = Math.min(Number(this.scene.width) - Math.max(wall.x1, wall.x2), dx);
      dy = Math.max(-Math.min(wall.y1, wall.y2), dy);
      dy = Math.min(Number(this.scene.height) - Math.max(wall.y1, wall.y2), dy);
      const connection = this.segmentConnection(wall, dx, dy, event.altKey);
      if (connection) {
        dx += connection.dx;
        dy += connection.dy;
        this.snapCandidate = connection.target;
      } else {
        this.snapCandidate = null;
      }
      this.preview = {
        x1: wall.x1 + dx,
        y1: wall.y1 + dy,
        x2: wall.x2 + dx,
        y2: wall.y2 + dy,
      };
    },
    segmentConnection(wall, dx, dy, bypass) {
      if (!this.connectPoints || bypass) return null;
      const moved = [
        { x: Number(wall.x1) + dx, y: Number(wall.y1) + dy },
        { x: Number(wall.x2) + dx, y: Number(wall.y2) + dy },
      ];
      const candidates = moved
        .map((point) => {
          const nearest = nearestWallEndpoint(
            point,
            this.walls,
            this.connectionTolerance(),
            wall.id,
          );
          if (!nearest) return null;
          return {
            dx: nearest.x - point.x,
            dy: nearest.y - point.y,
            distance: nearest.distance,
            target: { x: nearest.x, y: nearest.y, wallId: nearest.wall.id },
          };
        })
        .filter(Boolean)
        .sort((left, right) => left.distance - right.distance);
      const candidate = candidates[0];
      if (!candidate) return null;
      const shifted = {
        x1: Number(wall.x1) + dx + candidate.dx,
        y1: Number(wall.y1) + dy + candidate.dy,
        x2: Number(wall.x2) + dx + candidate.dx,
        y2: Number(wall.y2) + dy + candidate.dy,
      };
      const inside =
        shifted.x1 >= 0 &&
        shifted.y1 >= 0 &&
        shifted.x2 >= 0 &&
        shifted.y2 >= 0 &&
        shifted.x1 <= Number(this.scene.width) &&
        shifted.x2 <= Number(this.scene.width) &&
        shifted.y1 <= Number(this.scene.height) &&
        shifted.y2 <= Number(this.scene.height);
      return inside ? candidate : null;
    },
    finish(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      this.move(event);
      const drag = this.drag;
      const preview = this.preview;
      this.cancel();
      if (drag.type === "segment") {
        if (
          Object.keys(preview).some((key) => preview[key] !== drag.wall[key])
        ) {
          this.$emit("update", { wall: drag.wall, changes: preview });
        }
        return;
      }
      this.finishEndpoint(drag, preview);
    },
    setInteractionMode(mode) {
      this.cancel();
      this.interactionMode = mode;
    },
    closeTolerance() {
      const rect = this.$refs.surface?.getBoundingClientRect?.();
      const sceneUnitsPerPixel = rect?.width
        ? Number(this.scene.width) / rect.width
        : 1;
      return Math.max(6, sceneUnitsPerPixel * 14);
    },
    drawPreviewPoint(event) {
      const point = this.point(event);
      const origin = this.drawPoints[0];
      if (
        origin &&
        this.drawPoints.length >= 3 &&
        Math.hypot(point.x - origin.x, point.y - origin.y) <=
          this.closeTolerance()
      ) {
        return { ...origin };
      }
      return point;
    },
    addDrawPoint(event) {
      if (this.busy || event.button !== 0) return;
      const point = this.drawPreviewPoint(event);
      if (!this.drawPoints.length) {
        this.$emit("select", null);
        this.drawPoints = [point];
        this.hoverPoint = point;
        this.$refs.surface?.focus?.();
        return;
      }
      const previous = this.drawPoints[this.drawPoints.length - 1];
      if (Math.hypot(point.x - previous.x, point.y - previous.y) < 2) return;
      const closesPolygon =
        this.drawPoints.length >= 3 &&
        Math.hypot(
          point.x - this.drawPoints[0].x,
          point.y - this.drawPoints[0].y,
        ) <= this.closeTolerance();
      const points = [
        ...this.drawPoints,
        closesPolygon ? { ...this.drawPoints[0] } : point,
      ];
      if (closesPolygon || points.length >= 31) {
        this.finishDrawing(points);
        return;
      }
      this.drawPoints = points;
      this.hoverPoint = point;
    },
    finishDrawing(value) {
      if (this.interactionMode !== "draw") {
        this.cancel();
        return;
      }
      const points = Array.isArray(value) ? value : this.drawPoints;
      this.drawPoints = [];
      this.hoverPoint = null;
      this.snapCandidate = null;
      const drafts = points
        .slice(1)
        .map((point, index) => ({
          name: `${this.$t("vtt.wall.defaultName")} ${
            this.walls.length + index + 1
          }`,
          type: "wall",
          x1: points[index].x,
          y1: points[index].y,
          x2: point.x,
          y2: point.y,
        }))
        .filter((wall) => wallLength(wall) >= 2);
      drafts.forEach((draft) => this.$emit("create", draft));
    },
    finishEndpoint(drag, preview) {
      const prefix = drag.endpoint === "start" ? "1" : "2";
      const point = { x: preview[`x${prefix}`], y: preview[`y${prefix}`] };
      const connected = drag.connected.length
        ? drag.connected
        : [{ wall: drag.wall, endpoint: drag.endpoint }];
      const updates = connected.map(({ wall, endpoint }) => {
        const suffix = endpoint === "start" ? "1" : "2";
        return {
          wall,
          changes: { [`x${suffix}`]: point.x, [`y${suffix}`]: point.y },
        };
      });
      this.$emit("update-many", updates);
    },
    cancel() {
      if (this.drag) this.release(this.drag.pointerId);
      this.drag = null;
      this.preview = null;
      this.drawPoints = [];
      this.hoverPoint = null;
      this.snapCandidate = null;
    },
    capture(pointerId) {
      this.$refs.surface?.setPointerCapture?.(pointerId);
      this.$refs.surface?.focus?.();
    },
    release(pointerId) {
      if (this.$refs.surface?.hasPointerCapture?.(pointerId)) {
        this.$refs.surface.releasePointerCapture(pointerId);
      }
    },
    display(wall) {
      return this.drag?.wall?.id === wall.id && this.preview
        ? { ...wall, ...this.preview }
        : wall;
    },
    wallClasses(wall) {
      return [
        "scene-wall",
        `scene-wall--${wall.type}`,
        `scene-wall--${wall.doorState || "solid"}`,
        {
          "scene-wall--selected": wall.id === this.selectedId,
          "scene-wall--disabled": !wall.enabled,
          "scene-wall--hidden": wall.hidden,
        },
      ];
    },
    insertOpening(event, wall, type) {
      if (wall.type !== "wall") return;
      const grid = Math.max(20, Number(this.scene.gridSize) || 100);
      const point = wallPoint(
        event,
        this.$refs.surface,
        this.scene,
        this.snapToGrid && !event.altKey,
      );
      const split = splitWallForOpening(
        wall,
        point,
        grid * 0.8,
        Math.max(2, grid / 10),
      );
      if (!split) return;
      const inherited = {
        blocksMovement: wall.blocksMovement,
        blocksSight: wall.blocksSight,
        blocksLight: wall.blocksLight,
        enabled: wall.enabled,
        hidden: wall.hidden,
        color: wall.color,
      };
      const openingName =
        type === "window"
          ? this.$t("vtt.wall.defaultWindowName")
          : this.$t("vtt.wall.defaultDoorName");
      this.$emit("insert-opening", {
        wall,
        changes: split.before,
        drafts: [
          {
            ...split.opening,
            name: `${openingName} ${this.walls.length + 1}`,
            type,
            blocksMovement: true,
            blocksSight: type === "window" ? false : wall.blocksSight,
            blocksLight: type === "window" ? false : wall.blocksLight,
            ...(type === "door" ? { doorState: "closed" } : {}),
            enabled: wall.enabled,
            hidden: wall.hidden,
          },
          {
            ...split.after,
            ...inherited,
            name: `${wall.name} — B`,
            type: "wall",
          },
        ],
      });
    },
    toggleDoor(wall) {
      if (!["door", "secret"].includes(wall.type)) return;
      this.$emit("update", {
        wall,
        changes: { doorState: wall.doorState === "open" ? "closed" : "open" },
      });
    },
    updateSelected(changes) {
      if (this.selectedWall) {
        this.$emit("update", { wall: this.selectedWall, changes });
      }
    },
    toggleAllEnabled() {
      this.$emit(
        "update-many",
        this.walls.map((wall) => ({
          wall,
          changes: { enabled: !this.allEnabled },
        })),
      );
    },
    toggleAllVisible() {
      this.$emit(
        "update-many",
        this.walls.map((wall) => ({
          wall,
          changes: { hidden: this.allVisible },
        })),
      );
    },
    addDefault() {
      const grid = Math.max(20, Number(this.scene.gridSize) || 100);
      const x1 = Math.max(0, Number(this.scene.width) / 2 - grid);
      const x2 = Math.min(Number(this.scene.width), x1 + grid * 2);
      const y = Number(this.scene.height) / 2;
      this.$emit("create", {
        name: `${this.$t("vtt.wall.defaultName")} ${this.walls.length + 1}`,
        type: "wall",
        x1,
        y1: y,
        x2,
        y2: y,
      });
    },
    focusWall(wallId) {
      this.$emit("select", wallId);
      this.interactionMode = "select";
    },
    editWall(wallId) {
      this.focusWall(wallId);
      this.listOpen = false;
      this.propertiesOpen = true;
    },
    openProperties() {
      if (!this.selectedWall) return;
      this.listOpen = false;
      this.propertiesOpen = true;
    },
    toggleManager() {
      this.listOpen = !this.listOpen;
      if (this.listOpen) this.propertiesOpen = false;
    },
    saveProperties(changes) {
      this.updateSelected(changes);
      this.propertiesOpen = false;
    },
    keyboard(event) {
      if (event.key === "Escape") this.cancel();
      if (event.key === "Enter" && this.drawPoints.length > 1) {
        event.preventDefault();
        this.finishDrawing();
      }
      if (["Delete", "Backspace"].includes(event.key) && this.selectedWall) {
        event.preventDefault();
        this.$emit("delete", this.selectedWall);
      }
    },
  },
};
</script>

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
    <WallSoundZoneOverlay
      v-if="active && propertiesOpen && selectedWall && soundPreviewConfig"
      :scene="scene"
      :wall="selectedWall"
      :config="soundPreviewConfig"
      :active-rule-id="activeSoundRuleId"
      :scale="scale"
      @geometry-change="applySoundGeometry"
    />
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
          @dblclick.stop="editWall(wall.id)"
        />
        <template v-if="isSelectedWall(wall)">
          <g
            v-for="endpoint in ['start', 'end']"
            :key="`${wall.id}-${endpoint}`"
            class="scene-wall__vertex"
            :class="{
              'scene-wall__vertex--active': isActiveWallVertex(wall, endpoint),
            }"
            role="button"
            :aria-label="wallVertexLabel(wall, endpoint)"
            @pointerdown.stop.prevent="
              startEndpointMove($event, wall, endpoint)
            "
            @dblclick.stop.prevent="editWall(wall.id)"
          >
            <circle
              class="scene-wall__vertex-hit"
              :cx="endpointPoint(wall, endpoint).x"
              :cy="endpointPoint(wall, endpoint).y"
              :r="screenSize(16)"
            />
            <circle
              class="scene-wall__point"
              :cx="endpointPoint(wall, endpoint).x"
              :cy="endpointPoint(wall, endpoint).y"
              :r="screenSize(7)"
            />
            <circle
              class="scene-wall__point-core"
              :cx="endpointPoint(wall, endpoint).x"
              :cy="endpointPoint(wall, endpoint).y"
              :r="screenSize(2.5)"
            />
            <title>{{ $t("vtt.wall.vertexHint") }}</title>
            <g
              v-if="isActiveWallVertex(wall, endpoint)"
              class="scene-wall__vertex-readout"
              :transform="`translate(${
                endpointPoint(wall, endpoint).x + screenSize(12)
              } ${endpointPoint(wall, endpoint).y - screenSize(23)})`"
            >
              <rect
                :width="screenSize(132)"
                :height="screenSize(21)"
                :rx="screenSize(4)"
              />
              <text
                :x="screenSize(7)"
                :y="screenSize(14)"
                :style="{ fontSize: `${screenSize(10)}px` }"
              >
                {{ wallVertexLabel(wall, endpoint) }}
              </text>
            </g>
          </g>
        </template>
      </g>
      <rect
        v-if="selectionBox"
        class="scene-wall-selection-box"
        :x="selectionBox.x"
        :y="selectionBox.y"
        :width="selectionBox.width"
        :height="selectionBox.height"
      />
      <polyline
        v-if="drawPointList"
        class="scene-wall-path--draft"
        :points="drawPointList"
        :style="{ '--wall-color': color({ type: 'wall' }) }"
      />
      <line
        v-if="drag?.type === 'create' && preview"
        class="scene-wall-path--draft"
        :x1="preview.x1"
        :y1="preview.y1"
        :x2="preview.x2"
        :y2="preview.y2"
      />
      <circle
        v-for="(gap, index) in gapWarnings"
        :key="`gap-${index}`"
        class="scene-wall-gap-warning"
        :cx="gap.x"
        :cy="gap.y"
        r="8"
      />
      <line
        v-if="regionGap"
        class="scene-wall-region-gap"
        :x1="regionGap.from.x"
        :y1="regionGap.from.y"
        :x2="regionGap.to.x"
        :y2="regionGap.to.y"
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
        :preset="activePreset"
        :selected-count="selectedWalls.length"
        @mode="setInteractionMode"
        @finish-drawing="finishDrawing"
        @connect="connectPoints = $event"
        @snap="snapToGrid = $event"
        @update="updateSelected"
        @toggle-all-enabled="toggleAllEnabled"
        @toggle-all-visible="toggleAllVisible"
        @toggle-list="toggleManager"
        @edit="openProperties"
        @delete="deleteSelected"
        @preset="activePreset = $event"
        @close-doors="closeAllDoors"
        @delete-all="deleteAllWalls"
        @create-region="createRegionFromSelection"
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
      <TableFloatingWindow
        :model="propertiesWindow"
        :title="$t('vtt.wall.properties')"
        :subtitle="selectedWall.name"
        icon="wall"
        @move="movePropertiesWindow"
        @resize="resizePropertiesWindow"
        @layer-change="propertiesWindow.z = $event.z"
        @minimize="propertiesWindow.minimized = !propertiesWindow.minimized"
        @close="closeProperties"
      >
        <WallPropertiesPanel
          :key="selectedWall.id"
          :wall="selectedWall"
          :scene="scene"
          :busy="busy"
          :geometry-patch="soundGeometryPatch"
          floating
          @save="saveProperties"
          @close="closeProperties"
          @preview-change="soundPreviewConfig = $event"
          @preview-clear="clearSoundPreview"
          @active-sound-rule="activeSoundRuleId = $event"
        />
      </TableFloatingWindow>
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
import WallSoundZoneOverlay from "./WallSoundZoneOverlay.vue";
import TableFloatingWindow from "@/components/vtt/table/TableFloatingWindow.vue";
import { focusTableWindow } from "@/components/vtt/table/tableWindowLayers";
import { wallPreset } from "@/lib/vtt/wallPresets";
import { wallsToPolygons } from "@/lib/vtt/regionGeometry";

export default {
  name: "SceneWallLayer",
  components: {
    WallManagementPanel,
    WallPropertiesPanel,
    WallToolToolbar,
    WallSoundZoneOverlay,
    TableFloatingWindow,
  },
  props: {
    scene: { type: Object, required: true },
    walls: { type: Array, default: () => [] },
    activeTool: { type: String, default: "select" },
    selectedId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    toolbarTarget: { type: String, default: "" },
    scale: { type: Number, default: 1 },
  },
  emits: [
    "select",
    "create",
    "create-many",
    "insert-opening",
    "update",
    "update-many",
    "delete",
    "create-region",
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
    listOpen: false,
    propertiesOpen: false,
    soundPreviewConfig: null,
    activeSoundRuleId: null,
    soundGeometryPatch: null,
    activePreset: "solid",
    clipboard: [],
    selectedIds: [],
    groupPreview: {},
    selectionBox: null,
    regionGap: null,
    activeVertex: null,
    vertexNudgePreview: {},
    vertexNudgeContext: null,
    vertexNudgeTimer: null,
    propertiesWindow: {
      id: "wall-properties",
      windowType: "wall-properties",
      x: 82,
      y: 110,
      width: 620,
      height: 570,
      minWidth: 390,
      minHeight: 380,
      z: 920,
      resizable: true,
      maximizable: false,
      minimized: false,
      constrainToViewport: true,
    },
  }),
  computed: {
    active() {
      return this.activeTool === "walls";
    },
    selectedWall() {
      return this.walls.find((wall) => wall.id === this.selectedId) || null;
    },
    selectedWalls() {
      const ids = new Set(this.selectedIds.map(Number));
      return this.walls.filter((wall) => ids.has(Number(wall.id)));
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
    gapWarnings() {
      const threshold = Math.max(2, Number(this.scene.gridSize || 100) * 0.08);
      const points = this.walls.flatMap((wall) => [
        { x: Number(wall.x1), y: Number(wall.y1), id: wall.id },
        { x: Number(wall.x2), y: Number(wall.y2), id: wall.id },
      ]);
      return points.filter((point, index) =>
        points.some((other, otherIndex) => {
          if (index === otherIndex || point.id === other.id) return false;
          const distance = Math.hypot(point.x - other.x, point.y - other.y);
          return distance > 0.01 && distance < threshold;
        }),
      );
    },
  },
  watch: {
    active(value) {
      if (!value) {
        this.flushWallVertexNudge();
        this.cancel();
        this.closeProperties();
      }
    },
    selectedWall(value) {
      if (!value) this.closeProperties();
      if (
        this.activeVertex &&
        !this.selectedIds.map(Number).includes(Number(this.activeVertex.wallId))
      ) {
        this.flushWallVertexNudge();
        this.activeVertex = null;
      }
      if (value && !this.selectedIds.map(Number).includes(Number(value.id))) {
        this.selectedIds = [value.id];
      }
    },
  },
  mounted() {
    if (this.selectedWall) this.selectedIds = [this.selectedWall.id];
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
    this.flushWallVertexNudge();
  },
  methods: {
    color: wallColor,
    screenSize(value) {
      return Number(value) / Math.max(0.1, Number(this.scale) || 1);
    },
    isSelectedWall(wall) {
      return this.selectedIds.map(Number).includes(Number(wall.id));
    },
    endpointPoint(wall, endpoint) {
      const value = this.display(wall);
      return endpoint === "start"
        ? { x: Number(value.x1), y: Number(value.y1) }
        : { x: Number(value.x2), y: Number(value.y2) };
    },
    isActiveWallVertex(wall, endpoint) {
      return Boolean(
        this.activeVertex &&
        Number(this.activeVertex.wallId) === Number(wall.id) &&
        this.activeVertex.endpoint === endpoint,
      );
    },
    wallVertexLabel(wall, endpoint) {
      const point = this.endpointPoint(wall, endpoint);
      const name = endpoint === "start" ? "A" : "B";
      return `${name}  ${Math.round(point.x)}, ${Math.round(point.y)}`;
    },
    point(event, excludedWallId = null, connect = true) {
      const resolved = resolveWallPoint(
        event,
        this.$refs.surface,
        this.scene,
        this.walls,
        {
          snapToGrid: this.snapToGrid && !event.shiftKey,
          connect: connect && this.connectPoints && !event.shiftKey,
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
        const origin = wallPoint(event, this.$refs.surface, this.scene, false);
        this.drag = {
          type: "marquee",
          pointerId: event.pointerId,
          origin,
          additive: event.ctrlKey || event.metaKey,
        };
        this.selectionBox = { x: origin.x, y: origin.y, width: 0, height: 0 };
        this.capture(event.pointerId);
        return;
      }
      if (["door", "window"].includes(this.interactionMode)) return;
      if (this.interactionMode === "draw") {
        if (this.drawPoints.length || event.ctrlKey || event.metaKey) {
          this.addDrawPoint(event);
          if (!event.ctrlKey && !event.metaKey && this.drawPoints.length > 1) {
            this.finishDrawing();
          }
        } else {
          const origin = this.point(event);
          this.drag = { type: "create", pointerId: event.pointerId, origin };
          this.preview = {
            x1: origin.x,
            y1: origin.y,
            x2: origin.x,
            y2: origin.y,
          };
          this.capture(event.pointerId);
        }
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
      const nudgeSnapshot = { ...this.vertexNudgePreview };
      this.flushWallVertexNudge();
      this.interactionMode = "select";
      if (event.ctrlKey || event.metaKey) {
        this.toggleSelection(wall);
        return;
      }
      if (!this.selectedIds.map(Number).includes(Number(wall.id))) {
        this.selectedIds = [wall.id];
        this.$emit("select", wall.id);
      }
      const selected =
        this.selectedWalls.length > 1 ? this.selectedWalls : [wall];
      const group = selected.map((item) => ({
        ...item,
        ...(nudgeSnapshot[item.id] || {}),
      }));
      const movingWall =
        group.find((item) => Number(item.id) === Number(wall.id)) || wall;
      this.drag = {
        type: group.length > 1 ? "group" : "segment",
        pointerId: event.pointerId,
        wall: movingWall,
        walls: group,
        origin: wallPoint(event, this.$refs.surface, this.scene, false),
        snappedOrigin: wallPoint(event, this.$refs.surface, this.scene, true),
      };
      this.preview = {
        x1: movingWall.x1,
        y1: movingWall.y1,
        x2: movingWall.x2,
        y2: movingWall.y2,
      };
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
      const displayed = this.display(wall);
      const pending = this.flushWallVertexNudge();
      this.activeVertex = { wallId: wall.id, endpoint };
      if (!this.isSelectedWall(wall)) {
        this.selectedIds = [wall.id];
        this.$emit("select", wall.id);
      }
      const original =
        endpoint === "start"
          ? { x: displayed.x1, y: displayed.y1 }
          : { x: displayed.x2, y: displayed.y2 };
      this.drag = {
        type: "endpoint",
        pointerId: event.pointerId,
        wall,
        endpoint,
        original,
        startClient: { x: event.clientX, y: event.clientY },
        hasMoved: false,
        connected:
          pending &&
          Number(pending.wallId) === Number(wall.id) &&
          pending.endpoint === endpoint
            ? pending.connected
            : this.connectPoints
              ? connectedWallEndpoints(this.walls, original)
              : [],
      };
      this.preview = {
        x1: displayed.x1,
        y1: displayed.y1,
        x2: displayed.x2,
        y2: displayed.y2,
      };
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
      if (this.drag.type === "marquee") {
        const point = wallPoint(event, this.$refs.surface, this.scene, false);
        this.selectionBox = {
          x: Math.min(this.drag.origin.x, point.x),
          y: Math.min(this.drag.origin.y, point.y),
          width: Math.abs(point.x - this.drag.origin.x),
          height: Math.abs(point.y - this.drag.origin.y),
        };
        return;
      }
      if (this.drag.type === "create") {
        const point = this.point(event);
        this.preview = {
          x1: this.drag.origin.x,
          y1: this.drag.origin.y,
          x2: point.x,
          y2: point.y,
        };
        return;
      }
      if (this.drag.type === "endpoint") {
        if (!this.drag.hasMoved) {
          const distance = Math.hypot(
            Number(event.clientX) - Number(this.drag.startClient.x),
            Number(event.clientY) - Number(this.drag.startClient.y),
          );
          if (distance < 2) return;
          this.drag.hasMoved = true;
        }
        let point = this.point(event, this.drag.wall.id);
        if (event.altKey) {
          const dx = Math.abs(point.x - this.drag.original.x);
          const dy = Math.abs(point.y - this.drag.original.y);
          point =
            dx >= dy
              ? { x: point.x, y: this.drag.original.y }
              : { x: this.drag.original.x, y: point.y };
        }
        const prefix = this.drag.endpoint === "start" ? "1" : "2";
        this.preview = {
          ...this.preview,
          [`x${prefix}`]: point.x,
          [`y${prefix}`]: point.y,
        };
        this.groupPreview = Object.fromEntries(
          this.drag.connected.map(({ wall, endpoint }) => {
            const suffix = endpoint === "start" ? "1" : "2";
            return [
              wall.id,
              {
                [`x${suffix}`]: point.x,
                [`y${suffix}`]: point.y,
              },
            ];
          }),
        );
        return;
      }
      const snapped = this.snapToGrid && !event.shiftKey;
      const point = wallPoint(event, this.$refs.surface, this.scene, snapped);
      const wall = this.drag.wall;
      const origin = snapped ? this.drag.snappedOrigin : this.drag.origin;
      let dx = point.x - origin.x;
      let dy = point.y - origin.y;
      const movingWalls = this.drag.walls || [wall];
      const minimumX = Math.min(
        ...movingWalls.flatMap((item) => [item.x1, item.x2]),
      );
      const maximumX = Math.max(
        ...movingWalls.flatMap((item) => [item.x1, item.x2]),
      );
      const minimumY = Math.min(
        ...movingWalls.flatMap((item) => [item.y1, item.y2]),
      );
      const maximumY = Math.max(
        ...movingWalls.flatMap((item) => [item.y1, item.y2]),
      );
      dx = Math.max(-minimumX, dx);
      dx = Math.min(Number(this.scene.width) - maximumX, dx);
      dy = Math.max(-minimumY, dy);
      dy = Math.min(Number(this.scene.height) - maximumY, dy);
      if (this.drag.type === "group") {
        this.groupPreview = Object.fromEntries(
          movingWalls.map((item) => [
            item.id,
            {
              x1: Number(item.x1) + dx,
              y1: Number(item.y1) + dy,
              x2: Number(item.x2) + dx,
              y2: Number(item.y2) + dy,
            },
          ]),
        );
        return;
      }
      const connection = this.segmentConnection(wall, dx, dy, event.shiftKey);
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
      const selectionBox = this.selectionBox;
      const groupPreview = this.groupPreview;
      this.cancel();
      if (drag.type === "marquee") {
        this.finishMarquee(selectionBox, drag.additive);
        return;
      }
      if (drag.type === "create") {
        if (wallLength(preview) >= 2) {
          this.$emit("create", {
            ...wallPreset(this.activePreset),
            ...preview,
            name: `${this.$t("vtt.wall.defaultName")} ${this.walls.length + 1}`,
          });
        }
        return;
      }
      if (drag.type === "segment") {
        if (
          Object.keys(preview).some((key) => preview[key] !== drag.wall[key])
        ) {
          this.$emit("update", { wall: drag.wall, changes: preview });
        }
        return;
      }
      if (drag.type === "group") {
        this.$emit(
          "update-many",
          drag.walls.map((wall) => ({ wall, changes: groupPreview[wall.id] })),
        );
        return;
      }
      this.finishEndpoint(drag, preview);
    },
    setInteractionMode(mode) {
      this.flushWallVertexNudge();
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
          ...wallPreset(this.activePreset),
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
      if (
        Number(point.x) === Number(drag.original.x) &&
        Number(point.y) === Number(drag.original.y)
      ) {
        return;
      }
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
      this.groupPreview = {};
      this.selectionBox = null;
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
      if (this.groupPreview[wall.id]) {
        return { ...wall, ...this.groupPreview[wall.id] };
      }
      if (this.vertexNudgePreview[wall.id]) {
        return { ...wall, ...this.vertexNudgePreview[wall.id] };
      }
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
          "scene-wall--selected": this.selectedIds
            .map(Number)
            .includes(Number(wall.id)),
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
        this.snapToGrid && !event.shiftKey,
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
            ...(["door", "window"].includes(type)
              ? { doorState: "closed" }
              : {}),
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
      if (!["door", "secret", "window"].includes(wall.type)) return;
      this.$emit("update", {
        wall,
        changes: { doorState: wall.doorState === "open" ? "closed" : "open" },
      });
    },
    updateSelected(changes, callbacks = {}) {
      if (this.selectedWalls.length > 1) {
        this.$emit("update-many", {
          updates: this.selectedWalls.map((wall) => ({ wall, changes })),
          ...callbacks,
        });
      } else if (this.selectedWall)
        this.$emit("update", {
          wall: this.selectedWall,
          changes,
          ...callbacks,
        });
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
        ...wallPreset(this.activePreset),
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
      this.propertiesWindow.minimized = false;
      this.propertiesOpen = true;
      this.$nextTick(() => focusTableWindow(this.propertiesWindow.id));
      this.soundPreviewConfig = null;
      this.activeSoundRuleId = null;
    },
    openProperties() {
      if (!this.selectedWall) return;
      this.listOpen = false;
      this.propertiesWindow.minimized = false;
      this.propertiesOpen = true;
      this.$nextTick(() => focusTableWindow(this.propertiesWindow.id));
      this.soundPreviewConfig = null;
      this.activeSoundRuleId = null;
    },
    toggleManager() {
      this.listOpen = !this.listOpen;
      if (this.listOpen) this.closeProperties();
    },
    saveProperties(changes) {
      this.updateSelected(changes, {
        onSuccess: () => this.closeProperties(),
      });
    },
    closeProperties() {
      this.propertiesOpen = false;
      this.clearSoundPreview();
    },
    clearSoundPreview() {
      this.soundPreviewConfig = null;
      this.activeSoundRuleId = null;
      this.soundGeometryPatch = null;
    },
    applySoundGeometry(payload) {
      if (!payload?.ruleId || !payload.geometry || !this.soundPreviewConfig) {
        return;
      }
      this.soundGeometryPatch = {
        ...payload,
        nonce: Date.now(),
      };
      this.soundPreviewConfig = {
        ...this.soundPreviewConfig,
        rules: (this.soundPreviewConfig.rules || []).map((rule) =>
          rule.id === payload.ruleId
            ? { ...rule, geometry: { ...rule.geometry, ...payload.geometry } }
            : rule,
        ),
      };
    },
    keyboard(event) {
      if (this.activeVertex && this.wallArrowDelta(event)) {
        event.preventDefault();
        this.nudgeActiveWallVertex(event);
        return;
      }
      if (
        (event.ctrlKey || event.metaKey) &&
        event.key.toLowerCase() === "c" &&
        this.selectedWalls.length
      ) {
        event.preventDefault();
        this.clipboard = this.selectedWalls.map((wall) => ({ ...wall }));
      }
      if (
        (event.ctrlKey || event.metaKey) &&
        event.key.toLowerCase() === "v" &&
        this.clipboard.length
      ) {
        event.preventDefault();
        const offset = Math.max(4, Number(this.scene.gridSize) / 4 || 25);
        const drafts = this.clipboard.map((source) => {
          const draft = { ...source };
          delete draft.id;
          delete draft.revision;
          delete draft.capabilities;
          return {
            ...draft,
            name: `${draft.name} — copy`,
            x1: Math.min(this.scene.width, Number(draft.x1) + offset),
            y1: Math.min(this.scene.height, Number(draft.y1) + offset),
            x2: Math.min(this.scene.width, Number(draft.x2) + offset),
            y2: Math.min(this.scene.height, Number(draft.y2) + offset),
          };
        });
        this.$emit("create-many", drafts);
      }
      if (event.key === "Escape") {
        if (this.activeVertex && !this.drag) {
          this.flushWallVertexNudge();
          this.activeVertex = null;
        } else this.cancel();
      }
      if (event.key === "Enter" && this.drawPoints.length > 1) {
        event.preventDefault();
        this.finishDrawing();
      }
      if (
        ["Delete", "Backspace"].includes(event.key) &&
        this.selectedWalls.length
      ) {
        event.preventDefault();
        this.deleteSelected();
      }
    },
    toggleSelection(wall) {
      const id = Number(wall.id);
      const ids = this.selectedIds.map(Number);
      this.selectedIds = ids.includes(id)
        ? ids.filter((value) => value !== id)
        : [...ids, id];
      this.$emit("select", this.selectedIds.at(-1) || null);
    },
    wallArrowDelta(event) {
      const step = event.shiftKey
        ? 1
        : Math.max(1, Number(this.scene.gridSize || 100) / 10);
      return {
        ArrowLeft: { x: -step, y: 0 },
        ArrowRight: { x: step, y: 0 },
        ArrowUp: { x: 0, y: -step },
        ArrowDown: { x: 0, y: step },
      }[event.key];
    },
    nudgeActiveWallVertex(event) {
      if (this.busy) return;
      const active = this.activeVertex;
      const wall = this.walls.find(
        (item) => Number(item.id) === Number(active?.wallId),
      );
      const delta = this.wallArrowDelta(event);
      if (!wall || !delta) return;
      const matchingContext =
        this.vertexNudgeContext &&
        Number(this.vertexNudgeContext.wallId) === Number(wall.id) &&
        this.vertexNudgeContext.endpoint === active.endpoint;
      if (!matchingContext) {
        this.flushWallVertexNudge();
        const original =
          active.endpoint === "start"
            ? { x: Number(wall.x1), y: Number(wall.y1) }
            : { x: Number(wall.x2), y: Number(wall.y2) };
        this.vertexNudgeContext = {
          wallId: wall.id,
          endpoint: active.endpoint,
          point: original,
          connected: this.connectPoints
            ? connectedWallEndpoints(this.walls, original)
            : [{ wall, endpoint: active.endpoint }],
        };
      }
      const original = this.vertexNudgeContext.point;
      const point = {
        x: Math.max(
          0,
          Math.min(Number(this.scene.width), original.x + delta.x),
        ),
        y: Math.max(
          0,
          Math.min(Number(this.scene.height), original.y + delta.y),
        ),
      };
      this.vertexNudgeContext.point = point;
      this.vertexNudgePreview = Object.fromEntries(
        this.vertexNudgeContext.connected.map(({ wall: item, endpoint }) => {
          const suffix = endpoint === "start" ? "1" : "2";
          return [
            item.id,
            { [`x${suffix}`]: point.x, [`y${suffix}`]: point.y },
          ];
        }),
      );
      window.clearTimeout(this.vertexNudgeTimer);
      this.vertexNudgeTimer = window.setTimeout(this.flushWallVertexNudge, 140);
    },
    flushWallVertexNudge() {
      window.clearTimeout(this.vertexNudgeTimer);
      this.vertexNudgeTimer = null;
      const context = this.vertexNudgeContext;
      this.vertexNudgeContext = null;
      this.vertexNudgePreview = {};
      if (!context) return null;
      const point = context.point;
      this.$emit(
        "update-many",
        context.connected.map(({ wall: item, endpoint }) => {
          const suffix = endpoint === "start" ? "1" : "2";
          return {
            wall: item,
            changes: { [`x${suffix}`]: point.x, [`y${suffix}`]: point.y },
          };
        }),
      );
      return context;
    },
    finishMarquee(box, additive) {
      if (!box) return;
      const selected = this.walls
        .filter((wall) => {
          const xs = [Number(wall.x1), Number(wall.x2)];
          const ys = [Number(wall.y1), Number(wall.y2)];
          return (
            Math.max(...xs) >= box.x &&
            Math.min(...xs) <= box.x + box.width &&
            Math.max(...ys) >= box.y &&
            Math.min(...ys) <= box.y + box.height
          );
        })
        .map((wall) => wall.id);
      this.selectedIds = additive
        ? [...new Set([...this.selectedIds, ...selected])]
        : selected;
      this.$emit("select", this.selectedIds.at(-1) || null);
    },
    deleteSelected() {
      if (!this.selectedWalls.length) return;
      if (!window.confirm(this.$t("vtt.wall.deleteSelectedConfirm"))) return;
      this.selectedWalls.forEach((wall) =>
        this.$emit("delete", { wall, confirmed: true }),
      );
      this.selectedIds = [];
      this.$emit("select", null);
    },
    createRegionFromSelection() {
      const tolerance = Math.max(
        0.01,
        Number(this.scene.gridSize || 100) * 0.02,
      );
      const result = wallsToPolygons(this.selectedWalls, tolerance);
      this.regionGap = result.gap;
      if (!result.closed) return;
      this.$emit("create-region", {
        name: `${this.$t("vtt.region.defaultName")} ${Date.now()}`,
        polygons: result.polygons,
        darknessMode: "override",
        darknessValue: 0,
        disableGlobalIllumination: false,
      });
    },
    movePropertiesWindow({ x, y }) {
      this.propertiesWindow.x = x;
      this.propertiesWindow.y = y;
    },
    resizePropertiesWindow({ width, height }) {
      this.propertiesWindow.width = width;
      this.propertiesWindow.height = height;
    },
    closeAllDoors() {
      this.$emit(
        "update-many",
        this.walls
          .filter(
            (wall) =>
              ["door", "secret", "window"].includes(
                wall.doorType || wall.type,
              ) && wall.doorState === "open",
          )
          .map((wall) => ({ wall, changes: { doorState: "closed" } })),
      );
    },
    deleteAllWalls() {
      if (!window.confirm(this.$t("vtt.wall.deleteAllConfirm"))) return;
      this.walls.forEach((wall) =>
        this.$emit("delete", { wall, confirmed: true }),
      );
    },
  },
};
</script>

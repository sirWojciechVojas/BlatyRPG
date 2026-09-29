<template>
  <div v-if="canManage && active" class="scene-region-layer" @pointerdown.stop>
    <svg
      ref="surface"
      :viewBox="`0 0 ${scene.width} ${scene.height}`"
      tabindex="0"
      @pointerdown="addPoint"
      @pointermove="previewPoint"
      @dblclick.prevent="finishPolygon"
      @keydown="keyboard"
    >
      <rect class="scene-region-layer__surface" width="100%" height="100%" />
      <g v-for="region in regions" :key="region.id">
        <polygon
          v-for="(polygon, index) in displayRegion(region).polygons"
          :key="`${region.id}-${index}`"
          class="scene-region"
          :class="{ 'scene-region--selected': region.id === selectedId }"
          :points="pointsAttribute(polygon)"
          :style="{ '--region-color': region.color }"
          @pointerdown.stop="select(region)"
          @dblclick.stop="edit(region)"
        />
        <template v-if="region.id === selectedId">
          <template
            v-for="(polygon, polygonIndex) in displayRegion(region).polygons"
            :key="`polygon-handles-${region.id}-${polygonIndex}`"
          >
            <g
              v-for="edge in edgeHandles(polygon)"
              :key="`edge-${polygonIndex}-${edge.edgeIndex}`"
              class="scene-region__edge-handle"
              role="button"
              :aria-label="$t('vtt.region.edgeInsertHint')"
              @pointerdown.stop.prevent="
                startEdgePointMove(
                  $event,
                  region,
                  polygonIndex,
                  edge.edgeIndex,
                  edge,
                )
              "
            >
              <circle
                class="scene-region__edge-hit"
                :cx="edge.x"
                :cy="edge.y"
                :r="screenSize(13)"
              />
              <circle
                class="scene-region__edge-control"
                :cx="edge.x"
                :cy="edge.y"
                :r="screenSize(5)"
              />
              <text
                :x="edge.x"
                :y="edge.y"
                :style="{ fontSize: `${screenSize(10)}px` }"
              >
                +
              </text>
              <title>{{ $t("vtt.region.edgeInsertHint") }}</title>
            </g>
            <g
              v-for="(point, pointIndex) in polygon"
              :key="`handle-${polygonIndex}-${pointIndex}`"
              class="scene-region__vertex"
              :class="{
                'scene-region__vertex--active': isActiveVertex(
                  region,
                  polygonIndex,
                  pointIndex,
                ),
              }"
              role="button"
              :aria-label="vertexLabel(point, pointIndex)"
              @pointerdown.stop.prevent="
                startPointMove($event, region, polygonIndex, pointIndex)
              "
              @dblclick.stop.prevent="
                removeVertex(region, polygonIndex, pointIndex)
              "
              @click.alt.stop.prevent="
                removeVertex(region, polygonIndex, pointIndex)
              "
            >
              <circle
                class="scene-region__vertex-hit"
                :cx="point.x"
                :cy="point.y"
                :r="screenSize(15)"
              />
              <circle
                class="scene-region__handle"
                :cx="point.x"
                :cy="point.y"
                :r="screenSize(7)"
              />
              <circle
                class="scene-region__handle-core"
                :cx="point.x"
                :cy="point.y"
                :r="screenSize(2.5)"
              />
              <title>{{ $t("vtt.region.vertexHint") }}</title>
              <g
                v-if="isActiveVertex(region, polygonIndex, pointIndex)"
                class="scene-region__vertex-readout"
                :transform="`translate(${point.x + screenSize(12)} ${
                  point.y - screenSize(23)
                })`"
              >
                <rect
                  :width="screenSize(126)"
                  :height="screenSize(21)"
                  :rx="screenSize(4)"
                />
                <text
                  :x="screenSize(7)"
                  :y="screenSize(14)"
                  :style="{ fontSize: `${screenSize(10)}px` }"
                >
                  {{ vertexLabel(point, pointIndex) }}
                </text>
              </g>
            </g>
          </template>
        </template>
      </g>
      <polygon
        v-if="draftPoints.length"
        class="scene-region scene-region--draft"
        :points="pointsAttribute(previewPoints)"
      />
    </svg>
    <Teleport to="body">
      <nav class="scene-region-palette" :aria-label="$t('vtt.region.toolbar')">
        <SceneToolIconButton
          icon="cursor"
          :label="$t('vtt.region.select')"
          :description="$t('vtt.region.tooltips.select')"
          shortcut="↑ ↓ ← → · Shift · Alt"
          :active="mode === 'select'"
          toggle
          @click="mode = 'select'"
        />
        <SceneToolIconButton
          icon="region"
          :label="$t('vtt.region.draw')"
          :description="$t('vtt.region.tooltips.draw')"
          shortcut="Shift"
          :active="mode === 'draw'"
          toggle
          @click="mode = 'draw'"
        />
        <SceneToolIconButton
          icon="check"
          :label="$t('vtt.region.finish')"
          :description="$t('vtt.region.tooltips.finish')"
          shortcut="Enter"
          :disabled="draftPoints.length < 3"
          @click="finishPolygon"
        />
        <SceneToolIconButton
          icon="settings"
          :label="$t('vtt.region.edit')"
          :description="$t('vtt.region.tooltips.edit')"
          :disabled="!selectedRegion"
          @click="openProperties"
        />
        <SceneToolIconButton
          icon="trash"
          :label="$t('vtt.region.delete')"
          :description="$t('vtt.region.tooltips.delete')"
          shortcut="Delete"
          :disabled="!selectedRegion"
          danger
          @click="$emit('delete', selectedRegion)"
        />
      </nav>
    </Teleport>
    <Teleport v-if="propertiesOpen && selectedRegion" to="body">
      <TableFloatingWindow
        :model="windowModel"
        :title="$t('vtt.region.properties')"
        :subtitle="selectedRegion.name"
        icon="region"
        @move="moveWindow"
        @resize="resizeWindow"
        @layer-change="windowModel.z = $event.z"
        @minimize="windowModel.minimized = !windowModel.minimized"
        @close="propertiesOpen = false"
      >
        <RegionPropertiesPanel
          :region="selectedRegion"
          :busy="busy"
          @save="saveProperties"
          @close="propertiesOpen = false"
        />
      </TableFloatingWindow>
    </Teleport>
  </div>
</template>

<script>
import TableFloatingWindow from "@/components/vtt/table/TableFloatingWindow.vue";
import { focusTableWindow } from "@/components/vtt/table/tableWindowLayers";
import SceneToolIconButton from "@/components/vtt/table/SceneToolIconButton.vue";
import RegionPropertiesPanel from "./RegionPropertiesPanel.vue";
import { wallPoint } from "@/lib/vtt/wallGeometry";
import {
  cloneRegionPolygons,
  insertRegionVertex,
  regionEdgeHandles,
  removeRegionVertex,
  replaceRegionVertex,
} from "@/lib/vtt/regionVertexGeometry";

export default {
  name: "SceneRegionLayer",
  components: {
    RegionPropertiesPanel,
    SceneToolIconButton,
    TableFloatingWindow,
  },
  props: {
    scene: { type: Object, required: true },
    regions: { type: Array, default: () => [] },
    activeTool: { type: String, default: "select" },
    selectedId: { type: [Number, String], default: null },
    canManage: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    scale: { type: Number, default: 1 },
  },
  emits: ["select", "create", "update", "delete"],
  data: () => ({
    mode: "draw",
    draftPoints: [],
    hoverPoint: null,
    drag: null,
    activeVertex: null,
    nudgePreview: null,
    nudgeTimer: null,
    propertiesOpen: false,
    windowModel: {
      id: "region-properties",
      windowType: "region-properties",
      x: 180,
      y: 120,
      width: 360,
      height: 430,
      minWidth: 300,
      minHeight: 320,
      z: 900,
      resizable: true,
      maximizable: false,
      minimized: false,
      constrainToViewport: true,
    },
  }),
  computed: {
    active() {
      return this.activeTool === "regions";
    },
    selectedRegion() {
      return (
        this.regions.find((region) => region.id === this.selectedId) || null
      );
    },
    previewPoints() {
      return this.hoverPoint
        ? [...this.draftPoints, this.hoverPoint]
        : this.draftPoints;
    },
  },
  beforeUnmount() {
    this.cancelPointMove();
    this.flushVertexNudge();
  },
  watch: {
    selectedId(value, previous) {
      if (String(value) !== String(previous)) {
        this.flushVertexNudge();
        this.activeVertex = null;
      }
    },
  },
  methods: {
    screenSize(value) {
      return Number(value) / Math.max(0.1, Number(this.scale) || 1);
    },
    point(event) {
      return wallPoint(event, this.$refs.surface, this.scene, !event.shiftKey);
    },
    edgeHandles: regionEdgeHandles,
    pointsAttribute(points) {
      return (points || []).map((point) => `${point.x},${point.y}`).join(" ");
    },
    addPoint(event) {
      if (this.busy || event.button !== 0) return;
      if (this.mode === "select") {
        this.$emit("select", null);
        return;
      }
      if (!event.target.classList.contains("scene-region-layer__surface"))
        return;
      this.draftPoints.push(this.point(event));
    },
    previewPoint(event) {
      if (this.drag) return this.movePoint(event);
      if (this.mode === "draw" && this.draftPoints.length) {
        this.hoverPoint = this.point(event);
      }
    },
    finishPolygon() {
      if (this.draftPoints.length < 3) return;
      this.$emit("create", {
        name: `${this.$t("vtt.region.defaultName")} ${this.regions.length + 1}`,
        polygons: [this.draftPoints.map((point) => ({ ...point }))],
        darknessMode: "override",
        darknessValue: 0,
        disableGlobalIllumination: false,
      });
      this.draftPoints = [];
      this.hoverPoint = null;
      this.mode = "select";
    },
    keyboard(event) {
      if (event.key === "Escape") {
        if (this.activeVertex) {
          this.flushVertexNudge();
          this.activeVertex = null;
          return;
        }
        this.draftPoints = [];
        this.hoverPoint = null;
      } else if (event.key === "Enter") this.finishPolygon();
      else if (this.activeVertex && this.arrowDelta(event)) {
        event.preventDefault();
        this.nudgeActiveVertex(event);
      } else if (
        (event.key === "Delete" || event.key === "Backspace") &&
        this.activeVertex
      ) {
        event.preventDefault();
        const region = this.regions.find(
          (item) => String(item.id) === String(this.activeVertex.regionId),
        );
        if (region) {
          this.removeVertex(
            region,
            this.activeVertex.polygonIndex,
            this.activeVertex.pointIndex,
          );
        }
      } else if (
        (event.key === "Delete" || event.key === "Backspace") &&
        this.selectedRegion
      ) {
        event.preventDefault();
        this.$emit("delete", this.selectedRegion);
      }
    },
    select(region) {
      this.mode = "select";
      if (String(region.id) !== String(this.selectedId)) {
        this.activeVertex = null;
      }
      this.$emit("select", region.id);
    },
    edit(region) {
      this.select(region);
      this.openProperties(region);
    },
    openProperties(region = null) {
      const requestedRegion = region?.id ? region : this.selectedRegion;
      if (!requestedRegion) return;
      this.windowModel.minimized = false;
      this.propertiesOpen = true;
      this.$nextTick(() => focusTableWindow(this.windowModel.id));
    },
    startPointMove(event, region, polygonIndex, pointIndex) {
      if (this.busy || event.button !== 0) return;
      this.select(region);
      this.activeVertex = { regionId: region.id, polygonIndex, pointIndex };
      this.beginPointMove(
        event,
        region,
        polygonIndex,
        pointIndex,
        false,
        this.consumeVertexNudge(region),
      );
    },
    startEdgePointMove(event, region, polygonIndex, edgeIndex, midpoint) {
      if (this.busy || event.button !== 0) return;
      this.select(region);
      const pointIndex = edgeIndex + 1;
      const polygons = insertRegionVertex(
        this.consumeVertexNudge(region),
        polygonIndex,
        edgeIndex,
        midpoint,
      );
      this.activeVertex = { regionId: region.id, polygonIndex, pointIndex };
      this.beginPointMove(
        event,
        region,
        polygonIndex,
        pointIndex,
        true,
        polygons,
      );
    },
    beginPointMove(
      event,
      region,
      polygonIndex,
      pointIndex,
      inserted,
      polygons = cloneRegionPolygons(region.polygons),
    ) {
      const original = polygons[polygonIndex]?.[pointIndex];
      if (!original) return;
      this.drag = {
        pointerId: event.pointerId,
        region,
        polygonIndex,
        pointIndex,
        polygons,
        original: { ...original },
        startClient: { x: event.clientX, y: event.clientY },
        hasMoved: false,
        inserted,
        changed: inserted,
      };
      this.$refs.surface?.focus?.();
      window.addEventListener("pointermove", this.movePoint);
      window.addEventListener("pointerup", this.stopPointMove, { once: true });
      window.addEventListener("pointercancel", this.cancelPointMove, {
        once: true,
      });
    },
    movePoint(event) {
      if (!this.drag || event.pointerId !== this.drag.pointerId) return;
      if (!this.drag.hasMoved) {
        const distance = Math.hypot(
          Number(event.clientX) - Number(this.drag.startClient.x),
          Number(event.clientY) - Number(this.drag.startClient.y),
        );
        if (distance < 2) return;
        this.drag.hasMoved = true;
      }
      let point = this.point(event);
      if (event.altKey) {
        const dx = Math.abs(point.x - this.drag.original.x);
        const dy = Math.abs(point.y - this.drag.original.y);
        point =
          dx >= dy
            ? { x: point.x, y: this.drag.original.y }
            : { x: this.drag.original.x, y: point.y };
      }
      this.drag.polygons = replaceRegionVertex(
        this.drag.polygons,
        this.drag.polygonIndex,
        this.drag.pointIndex,
        point,
      );
      this.drag.changed =
        this.drag.inserted ||
        point.x !== this.drag.original.x ||
        point.y !== this.drag.original.y;
    },
    stopPointMove(event) {
      if (!this.drag) return;
      if (event) this.movePoint(event);
      const drag = this.drag;
      this.drag = null;
      window.removeEventListener("pointermove", this.movePoint);
      window.removeEventListener("pointercancel", this.cancelPointMove);
      if (drag.changed) this.commitPolygons(drag.region, drag.polygons);
    },
    cancelPointMove() {
      if (!this.drag) return;
      this.drag = null;
      window.removeEventListener("pointermove", this.movePoint);
      window.removeEventListener("pointerup", this.stopPointMove);
      window.removeEventListener("pointercancel", this.cancelPointMove);
    },
    removeVertex(region, polygonIndex, pointIndex) {
      if (this.busy) return;
      const source = this.consumeVertexNudge(region);
      const polygon = source?.[polygonIndex];
      if (!Array.isArray(polygon) || polygon.length <= 3) return;
      const polygons = removeRegionVertex(source, polygonIndex, pointIndex);
      this.activeVertex = null;
      this.commitPolygons(region, polygons);
    },
    commitPolygons(region, polygons) {
      this.$emit("update", { region, changes: { polygons } });
    },
    arrowDelta(event) {
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
    nudgeActiveVertex(event) {
      const active = this.activeVertex;
      const region = this.regions.find(
        (item) => String(item.id) === String(active?.regionId),
      );
      const delta = this.arrowDelta(event);
      if (!region || !delta) return;
      const polygons =
        this.nudgePreview &&
        String(this.nudgePreview.region.id) === String(region.id)
          ? this.nudgePreview.polygons
          : cloneRegionPolygons(region.polygons);
      const point = polygons?.[active.polygonIndex]?.[active.pointIndex];
      if (!point) return;
      const next = {
        x: Math.max(0, Math.min(Number(this.scene.width), point.x + delta.x)),
        y: Math.max(0, Math.min(Number(this.scene.height), point.y + delta.y)),
      };
      this.nudgePreview = {
        region,
        polygons: replaceRegionVertex(
          polygons,
          active.polygonIndex,
          active.pointIndex,
          next,
        ),
      };
      window.clearTimeout(this.nudgeTimer);
      this.nudgeTimer = window.setTimeout(this.flushVertexNudge, 140);
    },
    flushVertexNudge() {
      window.clearTimeout(this.nudgeTimer);
      this.nudgeTimer = null;
      const preview = this.nudgePreview;
      this.nudgePreview = null;
      if (preview) this.commitPolygons(preview.region, preview.polygons);
    },
    consumeVertexNudge(region) {
      window.clearTimeout(this.nudgeTimer);
      this.nudgeTimer = null;
      const preview = this.nudgePreview;
      this.nudgePreview = null;
      if (!preview) return cloneRegionPolygons(region.polygons);
      if (String(preview.region.id) === String(region.id)) {
        return cloneRegionPolygons(preview.polygons);
      }
      this.commitPolygons(preview.region, preview.polygons);
      return cloneRegionPolygons(region.polygons);
    },
    isActiveVertex(region, polygonIndex, pointIndex) {
      return Boolean(
        this.activeVertex &&
        String(this.activeVertex.regionId) === String(region.id) &&
        this.activeVertex.polygonIndex === polygonIndex &&
        this.activeVertex.pointIndex === pointIndex,
      );
    },
    vertexLabel(point, pointIndex) {
      return `#${pointIndex + 1}  ${Math.round(point.x)}, ${Math.round(point.y)}`;
    },
    saveProperties(changes) {
      this.$emit("update", { region: this.selectedRegion, changes });
      this.propertiesOpen = false;
    },
    moveWindow({ x, y }) {
      this.windowModel.x = x;
      this.windowModel.y = y;
    },
    resizeWindow({ width, height }) {
      this.windowModel.width = width;
      this.windowModel.height = height;
    },
    displayRegion(region) {
      if (this.drag?.region?.id === region.id) {
        return { ...region, polygons: this.drag.polygons };
      }
      return this.nudgePreview?.region?.id === region.id
        ? { ...region, polygons: this.nudgePreview.polygons }
        : region;
    },
  },
};
</script>

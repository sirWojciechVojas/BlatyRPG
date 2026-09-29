<template>
  <div
    class="scene-fog-layer"
    :class="{
      'scene-fog-layer--editing': editing,
      'scene-fog-layer--brush': editing && tool.includes('brush'),
    }"
  >
    <canvas
      ref="fog"
      class="scene-fog-layer__mask"
      :style="fogCanvasStyle"
      aria-hidden="true"
    />
    <canvas
      v-if="editing"
      ref="editor"
      class="scene-fog-layer__editor"
      :width="editorSize.width"
      :height="editorSize.height"
      @pointerdown.stop="pointerDown"
      @pointermove.stop="pointerMove"
      @pointerleave.stop="pointerLeave"
      @pointerup.stop="pointerUp"
      @pointercancel.stop="pointerCancel"
      @lostpointercapture.stop="pointerCancel"
      @dblclick.stop.prevent="finishPolygon"
    />
    <Teleport to="body">
      <FogToolToolbar
        v-if="editing"
        :model-value="tool"
        :members="members"
        :tokens="tokens"
        :preview="preview"
        :busy="busy"
        :brush-size="resolvedBrushSize"
        :brush-hardness="brushHardness"
        :brush-min="grid.cellSize"
        :brush-max="brushMaximum"
        :brush-step="Math.max(1, Math.round(grid.cellSize / 2))"
        @update:model-value="tool = $event"
        @preview-change="$emit('preview-change', $event)"
        @command="command"
        @brush-change="setBrush"
      />
    </Teleport>
  </div>
</template>

<script>
import FogToolToolbar from "./FogToolToolbar.vue";
import {
  cellAtPoint,
  fogGrid,
  markBrush,
  markPolygon,
  maskFromRanges,
  newMaskRanges,
  rangesFromMask,
} from "@/lib/vtt/fogGrid";
import { computeFogVisibility } from "@/lib/vtt/fogVisibility";
import {
  fogBackingMetrics,
  fogViewportRect,
  normalizeFogFeather,
  normalizeFogMask,
} from "@/lib/vtt/fogRenderMetrics";

const scopeAllows = (scope, userId) => {
  if (scope?.mode === "everyone") return true;
  return (
    scope?.mode === "users" &&
    (scope.userIds || []).map(Number).includes(Number(userId))
  );
};

const rangeBatches = (ranges, maximumCells = 40000) => {
  const batches = [];
  let batch = [];
  let count = 0;
  ranges.forEach(([rangeStart, rangeEnd]) => {
    let start = rangeStart;
    while (start <= rangeEnd) {
      const length = Math.min(maximumCells - count, rangeEnd - start + 1);
      batch.push([start, start + length - 1]);
      count += length;
      start += length;
      if (count === maximumCells) {
        batches.push(batch);
        batch = [];
        count = 0;
      }
    }
  });
  if (batch.length) batches.push(batch);
  return batches;
};

export default {
  name: "SceneFogLayer",
  components: { FogToolToolbar },
  props: {
    scene: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    walls: { type: Array, default: () => [] },
    lights: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
    fogState: { type: Object, default: null },
    activeTool: { type: String, default: "select" },
    canManage: { type: Boolean, default: false },
    preview: { type: Object, default: () => ({ mode: "gm", id: null }) },
    busy: { type: Boolean, default: false },
    camera: {
      type: Object,
      default: () => ({ x: 0, y: 0, scale: 1 }),
    },
    viewportSize: {
      type: Object,
      default: () => ({ width: 0, height: 0 }),
    },
    scenePadding: { type: Number, default: 0 },
  },
  emits: ["patch", "visibility-change", "preview-change"],
  data: () => ({
    tool: "reveal-rect",
    frame: 0,
    saveTimer: 0,
    drag: null,
    polygon: [],
    pendingExplore: null,
    visibleMask: null,
    exploredMask: null,
    hiddenMask: null,
    brushSize: null,
    brushHardness: 85,
    brushCursor: null,
    visibilityGeometry: null,
    renderFrame: 0,
    renderBuffers: null,
    hasExploredCells: false,
    hasHiddenCells: false,
    fogImage: null,
    fogImageUrl: "",
  }),
  computed: {
    grid() {
      return fogGrid(this.scene, this.fogState?.cellSize || 64);
    },
    editorScale() {
      return Math.min(
        1,
        4096 / Math.max(1, Number(this.scene.width), Number(this.scene.height)),
      );
    },
    editorSize() {
      return {
        width: Math.max(1, Math.ceil(this.scene.width * this.editorScale)),
        height: Math.max(1, Math.ceil(this.scene.height * this.editorScale)),
      };
    },
    editing() {
      return this.canManage && this.activeTool === "fog";
    },
    resolvedBrushSize() {
      return Math.max(
        this.grid.cellSize,
        Number(this.brushSize) || Number(this.scene.gridSize) || 100,
      );
    },
    brushMaximum() {
      return Math.max(
        this.grid.cellSize,
        Math.min(
          4000,
          Math.max(Number(this.scene.width), Number(this.scene.height)) / 2,
        ),
      );
    },
    previewing() {
      return this.preview?.mode !== "gm";
    },
    applies() {
      return (
        this.scene.fogEnabled === true &&
        (!this.canManage || this.previewing || this.editing)
      );
    },
    visionTokens() {
      if (this.preview?.mode === "token") {
        const token = this.tokens.find(
          (item) => Number(item.id) === Number(this.preview.id),
        );
        return token
          ? [
              {
                ...token,
                capabilities: { ...token.capabilities, canControl: true },
              },
            ]
          : [];
      }
      if (this.preview?.mode === "user") {
        const authoritativeIds =
          Number(this.fogState?.userId) === Number(this.preview.id) &&
          Array.isArray(this.fogState?.visionTokenIds)
            ? new Set(this.fogState.visionTokenIds.map(Number))
            : null;
        return this.tokens
          .filter((token) => {
            if (authoritativeIds) return authoritativeIds.has(Number(token.id));
            if (scopeAllows(token.controlledBy, this.preview.id)) return true;
            if (token.controlledBy?.mode !== "inherit") return false;
            const actor = this.characters.find(
              (character) => Number(character.id) === Number(token.characterId),
            );
            return Number(actor?.ownerUserId) === Number(this.preview.id);
          })
          .map((token) => ({
            ...token,
            capabilities: { ...token.capabilities, canControl: true },
          }));
      }
      return this.canManage
        ? []
        : this.tokens.filter((token) => token.capabilities?.canControl);
    },
    feather() {
      return normalizeFogFeather(this.scene.fogEdgeSoftness);
    },
    fogViewport() {
      return fogViewportRect({
        scene: this.scene,
        camera: this.camera,
        viewport: this.viewportSize,
        padding: this.scenePadding,
        feather: this.feather,
      });
    },
    fogCanvasStyle() {
      const rect = this.fogViewport;
      return {
        left: `${rect.x}px`,
        top: `${rect.y}px`,
        width: `${rect.width}px`,
        height: `${rect.height}px`,
      };
    },
  },
  watch: {
    scene: { deep: true, handler: "schedule" },
    "scene.fogExplorationImage": "loadFogImage",
    tokens: { deep: true, handler: "schedule" },
    walls: { deep: true, handler: "schedule" },
    lights: { deep: true, handler: "schedule" },
    regions: { deep: true, handler: "schedule" },
    fogState: { deep: true, handler: "schedule" },
    preview: { deep: true, handler: "resetEditor" },
    activeTool: "resetEditor",
    tool: "resetEditor",
    camera: { deep: true, handler: "scheduleRender" },
    viewportSize: { deep: true, handler: "scheduleRender" },
  },
  mounted() {
    this.renderBuffers = {};
    window.addEventListener("resize", this.scheduleRender);
    this.loadFogImage();
    this.schedule();
  },
  beforeUnmount() {
    cancelAnimationFrame(this.frame);
    cancelAnimationFrame(this.renderFrame);
    window.clearTimeout(this.saveTimer);
    window.removeEventListener("resize", this.scheduleRender);
  },
  methods: {
    loadFogImage() {
      const url = String(this.scene.fogExplorationImage || "").trim();
      this.fogImageUrl = url;
      this.fogImage = null;
      if (!url) {
        this.scheduleRender();
        return;
      }
      const image = new Image();
      image.decoding = "async";
      image.onload = () => {
        if (this.fogImageUrl !== url) return;
        this.fogImage = image;
        this.scheduleRender();
      };
      image.onerror = () => {
        if (this.fogImageUrl === url) this.fogImage = null;
      };
      image.src = url;
    },
    schedule() {
      cancelAnimationFrame(this.frame);
      this.frame = requestAnimationFrame(() => this.recompute());
    },
    scheduleRender() {
      cancelAnimationFrame(this.renderFrame);
      this.renderFrame = requestAnimationFrame(() => this.render());
    },
    recompute() {
      const grid = this.grid;
      this.exploredMask = maskFromRanges(
        grid.length,
        this.fogState?.exploredRanges || [],
      );
      this.hasExploredCells = this.exploredMask.some((value) => value > 0);
      this.hiddenMask = maskFromRanges(
        grid.length,
        this.fogState?.forcedHiddenRanges || [],
      );
      this.hasHiddenCells = this.hiddenMask.some((value) => value > 0);
      this.visibilityGeometry = null;
      if (!this.applies) this.visibleMask = new Uint8Array(grid.length).fill(1);
      else if (this.scene.dynamicVision === false)
        this.visibleMask = this.exploredMask.slice();
      else {
        const visibility = computeFogVisibility({
          scene: this.scene,
          tokens: this.visionTokens,
          walls: this.walls,
          lights: this.lights,
          regions: this.regions,
          grid,
        });
        this.visibleMask = visibility.visible;
        this.visibilityGeometry = visibility.rasterized ? null : visibility;
      }
      for (let index = 0; index < grid.length; index += 1)
        if (this.hiddenMask[index]) this.visibleMask[index] = 0;
      this.queueExploration();
      this.render();
      this.publishVisibility();
    },
    queueExploration() {
      if (
        !this.applies ||
        this.scene.explorationMemory === false ||
        this.scene.fogExplorationMode === "none" ||
        !this.visionTokens.length ||
        (this.canManage && this.preview?.mode !== "user")
      )
        return;
      const ranges = newMaskRanges(
        this.visibleMask,
        this.exploredMask,
        this.hiddenMask,
      );
      if (!ranges.length) return;
      if (
        !this.pendingExplore ||
        this.pendingExplore.length !== this.grid.length
      )
        this.pendingExplore = new Uint8Array(this.grid.length);
      ranges.forEach(([start, end]) =>
        this.pendingExplore.fill(1, start, end + 1),
      );
      window.clearTimeout(this.saveTimer);
      this.saveTimer = window.setTimeout(() => {
        const pending = rangesFromMask(this.pendingExplore);
        this.pendingExplore.fill(0);
        rangeBatches(pending).forEach((ranges) =>
          this.$emit("patch", {
            mode: "explore",
            cellSize: this.grid.cellSize,
            ranges,
          }),
        );
      }, 700);
    },
    buffer(name, width, height) {
      if (!this.renderBuffers) this.renderBuffers = {};
      if (!this.renderBuffers[name])
        this.renderBuffers[name] = document.createElement("canvas");
      const canvas = this.renderBuffers[name];
      if (canvas.width !== width) canvas.width = width;
      if (canvas.height !== height) canvas.height = height;
      return canvas;
    },
    clearBuffer(canvas) {
      const context = canvas.getContext("2d", { alpha: true });
      context.setTransform(1, 0, 0, 1, 0, 0);
      context.globalAlpha = 1;
      context.globalCompositeOperation = "source-over";
      context.filter = "none";
      context.clearRect(0, 0, canvas.width, canvas.height);
      return context;
    },
    tracePolygons(context, polygons, metrics, rect) {
      context.setTransform(
        metrics.scale,
        0,
        0,
        metrics.scale,
        -rect.x * metrics.scale,
        -rect.y * metrics.scale,
      );
      context.beginPath();
      polygons.forEach((polygon) => {
        if (!polygon?.length) return;
        context.moveTo(polygon[0].x, polygon[0].y);
        for (let index = 1; index < polygon.length; index += 1)
          context.lineTo(polygon[index].x, polygon[index].y);
        context.closePath();
      });
      context.fillStyle = "#fff";
      context.fill();
      context.setTransform(1, 0, 0, 1, 0, 0);
    },
    logicalMask(name, mask) {
      const canvas = this.buffer(name, this.grid.columns, this.grid.rows);
      const sourceMask = normalizeFogMask(mask, this.grid.length);
      if (canvas.fogSourceMask === sourceMask) return canvas;
      const context = this.clearBuffer(canvas);
      const image = context.createImageData(canvas.width, canvas.height);
      for (let index = 0; index < (sourceMask?.length || 0); index += 1) {
        if (!sourceMask[index]) continue;
        const offset = index * 4;
        image.data[offset] = 255;
        image.data[offset + 1] = 255;
        image.data[offset + 2] = 255;
        image.data[offset + 3] = 255;
      }
      context.putImageData(image, 0, 0);
      canvas.fogSourceMask = sourceMask;
      return canvas;
    },
    drawLogicalMask(context, source, metrics, rect, operation = "source-over") {
      context.save();
      context.setTransform(1, 0, 0, 1, 0, 0);
      context.globalCompositeOperation = operation;
      context.imageSmoothingEnabled = true;
      context.imageSmoothingQuality = "high";
      context.drawImage(
        source,
        -rect.x * metrics.scale,
        -rect.y * metrics.scale,
        this.grid.columns * this.grid.cellSize * metrics.scale,
        this.grid.rows * this.grid.cellSize * metrics.scale,
      );
      context.restore();
    },
    renderVisibilityMask(target, metrics, rect) {
      const context = this.clearBuffer(target);
      if (!this.visibilityGeometry) {
        this.drawLogicalMask(
          context,
          this.logicalMask("logical-visible", this.visibleMask),
          metrics,
          rect,
        );
      } else {
        const sources = this.visibilityGeometry.sources || [];
        const limitedPolygons = sources
          .filter((source) => source.limitedByLight)
          .map((source) => source.polygon);
        const direct = sources
          .flatMap((source) => [
            ...(source.limitedByLight ? [] : [source.polygon]),
            source.minimumPolygon,
            source.darkvisionPolygon,
          ])
          .filter(Boolean);
        this.tracePolygons(context, direct, metrics, rect);
        const geometry = this.visibilityGeometry.illuminationGeometry || {};
        if (geometry.global && !geometry.darknessPolygons?.length) {
          this.tracePolygons(context, limitedPolygons, metrics, rect);
        } else if (limitedPolygons.length) {
          const limitedCanvas = this.buffer(
            "limited-vision",
            target.width,
            target.height,
          );
          const limited = this.clearBuffer(limitedCanvas);
          this.tracePolygons(limited, limitedPolygons, metrics, rect);

          const illuminationCanvas = this.buffer(
            "illumination",
            target.width,
            target.height,
          );
          const illumination = this.clearBuffer(illuminationCanvas);
          if (geometry.global)
            illumination.fillRect(0, 0, target.width, target.height);
          this.tracePolygons(
            illumination,
            geometry.lightPolygons || [],
            metrics,
            rect,
          );
          illumination.globalCompositeOperation = "destination-out";
          this.tracePolygons(
            illumination,
            geometry.darknessPolygons || [],
            metrics,
            rect,
          );
          limited.globalCompositeOperation = "destination-in";
          limited.drawImage(illuminationCanvas, 0, 0);
          context.globalCompositeOperation = "source-over";
          context.drawImage(limitedCanvas, 0, 0);
        }
      }

      if (this.hasHiddenCells)
        this.drawLogicalMask(
          context,
          this.logicalMask("logical-hidden", this.hiddenMask),
          metrics,
          rect,
          "destination-out",
        );
    },
    softenMask(hard, featherPixels) {
      if (featherPixels < 0.5) return hard;
      const blurred = this.buffer("mask-blurred", hard.width, hard.height);
      const blurContext = this.clearBuffer(blurred);
      blurContext.filter = `blur(${Math.max(0.5, featherPixels / 2)}px)`;
      blurContext.drawImage(hard, 0, 0);
      blurContext.filter = "none";

      const soft = this.buffer("mask-soft", hard.width, hard.height);
      const softContext = this.clearBuffer(soft);
      softContext.drawImage(blurred, 0, 0);
      softContext.globalCompositeOperation = "destination-in";
      softContext.drawImage(hard, 0, 0);
      softContext.drawImage(blurred, 0, 0);
      softContext.drawImage(blurred, 0, 0);
      return soft;
    },
    replaceFogRegion(context, mask, colorValue, opacity, layer, image = null) {
      context.save();
      context.globalCompositeOperation = "destination-out";
      context.drawImage(mask, 0, 0);
      context.restore();
      const layerContext = this.clearBuffer(layer);
      layerContext.globalAlpha = opacity;
      if (image?.naturalWidth && image?.naturalHeight) {
        const rect = this.fogViewport;
        const sceneWidth = Math.max(1, Number(this.scene.width));
        const sceneHeight = Math.max(1, Number(this.scene.height));
        layerContext.drawImage(
          image,
          (rect.x / sceneWidth) * image.naturalWidth,
          (rect.y / sceneHeight) * image.naturalHeight,
          (rect.width / sceneWidth) * image.naturalWidth,
          (rect.height / sceneHeight) * image.naturalHeight,
          0,
          0,
          layer.width,
          layer.height,
        );
      } else {
        layerContext.fillStyle = colorValue;
        layerContext.fillRect(0, 0, layer.width, layer.height);
      }
      layerContext.globalAlpha = 1;
      layerContext.globalCompositeOperation = "destination-in";
      layerContext.drawImage(mask, 0, 0);
      context.drawImage(layer, 0, 0);
    },
    render() {
      const canvas = this.$refs.fog;
      if (!canvas) return;
      const rect = this.fogViewport;
      const metrics = fogBackingMetrics(
        rect,
        this.camera.scale,
        window.devicePixelRatio || 1,
      );
      if (canvas.width !== metrics.width) canvas.width = metrics.width;
      if (canvas.height !== metrics.height) canvas.height = metrics.height;
      const context = this.clearBuffer(canvas);
      if (!this.applies || !rect.width || !rect.height) return;

      const unexploredOpacity = Math.min(
        1,
        Math.max(0, Number(this.scene.fogUnexploredOpacity) || 0),
      );
      const exploredOpacity = Math.min(
        1,
        Math.max(0, Number(this.scene.fogExploredOpacity) || 0),
      );
      context.globalAlpha = unexploredOpacity;
      context.fillStyle = this.scene.fogUnexploredColor || "#05070B";
      context.fillRect(0, 0, canvas.width, canvas.height);
      context.globalAlpha = 1;

      const masksReady = [
        this.visibleMask,
        this.exploredMask,
        this.hiddenMask,
      ].every((mask) => Number(mask?.length) === Number(this.grid.length));
      if (!masksReady) {
        this.schedule();
        return;
      }

      const featherPixels =
        (this.feather / Math.max(0.01, Number(this.camera.scale) || 1)) *
        metrics.scale;
      if (this.hasExploredCells) {
        const logicalHard = this.buffer(
          "mask-hard",
          canvas.width,
          canvas.height,
        );
        const logicalContext = this.clearBuffer(logicalHard);
        this.drawLogicalMask(
          logicalContext,
          this.logicalMask("logical-explored", this.exploredMask),
          metrics,
          rect,
        );
        const exploredMask = this.softenMask(logicalHard, featherPixels * 0.6);
        this.replaceFogRegion(
          context,
          exploredMask,
          this.scene.fogExploredColor || "#202733",
          exploredOpacity,
          this.buffer("fog-layer", canvas.width, canvas.height),
          this.fogImage,
        );
      }

      const visibleHard = this.buffer("mask-hard", canvas.width, canvas.height);
      this.renderVisibilityMask(visibleHard, metrics, rect);
      const visibleSoft = this.softenMask(visibleHard, featherPixels);
      context.globalCompositeOperation = "destination-out";
      context.drawImage(visibleSoft, 0, 0);
      context.globalCompositeOperation = "source-over";

      if (this.hasHiddenCells) {
        const hiddenHard = this.buffer(
          "mask-hard",
          canvas.width,
          canvas.height,
        );
        const hiddenContext = this.clearBuffer(hiddenHard);
        this.drawLogicalMask(
          hiddenContext,
          this.logicalMask("logical-hidden-final", this.hiddenMask),
          metrics,
          rect,
        );
        const hiddenSoft = this.softenMask(hiddenHard, featherPixels * 0.6);
        this.replaceFogRegion(
          context,
          hiddenSoft,
          this.scene.fogUnexploredColor || "#05070B",
          unexploredOpacity,
          this.buffer("fog-layer", canvas.width, canvas.height),
        );
      }
    },
    publishVisibility() {
      const visibleTokenIds = this.tokens
        .filter((token) => {
          if (!this.applies) return true;
          const index = cellAtPoint(this.grid, {
            x: Number(token.x) + Number(token.width) / 2,
            y: Number(token.y) + Number(token.height) / 2,
          });
          return index >= 0 && this.visibleMask[index] > 0;
        })
        .map((token) => token.id);
      this.$emit("visibility-change", {
        visibleTokenIds,
        visionTokenIds: this.visionTokens.map((token) => token.id),
        constrained: this.applies,
      });
    },
    scenePoint(event) {
      const bounds = this.$refs.editor.getBoundingClientRect();
      return {
        x: ((event.clientX - bounds.left) / bounds.width) * this.scene.width,
        y: ((event.clientY - bounds.top) / bounds.height) * this.scene.height,
      };
    },
    pointerDown(event) {
      if (!this.editing || this.preview?.mode !== "user" || event.button !== 0)
        return;
      const point = this.scenePoint(event);
      if (this.tool.endsWith("-poly")) {
        this.polygon.push(point);
        this.renderEditor();
        return;
      }
      this.drag = {
        start: point,
        point,
        mask: new Uint8Array(this.grid.length),
      };
      if (this.tool.includes("brush")) this.brush(point);
      event.currentTarget.setPointerCapture?.(event.pointerId);
    },
    pointerMove(event) {
      const point = this.scenePoint(event);
      if (this.tool.includes("brush")) this.brushCursor = point;
      else this.brushCursor = null;
      if (!this.drag) {
        this.renderEditor();
        return;
      }
      const previous = this.drag.point;
      this.drag.point = point;
      if (this.tool.includes("brush")) this.brush(point, previous);
      else this.renderEditor();
    },
    pointerLeave() {
      if (this.drag) return;
      this.brushCursor = null;
      this.renderEditor();
    },
    pointerUp() {
      if (!this.drag) return;
      if (this.tool.endsWith("-rect")) {
        const { start, point } = this.drag;
        markPolygon(this.drag.mask, this.grid, [
          start,
          { x: point.x, y: start.y },
          point,
          { x: start.x, y: point.y },
        ]);
      }
      this.commitManual(
        this.tool.startsWith("hide") ? "hide" : "reveal",
        this.drag.mask,
      );
      this.drag = null;
      this.renderEditor();
    },
    pointerCancel() {
      this.drag = null;
      this.renderEditor();
    },
    brush(point, previous = null) {
      const distance = previous
        ? Math.hypot(point.x - previous.x, point.y - previous.y)
        : 0;
      const spacing = Math.max(
        this.grid.cellSize * 0.35,
        this.resolvedBrushSize * 0.12,
      );
      const steps = Math.max(1, Math.ceil(distance / spacing));
      for (let step = 1; step <= steps; step += 1) {
        const ratio = step / steps;
        const sample = previous
          ? {
              x: previous.x + (point.x - previous.x) * ratio,
              y: previous.y + (point.y - previous.y) * ratio,
            }
          : point;
        markBrush(
          this.drag.mask,
          this.grid,
          sample,
          this.resolvedBrushSize,
          this.brushHardness / 100,
        );
      }
      this.renderEditor(this.drag.mask);
    },
    finishPolygon() {
      if (this.polygon.length < 3) return;
      const mask = new Uint8Array(this.grid.length);
      markPolygon(mask, this.grid, this.polygon);
      this.commitManual(this.tool.startsWith("hide") ? "hide" : "reveal", mask);
      this.polygon = [];
      this.clearEditor();
    },
    commitManual(mode, mask) {
      const ranges = rangesFromMask(mask);
      if (ranges.length)
        this.$emit("patch", { mode, cellSize: this.grid.cellSize, ranges });
    },
    command(mode) {
      this.$emit("patch", { mode, cellSize: this.grid.cellSize, ranges: [] });
    },
    renderEditor(mask = null) {
      const canvas = this.$refs.editor;
      if (!canvas) return;
      const context = canvas.getContext("2d");
      context.clearRect(0, 0, canvas.width, canvas.height);
      const hiding = this.tool.startsWith("hide");
      context.fillStyle = hiding
        ? "rgba(198,72,65,.22)"
        : "rgba(208,160,92,.16)";
      context.strokeStyle = hiding
        ? "rgba(246,120,110,.9)"
        : "rgba(232,190,124,.92)";
      context.lineWidth = Math.max(1, 2 * this.editorScale);
      context.setLineDash([
        Math.max(2, 7 * this.editorScale),
        Math.max(2, 5 * this.editorScale),
      ]);
      if (mask)
        rangesFromMask(mask).forEach(([start, end]) => {
          for (let index = start; index <= end; index += 1)
            context.fillRect(
              (index % this.grid.columns) *
                this.grid.cellSize *
                this.editorScale,
              Math.floor(index / this.grid.columns) *
                this.grid.cellSize *
                this.editorScale,
              this.grid.cellSize * this.editorScale,
              this.grid.cellSize * this.editorScale,
            );
        });
      if (this.drag && this.tool.endsWith("-rect")) {
        const x = Math.min(this.drag.start.x, this.drag.point.x);
        const y = Math.min(this.drag.start.y, this.drag.point.y);
        const width = Math.abs(this.drag.start.x - this.drag.point.x);
        const height = Math.abs(this.drag.start.y - this.drag.point.y);
        context.fillRect(
          x * this.editorScale,
          y * this.editorScale,
          width * this.editorScale,
          height * this.editorScale,
        );
        context.strokeRect(
          x * this.editorScale,
          y * this.editorScale,
          width * this.editorScale,
          height * this.editorScale,
        );
      }
      if (this.polygon.length) {
        context.beginPath();
        this.polygon.forEach((point, index) => {
          const x = point.x * this.editorScale;
          const y = point.y * this.editorScale;
          index ? context.lineTo(x, y) : context.moveTo(x, y);
        });
        context.stroke();
      }
      if (this.brushCursor && this.tool.includes("brush")) {
        const x = this.brushCursor.x * this.editorScale;
        const y = this.brushCursor.y * this.editorScale;
        const radius = (this.resolvedBrushSize / 2) * this.editorScale;
        const inner = radius * (this.brushHardness / 100);
        const gradient = context.createRadialGradient(
          x,
          y,
          Math.min(inner, radius * 0.999),
          x,
          y,
          Math.max(radius, 0.001),
        );
        gradient.addColorStop(
          0,
          hiding ? "rgba(198,72,65,.22)" : "rgba(208,160,92,.18)",
        );
        gradient.addColorStop(1, "rgba(0,0,0,0)");
        context.save();
        context.setLineDash([]);
        context.fillStyle = gradient;
        context.beginPath();
        context.arc(x, y, radius, 0, Math.PI * 2);
        context.fill();
        context.stroke();
        context.restore();
      }
    },
    clearEditor() {
      this.$refs.editor
        ?.getContext("2d")
        .clearRect(0, 0, this.editorSize.width, this.editorSize.height);
    },
    resetEditor() {
      this.drag = null;
      this.polygon = [];
      this.brushCursor = null;
      this.clearEditor();
      this.schedule();
    },
    setBrush(settings) {
      this.brushSize = Math.max(
        this.grid.cellSize,
        Math.min(
          this.brushMaximum,
          Number(settings.size) || this.resolvedBrushSize,
        ),
      );
      this.brushHardness = Math.max(
        0,
        Math.min(100, Number(settings.hardness) || 0),
      );
      this.renderEditor();
    },
  },
};
</script>

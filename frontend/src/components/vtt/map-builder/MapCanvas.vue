<template>
  <div
    ref="host"
    class="map-canvas"
    tabindex="0"
    :aria-label="`Płótno mapy ${document.width} × ${document.height}`"
    @wheel.prevent="onWheel"
    @pointerdown="onPointerDown"
  >
    <div v-if="loading" class="map-canvas__loading">Ładowanie renderera…</div>
    <output class="map-canvas__zoom"
      >{{ Math.round(camera.scale * 100) }}%</output
    >
  </div>
</template>

<script>
import { markRaw } from "vue";
import {
  Application,
  Assets,
  Container,
  Graphics,
  Rectangle,
  Sprite,
  Text,
  Texture,
} from "pixi.js";
import { starterAssetById } from "./starterAssets";
import { resolveAccessToken } from "@/lib/api/jsonApiClient";

const colorNumber = (value, fallback = 0x808080) => {
  const normalized = String(value || "")
    .replace("#", "")
    .slice(0, 6);
  const parsed = Number.parseInt(normalized, 16);
  return Number.isFinite(parsed) ? parsed : fallback;
};

const loadHtmlImage = (url) =>
  new Promise((resolve, reject) => {
    const image = new Image();
    image.decoding = "async";
    image.onload = () => resolve(image);
    image.onerror = reject;
    image.src = url;
  });

export default {
  name: "MapCanvas",
  props: {
    document: { type: Object, required: true },
    selectedIds: { type: Array, default: () => [] },
    activeTool: { type: String, default: "select" },
    showGrid: { type: Boolean, default: true },
    playerPreview: { type: Boolean, default: false },
  },
  emits: ["select", "gesture", "camera-change"],
  data: () => ({
    loading: true,
    camera: { x: 40, y: 40, scale: 0.25 },
    gesture: null,
    app: null,
    world: null,
    content: null,
    atlasTextures: markRaw(new Map()),
    protectedObjectUrls: markRaw(new Map()),
    renderGeneration: 0,
  }),
  watch: {
    document: { deep: true, handler: "render" },
    selectedIds: { deep: true, handler: "render" },
    showGrid: "render",
    playerPreview: "render",
  },
  async mounted() {
    const app = markRaw(new Application());
    await app.init({
      resizeTo: this.$refs.host,
      antialias: true,
      backgroundAlpha: 0,
      preference: "webgl",
      autoDensity: true,
      resolution: Math.min(window.devicePixelRatio || 1, 2),
    });
    app.canvas.className = "map-canvas__surface";
    this.$refs.host.prepend(app.canvas);
    this.app = app;
    this.world = markRaw(new Container());
    this.content = markRaw(new Container());
    this.world.addChild(this.content);
    app.stage.addChild(this.world);
    this.loading = false;
    this.fit();
    await this.render();
  },
  beforeUnmount() {
    window.removeEventListener("pointermove", this.onPointerMove);
    window.removeEventListener("pointerup", this.onPointerUp);
    this.app?.destroy(true, {
      children: true,
      texture: false,
      textureSource: false,
    });
    for (const url of this.protectedObjectUrls.values())
      URL.revokeObjectURL(url);
    this.protectedObjectUrls.clear();
    this.atlasTextures.clear();
  },
  methods: {
    applyCamera() {
      if (!this.world) return;
      this.world.position.set(this.camera.x, this.camera.y);
      this.world.scale.set(this.camera.scale);
      this.$emit("camera-change", { ...this.camera });
    },
    fit() {
      const bounds = this.$refs.host?.getBoundingClientRect();
      if (!bounds) return;
      const scale = Math.max(
        0.05,
        Math.min(
          2,
          (bounds.width - 80) / this.document.width,
          (bounds.height - 80) / this.document.height,
        ),
      );
      this.camera = {
        scale,
        x: (bounds.width - this.document.width * scale) / 2,
        y: (bounds.height - this.document.height * scale) / 2,
      };
      this.applyCamera();
    },
    worldPoint(event) {
      const bounds = this.$refs.host.getBoundingClientRect();
      return {
        x: (event.clientX - bounds.left - this.camera.x) / this.camera.scale,
        y: (event.clientY - bounds.top - this.camera.y) / this.camera.scale,
      };
    },
    onWheel(event) {
      const bounds = this.$refs.host.getBoundingClientRect();
      const cursorX = event.clientX - bounds.left;
      const cursorY = event.clientY - bounds.top;
      const worldX = (cursorX - this.camera.x) / this.camera.scale;
      const worldY = (cursorY - this.camera.y) / this.camera.scale;
      const nextScale = Math.max(
        0.04,
        Math.min(4, this.camera.scale * Math.exp(-event.deltaY * 0.0015)),
      );
      this.camera = {
        scale: nextScale,
        x: cursorX - worldX * nextScale,
        y: cursorY - worldY * nextScale,
      };
      this.applyCamera();
    },
    onPointerDown(event) {
      if (event.button !== 0 && event.button !== 1) return;
      const point = this.worldPoint(event);
      const pan =
        this.activeTool === "pan" || event.button === 1 || event.altKey;
      const hit =
        !pan && this.activeTool === "select" ? this.hitObject(point) : null;
      const hitLayer = hit
        ? this.document.layers.find((layer) => layer.id === hit.layerId)
        : null;
      this.gesture = {
        pointerId: event.pointerId,
        pan,
        shiftKey: event.shiftKey,
        startClient: { x: event.clientX, y: event.clientY },
        startCamera: { ...this.camera },
        start: point,
        points: [point],
        last: point,
        moved: false,
        dragObjectId: hit && !hit.locked && !hitLayer?.locked ? hit.id : null,
      };
      if (!pan && this.activeTool === "select") {
        this.$emit("select", { id: hit?.id || null, additive: event.shiftKey });
      }
      window.addEventListener("pointermove", this.onPointerMove);
      window.addEventListener("pointerup", this.onPointerUp, { once: true });
      event.preventDefault();
    },
    onPointerMove(event) {
      if (!this.gesture || event.pointerId !== this.gesture.pointerId) return;
      if (this.gesture.pan) {
        this.camera.x =
          this.gesture.startCamera.x +
          event.clientX -
          this.gesture.startClient.x;
        this.camera.y =
          this.gesture.startCamera.y +
          event.clientY -
          this.gesture.startClient.y;
        this.applyCamera();
        return;
      }
      const point = this.worldPoint(event);
      const distance = Math.hypot(
        point.x - this.gesture.last.x,
        point.y - this.gesture.last.y,
      );
      if (distance >= Math.max(4, 8 / this.camera.scale)) {
        this.gesture.moved = true;
        this.gesture.points.push(point);
        this.gesture.last = point;
        if (
          ["terrain", "erase", "road", "river", "fence"].includes(
            this.activeTool,
          )
        ) {
          this.$emit("gesture", {
            phase: "move",
            tool: this.activeTool,
            start: this.gesture.start,
            end: point,
            points: [...this.gesture.points],
          });
        }
      }
    },
    onPointerUp(event) {
      if (!this.gesture || event.pointerId !== this.gesture.pointerId) return;
      const gesture = this.gesture;
      this.gesture = null;
      window.removeEventListener("pointermove", this.onPointerMove);
      if (gesture.pan) return;
      const end = this.worldPoint(event);
      if (this.activeTool === "select") {
        if (gesture.dragObjectId && gesture.moved) {
          this.$emit("gesture", {
            phase: "end",
            tool: "moveSelection",
            objectId: gesture.dragObjectId,
            start: gesture.start,
            end,
          });
        }
      } else {
        this.$emit("gesture", {
          phase: "end",
          tool: this.activeTool,
          start: gesture.start,
          end,
          points: [...gesture.points, end],
          shiftKey: gesture.shiftKey,
        });
      }
    },
    hitObject(point) {
      const visibleLayers = new Set(
        this.document.layers
          .filter((layer) => layer.visible !== false)
          .map((layer) => layer.id),
      );
      const visibleLevels = new Set(
        this.document.levels
          .filter((level) => level.visible !== false)
          .map((level) => level.id),
      );
      return [...this.document.objects].reverse().find((object) => {
        if (
          object.visible === false ||
          !visibleLayers.has(object.layerId) ||
          !visibleLevels.has(object.levelId)
        )
          return false;
        const width = Math.abs(
          Number(object.width) * Number(object.scaleX || 1),
        );
        const height = Math.abs(
          Number(object.height) * Number(object.scaleY || 1),
        );
        return (
          Math.abs(point.x - object.x) <= width / 2 &&
          Math.abs(point.y - object.y) <= height / 2
        );
      });
    },
    async atlasTexture(asset) {
      if (!asset?.source?.url) return null;
      const key = `${asset.source.url}:${JSON.stringify(asset.source.frame || null)}`;
      if (this.atlasTextures.has(key)) return this.atlasTextures.get(key);
      const base = await Assets.load(asset.source.url);
      const frame = asset.source.frame;
      const texture = frame
        ? new Texture({
            source: base.source,
            frame: new Rectangle(frame.x, frame.y, frame.width, frame.height),
          })
        : base;
      this.atlasTextures.set(key, texture);
      return texture;
    },
    async protectedUrl(url) {
      const source = String(url || "");
      if (!source.startsWith("/api/")) return source;
      if (this.protectedObjectUrls.has(source))
        return this.protectedObjectUrls.get(source);
      const token = resolveAccessToken();
      const response = await window.fetch(source, {
        headers: {
          Accept: "image/*",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        credentials: "same-origin",
      });
      if (!response.ok) throw new Error(`map_asset_http_${response.status}`);
      const objectUrl = URL.createObjectURL(await response.blob());
      this.protectedObjectUrls.set(source, objectUrl);
      return objectUrl;
    },
    async objectTexture(object) {
      const asset = starterAssetById(object.assetId);
      if (asset) return this.atlasTexture(asset);
      const key = `${object.assetId}:${object.assetVersion || 1}:${object.assetUrl || ""}`;
      if (this.atlasTextures.has(key)) return this.atlasTextures.get(key);
      const texture = markRaw(
        await Assets.load(await this.protectedUrl(object.assetUrl)),
      );
      this.atlasTextures.set(key, texture);
      return texture;
    },
    drawGrid(target) {
      if (!this.showGrid || this.document.grid.type === "none") return;
      const grid = new Graphics();
      const size = Math.max(10, Number(this.document.grid.size) || 100);
      const color = colorNumber(this.document.grid.color, 0xd8cab0);
      const alpha = Number(this.document.grid.opacity) || 0.2;
      if (this.document.grid.type === "hex") {
        const radius = size / Math.sqrt(3);
        const height = radius * Math.sqrt(3);
        for (
          let column = -1;
          column < this.document.width / (radius * 1.5) + 2;
          column += 1
        ) {
          for (
            let row = -1;
            row < this.document.height / height + 2;
            row += 1
          ) {
            const cx = column * radius * 1.5;
            const cy = row * height + (column % 2 ? height / 2 : 0);
            const points = [];
            for (let index = 0; index < 6; index += 1) {
              const angle = (Math.PI / 3) * index;
              points.push(
                cx + radius * Math.cos(angle),
                cy + radius * Math.sin(angle),
              );
            }
            grid.poly(points).closePath().stroke({ color, alpha, width: 1 });
          }
        }
      } else {
        for (
          let x = Number(this.document.grid.offsetX) || 0;
          x <= this.document.width;
          x += size
        ) {
          grid.moveTo(x, 0).lineTo(x, this.document.height);
        }
        for (
          let y = Number(this.document.grid.offsetY) || 0;
          y <= this.document.height;
          y += size
        ) {
          grid.moveTo(0, y).lineTo(this.document.width, y);
        }
        grid.stroke({ color, alpha, width: 1 });
      }
      target.addChild(grid);
    },
    async render() {
      if (!this.content) return;
      const generation = ++this.renderGeneration;
      this.content
        .removeChildren()
        .forEach((child) => child.destroy({ children: true }));
      const background = new Graphics()
        .rect(0, 0, this.document.width, this.document.height)
        .fill(colorNumber(this.document.backgroundColor, 0x222820));
      this.content.addChild(background);
      const layers = [...this.document.layers].sort(
        (a, b) => Number(a.order) - Number(b.order),
      );
      const visibleLevels = new Set(
        this.document.levels
          .filter((level) => level.visible !== false)
          .map((level) => level.id),
      );
      const selected = new Set(this.selectedIds);
      for (const layer of layers) {
        if (
          layer.visible === false ||
          (this.playerPreview && layer.private === true)
        )
          continue;
        const container = new Container();
        container.alpha = Number(layer.opacity ?? 1);
        const objects = this.document.objects.filter(
          (object) =>
            object.layerId === layer.id &&
            visibleLevels.has(object.levelId) &&
            object.visible !== false &&
            (!this.playerPreview || object.private !== true),
        );
        for (const object of objects)
          await this.drawObject(container, object, selected.has(object.id));
        if (generation !== this.renderGeneration) {
          container.destroy({ children: true });
          return;
        }
        this.content.addChild(container);
      }
      this.drawGrid(this.content);
      this.applyCamera();
    },
    async drawObject(container, object, isSelected) {
      let display;
      const pathTypes = [
        "path",
        "road",
        "river",
        "fence",
        "wall",
        "door",
        "window",
        "brush",
      ];
      if (object.type === "asset") {
        try {
          display = new Sprite(await this.objectTexture(object));
          display.anchor.set(
            Number(object.anchor?.x ?? 0.5),
            Number(object.anchor?.y ?? 0.5),
          );
          display.width = object.width;
          display.height = object.height;
        } catch (_error) {
          display = new Graphics()
            .rect(
              -object.width / 2,
              -object.height / 2,
              object.width,
              object.height,
            )
            .fill({ color: 0x4b2630, alpha: 0.8 });
        }
      } else if (
        object.type === "room" &&
        object.shape === "composite" &&
        Array.isArray(object.booleanParts)
      ) {
        display = new Graphics();
        const parts = [
          ...object.booleanParts.filter(
            (part) => part.operation !== "subtract",
          ),
          ...object.booleanParts.filter(
            (part) => part.operation === "subtract",
          ),
        ];
        for (const part of parts) {
          this.addRoomPart(display, part, object);
          if (part.operation === "subtract") display.cut();
          else
            display.fill({
              color: colorNumber(object.color, 0x74644c),
              alpha: 1,
            });
        }
      } else if (object.type === "text" || object.type === "marker") {
        display = new Text({
          text:
            object.type === "marker"
              ? `◆ ${object.text || "Znacznik"}`
              : object.text || "Tekst",
          style: {
            fill: object.color || "#f5e6bd",
            fontFamily: "Georgia, serif",
            fontSize: object.fontSize || 36,
            align: "center",
            stroke: { color: "#12100e", width: 4 },
          },
        });
        display.anchor.set(0.5);
      } else {
        display = new Graphics();
        const color = colorNumber(
          object.color,
          object.type === "light" ? 0xffb35c : 0x74644c,
        );
        if (pathTypes.includes(object.type) && object.points?.length > 1) {
          const trace = () => {
            display.moveTo(
              object.points[0].x - object.x,
              object.points[0].y - object.y,
            );
            for (const point of object.points.slice(1))
              display.lineTo(point.x - object.x, point.y - object.y);
          };
          if (object.type === "brush") {
            const hardness = Math.max(
              0,
              Math.min(1, Number(object.hardness ?? 0.65)),
            );
            const flow = Math.max(0.05, Math.min(1, Number(object.flow ?? 1)));
            trace();
            display.stroke({
              color,
              alpha: (1 - hardness) * flow * 0.32,
              width: (object.thickness || 24) * (1.7 - hardness * 0.35),
              cap: "round",
              join: "round",
            });
            trace();
            display.stroke({
              color,
              alpha: Math.max(0.12, hardness) * flow,
              width: object.thickness || 24,
              cap: "round",
              join: "round",
            });
          } else {
            trace();
            display.stroke({
              color,
              alpha: 1,
              width: object.thickness || 24,
              cap: "round",
              join: "round",
            });
          }
        } else if (object.shape === "circle" || object.type === "light") {
          display
            .ellipse(0, 0, object.width / 2, object.height / 2)
            .fill({ color, alpha: 1 });
        } else if (object.points?.length > 2) {
          display
            .poly(
              object.points.flatMap((point) => [
                point.x - object.x,
                point.y - object.y,
              ]),
            )
            .closePath()
            .fill({ color, alpha: 1 });
        } else {
          display
            .rect(
              -object.width / 2,
              -object.height / 2,
              object.width,
              object.height,
            )
            .fill({
              color,
              alpha: 1,
            });
        }
      }
      display.position.set(object.x, object.y);
      display.rotation = (Number(object.rotation) * Math.PI) / 180;
      display.scale.set(Number(object.scaleX) || 1, Number(object.scaleY) || 1);
      display.alpha = Number(object.opacity ?? 1);
      if (
        object.type === "brush" &&
        ["inside-rooms", "outside-rooms"].includes(object.maskMode)
      ) {
        const mask = this.roomMaskGraphics(object.maskMode, object.levelId);
        if (mask) {
          container.addChild(mask);
          display.mask = mask;
        }
      }
      container.addChild(display);
      if (isSelected) {
        const selection = new Graphics()
          .rect(
            -object.width / 2 - 5,
            -object.height / 2 - 5,
            object.width + 10,
            object.height + 10,
          )
          .stroke({
            color: 0xefca7c,
            width: Math.max(2, 3 / this.camera.scale),
            alpha: 1,
          });
        selection.position.set(object.x, object.y);
        selection.rotation = display.rotation;
        selection.scale.set(
          Number(object.scaleX) || 1,
          Number(object.scaleY) || 1,
        );
        container.addChild(selection);
      }
    },
    addRoomPart(graphics, part, object) {
      graphics
        .poly(
          this.roomPartPoints(part).flatMap((point) => [
            point.x - object.x,
            point.y - object.y,
          ]),
        )
        .closePath();
      return graphics;
    },
    roomPartPoints(part) {
      const centerX = Number(part.x) || 0;
      const centerY = Number(part.y) || 0;
      const width = Math.max(1, Number(part.width) || 1);
      const height = Math.max(1, Number(part.height) || 1);
      let points;
      if (part.shape === "circle") {
        points = Array.from({ length: 32 }, (_value, index) => {
          const angle = (Math.PI * 2 * index) / 32;
          return {
            x: centerX + Math.cos(angle) * width * 0.5,
            y: centerY + Math.sin(angle) * height * 0.5,
          };
        });
      } else if (part.shape === "polygon" && part.points?.length >= 3) {
        points = part.points.map((point) => ({
          x: Number(point.x),
          y: Number(point.y),
        }));
      } else {
        points = [
          { x: centerX - width / 2, y: centerY - height / 2 },
          { x: centerX + width / 2, y: centerY - height / 2 },
          { x: centerX + width / 2, y: centerY + height / 2 },
          { x: centerX - width / 2, y: centerY + height / 2 },
        ];
      }
      const angle = (Number(part.rotation) * Math.PI) / 180;
      const scaleX = Number(part.scaleX) || 1;
      const scaleY = Number(part.scaleY) || 1;
      return points.map((point) => {
        const dx = (point.x - centerX) * scaleX;
        const dy = (point.y - centerY) * scaleY;
        return {
          x: centerX + dx * Math.cos(angle) - dy * Math.sin(angle),
          y: centerY + dx * Math.sin(angle) + dy * Math.cos(angle),
        };
      });
    },
    roomMaskGraphics(mode, levelId) {
      const rooms = this.document.objects.filter(
        (item) =>
          item.type === "room" &&
          item.visible !== false &&
          (!levelId || item.levelId === levelId),
      );
      if (!rooms.length) return null;
      const mask = new Graphics();
      if (mode === "outside-rooms") {
        mask
          .rect(0, 0, this.document.width, this.document.height)
          .fill({ color: 0xffffff, alpha: 1 });
      }
      for (const room of rooms) {
        const parts =
          room.shape === "composite" && Array.isArray(room.booleanParts)
            ? [
                ...room.booleanParts.filter(
                  (part) => part.operation !== "subtract",
                ),
                ...room.booleanParts.filter(
                  (part) => part.operation === "subtract",
                ),
              ]
            : [{ ...room, operation: "add" }];
        for (const part of parts) {
          this.addRoomPart(mask, part, { x: 0, y: 0 });
          const subtract = part.operation === "subtract";
          const cut = mode === "outside-rooms" ? !subtract : subtract;
          if (cut) mask.cut();
          else mask.fill({ color: 0xffffff, alpha: 1 });
        }
      }
      return mask;
    },
    clipCanvasToRooms(context, mode, object) {
      const rooms = this.document.objects.filter(
        (item) =>
          item.type === "room" &&
          item.visible !== false &&
          (!object.levelId || item.levelId === object.levelId),
      );
      if (!rooms.length) return;
      context.beginPath();
      if (mode === "outside-rooms") {
        context.rect(
          -object.x,
          -object.y,
          this.document.width,
          this.document.height,
        );
      }
      for (const room of rooms) {
        const parts = Array.isArray(room.booleanParts)
          ? room.booleanParts
          : [{ ...room, operation: "add" }];
        for (const part of parts) {
          const points = this.roomPartPoints(part);
          points.forEach((point, index) => {
            const x = point.x - object.x;
            const y = point.y - object.y;
            if (index) context.lineTo(x, y);
            else context.moveTo(x, y);
          });
          context.closePath();
        }
      }
      context.clip("evenodd");
    },
    fillCompositeCanvas(context, object) {
      context.beginPath();
      const parts = [
        ...object.booleanParts.filter((part) => part.operation !== "subtract"),
        ...object.booleanParts.filter((part) => part.operation === "subtract"),
      ];
      for (const part of parts) {
        const subtract = part.operation === "subtract";
        const source = this.roomPartPoints(part);
        const points = subtract ? [...source].reverse() : source;
        points.forEach((point, index) => {
          const px = point.x - object.x;
          const py = point.y - object.y;
          if (index) context.lineTo(px, py);
          else context.moveTo(px, py);
        });
        context.closePath();
      }
      context.fill();
    },
    async exportBlob({ format = "webp", includeGrid = true } = {}) {
      const limit = 8192;
      const scale = Math.min(
        1,
        limit / Math.max(this.document.width, this.document.height),
      );
      const canvas = document.createElement("canvas");
      canvas.width = Math.max(1, Math.round(this.document.width * scale));
      canvas.height = Math.max(1, Math.round(this.document.height * scale));
      const context = canvas.getContext("2d");
      context.scale(scale, scale);
      context.fillStyle = this.document.backgroundColor;
      context.fillRect(0, 0, this.document.width, this.document.height);
      const atlas = await loadHtmlImage(
        "/map-builder/assets/starter-medieval-atlas-v1.png",
      );
      const visibleLayers = new Map(
        this.document.layers.map((layer) => [layer.id, layer]),
      );
      const visibleLevels = new Set(
        this.document.levels
          .filter((level) => level.visible !== false)
          .map((level) => level.id),
      );
      const objects = [...this.document.objects].sort(
        (left, right) =>
          Number(visibleLayers.get(left.layerId)?.order || 0) -
          Number(visibleLayers.get(right.layerId)?.order || 0),
      );
      for (const object of objects) {
        const layer = visibleLayers.get(object.layerId);
        if (
          !layer ||
          layer.visible === false ||
          !visibleLevels.has(object.levelId) ||
          object.visible === false ||
          (this.playerPreview && (object.private || layer.private))
        )
          continue;
        context.save();
        context.globalAlpha =
          Number(layer.opacity ?? 1) * Number(object.opacity ?? 1);
        context.translate(object.x, object.y);
        if (
          object.type === "brush" &&
          ["inside-rooms", "outside-rooms"].includes(object.maskMode)
        ) {
          this.clipCanvasToRooms(context, object.maskMode, object);
        }
        context.rotate((Number(object.rotation) * Math.PI) / 180);
        context.scale(Number(object.scaleX) || 1, Number(object.scaleY) || 1);
        const asset =
          object.type === "asset" ? starterAssetById(object.assetId) : null;
        if (asset?.source?.frame) {
          const frame = asset.source.frame;
          context.drawImage(
            atlas,
            frame.x,
            frame.y,
            frame.width,
            frame.height,
            -object.width / 2,
            -object.height / 2,
            object.width,
            object.height,
          );
        } else if (object.type === "asset" && object.assetUrl) {
          const image = await loadHtmlImage(
            await this.protectedUrl(object.assetUrl),
          );
          context.drawImage(
            image,
            -object.width / 2,
            -object.height / 2,
            object.width,
            object.height,
          );
        } else if (
          [
            "road",
            "river",
            "fence",
            "wall",
            "door",
            "window",
            "brush",
          ].includes(object.type)
        ) {
          context.strokeStyle = object.color || "#74644c";
          context.lineCap = "round";
          context.lineJoin = "round";
          const trace = () => {
            context.beginPath();
            object.points.forEach((point, index) => {
              const x = point.x - object.x;
              const y = point.y - object.y;
              if (index) context.lineTo(x, y);
              else context.moveTo(x, y);
            });
          };
          if (object.type === "brush") {
            const hardness = Math.max(
              0,
              Math.min(1, Number(object.hardness ?? 0.65)),
            );
            const flow = Math.max(0.05, Math.min(1, Number(object.flow ?? 1)));
            const alpha = context.globalAlpha;
            trace();
            context.globalAlpha = alpha * (1 - hardness) * flow * 0.32;
            context.lineWidth =
              (object.thickness || 24) * (1.7 - hardness * 0.35);
            context.stroke();
            trace();
            context.globalAlpha = alpha * Math.max(0.12, hardness) * flow;
            context.lineWidth = object.thickness || 24;
            context.stroke();
          } else {
            trace();
            context.lineWidth = object.thickness || 24;
            context.stroke();
          }
        } else if (
          object.type === "room" &&
          object.shape === "composite" &&
          Array.isArray(object.booleanParts)
        ) {
          context.fillStyle = object.color || "#74644c";
          this.fillCompositeCanvas(context, object);
        } else if (object.type === "text" || object.type === "marker") {
          context.fillStyle = object.color || "#f5e6bd";
          context.font = `${Number(object.fontSize) || 36}px Georgia, serif`;
          context.textAlign = "center";
          context.textBaseline = "middle";
          context.fillText(
            object.type === "marker"
              ? `◆ ${object.text || "Znacznik"}`
              : object.text || "Tekst",
            0,
            0,
          );
        } else {
          context.fillStyle = object.color || "#74644c";
          if (object.shape === "circle" || object.type === "light") {
            context.beginPath();
            context.ellipse(
              0,
              0,
              object.width / 2,
              object.height / 2,
              0,
              0,
              Math.PI * 2,
            );
            context.fill();
          } else
            context.fillRect(
              -object.width / 2,
              -object.height / 2,
              object.width,
              object.height,
            );
        }
        context.restore();
      }
      if (includeGrid && this.document.grid.type !== "none") {
        const size = Number(this.document.grid.size) || 100;
        context.save();
        context.strokeStyle = this.document.grid.color;
        context.globalAlpha = Number(this.document.grid.opacity) || 0.2;
        context.lineWidth = 1 / scale;
        context.beginPath();
        if (this.document.grid.type === "hex") {
          const radius = size / Math.sqrt(3);
          const height = radius * Math.sqrt(3);
          for (
            let column = -1;
            column < this.document.width / (radius * 1.5) + 2;
            column += 1
          ) {
            for (
              let row = -1;
              row < this.document.height / height + 2;
              row += 1
            ) {
              const cx = column * radius * 1.5;
              const cy = row * height + (column % 2 ? height / 2 : 0);
              for (let index = 0; index < 6; index += 1) {
                const angle = (Math.PI / 3) * index;
                const x = cx + radius * Math.cos(angle);
                const y = cy + radius * Math.sin(angle);
                if (index) context.lineTo(x, y);
                else context.moveTo(x, y);
              }
              context.closePath();
            }
          }
        } else {
          const offsetX = Number(this.document.grid.offsetX) || 0;
          const offsetY = Number(this.document.grid.offsetY) || 0;
          for (let x = offsetX; x <= this.document.width; x += size) {
            context.moveTo(x, 0);
            context.lineTo(x, this.document.height);
          }
          for (let y = offsetY; y <= this.document.height; y += size) {
            context.moveTo(0, y);
            context.lineTo(this.document.width, y);
          }
        }
        context.stroke();
        context.restore();
      }
      const mime = format === "png" ? "image/png" : "image/webp";
      return new Promise((resolve, reject) =>
        canvas.toBlob(
          (blob) =>
            blob ? resolve(blob) : reject(new Error("map_export_failed")),
          mime,
          0.9,
        ),
      );
    },
  },
};
</script>

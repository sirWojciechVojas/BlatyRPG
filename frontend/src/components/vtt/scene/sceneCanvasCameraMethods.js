import { nextTick } from "vue";
import { clamp } from "@/lib/vtt/grid";

export const sceneCanvasCameraMethods = {
  emitCamera() {
    const scale = Math.max(0.0001, Number(this.camera.scale) || 1);
    this.$emit("camera-change", {
      zoomPercent: Math.round(scale * 100),
      centerX: (Number(this.viewportSize.width) / 2 - this.camera.x) / scale,
      centerY: (Number(this.viewportSize.height) / 2 - this.camera.y) / scale,
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
    const point = origin || { x: rect.width / 2, y: rect.height / 2 };
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
    if (this.handleTokenAreaSelectionKey?.(event)) return;
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
    if (["+", "="].includes(event.key)) this.zoomBy(1.2);
    else if (["-", "_"].includes(event.key)) this.zoomBy(1 / 1.2);
    else if (event.key === "0" || event.key === "Home") this.fit();
    else return;
    event.preventDefault();
  },
  startPan(event) {
    if (!this.scene || ![0, 1].includes(event.button)) return;
    if (this.startTokenAreaSelection?.(event)) return;
    if (event.button === 0) {
      this.$emit("token-select", { tokenId: null, additive: false });
    }
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
    if (this.moveTokenAreaSelection?.(event)) return;
    if (!this.dragging || this.pointer?.id !== event.pointerId) return;
    this.camera.x = this.pointer.cameraX + event.clientX - this.pointer.x;
    this.camera.y = this.pointer.cameraY + event.clientY - this.pointer.y;
  },
  endPan(event) {
    if (this.endTokenAreaSelection?.(event)) return;
    if (this.pointer?.id !== event.pointerId) return;
    this.dragging = false;
    this.pointer = null;
    event.currentTarget.releasePointerCapture?.(event.pointerId);
    this.emitCamera();
  },
};

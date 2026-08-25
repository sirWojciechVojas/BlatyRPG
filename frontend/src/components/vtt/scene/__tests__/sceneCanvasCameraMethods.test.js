import { describe, expect, it, vi } from "vitest";
import { sceneCanvasCameraMethods } from "../sceneCanvasCameraMethods";

describe("scene canvas selection", () => {
  it("clears the active token before panning an empty map area", () => {
    const emit = vi.fn();
    const capture = vi.fn();
    const context = {
      scene: { id: 4 },
      camera: { x: 10, y: 20 },
      dragging: false,
      pointer: null,
      $emit: emit,
    };

    sceneCanvasCameraMethods.startPan.call(context, {
      button: 0,
      pointerId: 8,
      clientX: 100,
      clientY: 120,
      currentTarget: { setPointerCapture: capture },
    });

    expect(emit).toHaveBeenCalledWith("token-select", {
      tokenId: null,
      additive: false,
    });
    expect(context.dragging).toBe(true);
    expect(capture).toHaveBeenCalledWith(8);
  });
});

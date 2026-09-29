import { describe, expect, it } from "vitest";
import {
  fogBackingMetrics,
  fogViewportRect,
  normalizeFogFeather,
  normalizeFogMask,
} from "@/lib/vtt/fogRenderMetrics";

describe("Fog of War render metrics", () => {
  it("keeps feather width stable in screen pixels across zoom levels", () => {
    const scene = { width: 6000, height: 4000 };
    const viewport = { width: 1200, height: 800 };
    const screenWidths = [0.5, 1, 2].map((zoom) => {
      const rect = fogViewportRect({
        scene,
        camera: { x: -1000, y: -600, scale: zoom },
        viewport,
        feather: 32,
      });
      const backing = fogBackingMetrics(rect, zoom, 2);
      const featherPixels = (32 / zoom) * backing.scale;
      return (featherPixels / backing.scale) * zoom;
    });
    expect(screenWidths).toEqual([32, 32, 32]);
  });

  it("renders only one padded viewport-sized mask on a large scene", () => {
    const rect = fogViewportRect({
      scene: { width: 50000, height: 50000 },
      camera: { x: -10000, y: -8000, scale: 1 },
      viewport: { width: 1600, height: 900 },
      feather: 40,
    });
    const backing = fogBackingMetrics(rect, 1, 2);
    expect(rect.width).toBeLessThan(1800);
    expect(rect.height).toBeLessThan(1100);
    expect(backing.width * backing.height).toBeLessThanOrEqual(12582912);
  });

  it("normalizes enabled feathering to the supported visual range", () => {
    expect(normalizeFogFeather(0)).toBe(0);
    expect(normalizeFogFeather(12)).toBe(20);
    expect(normalizeFogFeather(32)).toBe(32);
    expect(normalizeFogFeather(120)).toBe(50);
  });

  it("rejects missing and stale masks during an asynchronous scene change", () => {
    const current = new Uint8Array(4);
    expect(normalizeFogMask(current, 4)).toBe(current);
    expect(normalizeFogMask(null, 4)).toBeNull();
    expect(normalizeFogMask(new Uint8Array(3), 4)).toBeNull();
  });
});

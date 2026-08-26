import { describe, expect, it, vi } from "vitest";
import { tokenAreaSelectionMethods } from "../tokenAreaSelectionMethods";

const event = (x, y, extra = {}) => ({
  button: 0,
  pointerId: 7,
  clientX: x,
  clientY: y,
  currentTarget: { setPointerCapture: vi.fn() },
  preventDefault: vi.fn(),
  ...extra,
});

const context = (mode = "rectangle") => {
  const vm = {
    activeTool: "tokens",
    tokenSelectionMode: mode,
    tokenSelection: null,
    scene: { width: 500, height: 400 },
    camera: { x: 10, y: 20, scale: 2 },
    mapDimensions: { padding: 5 },
    tokens: [
      {
        id: 1,
        x: 20,
        y: 20,
        width: 20,
        height: 20,
        capabilities: { canControl: true },
      },
      {
        id: 2,
        x: 200,
        y: 200,
        width: 20,
        height: 20,
        capabilities: { canControl: true },
      },
    ],
    $emit: vi.fn(),
    $refs: {
      viewport: {
        getBoundingClientRect: () => ({ left: 30, top: 40 }),
        releasePointerCapture: vi.fn(),
      },
    },
  };
  Object.entries(tokenAreaSelectionMethods).forEach(([name, method]) => {
    vm[name] = method.bind(vm);
  });
  return vm;
};

describe("token area selection interactions", () => {
  it("commits a rectangle drag in scene coordinates", () => {
    const vm = context();
    vm.startTokenAreaSelection(event(40, 50));
    vm.endTokenAreaSelection(event(180, 190, { type: "pointerup" }));
    expect(vm.$emit).toHaveBeenCalledWith("token-select", {
      tokenIds: [1],
      additive: false,
    });
  });

  it("finishes a polygon with Enter", () => {
    const vm = context("polygon");
    vm.addTokenPolygonPoint(event(40, 50));
    vm.addTokenPolygonPoint(event(180, 50));
    vm.addTokenPolygonPoint(event(40, 190));
    const key = { key: "Enter", preventDefault: vi.fn() };
    expect(vm.handleTokenAreaSelectionKey(key)).toBe(true);
    expect(vm.$emit).toHaveBeenCalledWith("token-select", {
      tokenIds: [1],
      additive: false,
    });
  });

  it("cancels an unfinished selection with Escape", () => {
    const vm = context("polygon");
    vm.addTokenPolygonPoint(event(40, 50));
    vm.handleTokenAreaSelectionKey({ key: "Escape", preventDefault: vi.fn() });
    expect(vm.tokenSelection).toBeNull();
    expect(vm.$emit).not.toHaveBeenCalled();
  });
});

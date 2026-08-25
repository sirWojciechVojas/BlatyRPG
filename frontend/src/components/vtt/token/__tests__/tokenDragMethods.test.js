import { afterEach, describe, expect, it, vi } from "vitest";
import { tokenDragMethods } from "../tokenDragMethods";

const pointer = (type, values) => {
  const event = new Event(type);
  Object.entries(values).forEach(([key, value]) =>
    Object.defineProperty(event, key, { value }),
  );
  return event;
};

const context = (scale = 1) => {
  const vm = {
    scale,
    scene: { gridType: "square", gridSize: 100 },
    drag: null,
    preview: {},
    busy: false,
    holdTokenPosition: vi.fn(),
    $emit: vi.fn(),
  };
  Object.entries(tokenDragMethods).forEach(([name, method]) => {
    vm[name] = method.bind(vm);
  });
  return vm;
};

const target = () => ({
  setPointerCapture: vi.fn(),
  releasePointerCapture: vi.fn(),
  hasPointerCapture: vi.fn(() => true),
});

afterEach(() => vi.restoreAllMocks());

describe("token pointer drag", () => {
  it("tracks the pointer and emits scaled coordinates snapped by token center", () => {
    const vm = context(2);
    const token = {
      id: 7,
      x: 100,
      y: 80,
      width: 100,
      height: 100,
      locked: false,
      capabilities: { canControl: true },
    };
    const captureTarget = target();
    vm.startDrag(
      {
        pointerId: 4,
        button: 0,
        clientX: 20,
        clientY: 30,
        currentTarget: captureTarget,
        preventDefault: vi.fn(),
      },
      token,
    );

    window.dispatchEvent(
      pointer("pointermove", { pointerId: 4, clientX: 100, clientY: 70 }),
    );
    expect(vm.preview[7]).toEqual({ x: 100, y: 100 });

    window.dispatchEvent(
      pointer("pointerup", { pointerId: 4, clientX: 120, clientY: 90 }),
    );
    expect(vm.$emit).toHaveBeenCalledWith("move", {
      token,
      x: 200,
      y: 100,
    });
    expect(vm.holdTokenPosition).toHaveBeenCalledWith(token, {
      x: 200,
      y: 100,
    });
    expect(captureTarget.releasePointerCapture).toHaveBeenCalledWith(4);
    expect(vm.drag).toBeNull();
  });

  it("does not drag a token without control permission", () => {
    const vm = context();
    const token = {
      id: 8,
      x: 0,
      y: 0,
      locked: false,
      capabilities: { canControl: false },
    };

    vm.startDrag(
      {
        pointerId: 1,
        button: 0,
        currentTarget: target(),
        preventDefault: vi.fn(),
      },
      token,
    );

    expect(vm.$emit).not.toHaveBeenCalled();
    expect(vm.drag).toBeNull();
  });
});

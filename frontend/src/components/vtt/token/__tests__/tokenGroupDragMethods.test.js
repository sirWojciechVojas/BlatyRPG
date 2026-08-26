import { afterEach, describe, expect, it, vi } from "vitest";
import { tokenDragMethods } from "../tokenDragMethods";

const pointer = (values) => {
  const event = new Event("pointerup");
  Object.entries(values).forEach(([key, value]) =>
    Object.defineProperty(event, key, { value }),
  );
  return event;
};

const token = (id, x, y, points) => ({
  id,
  x,
  y,
  width: 100,
  height: 100,
  movementRange: points,
  movementSpent: 0,
  movementPoints: points,
  capabilities: { canControl: true, canManage: false },
});

const context = (tokens) => {
  const vm = {
    scale: 1,
    scene: { gridType: "square", gridSize: 100 },
    tokens,
    effectiveSelectedIds: tokens.map(({ id }) => id),
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

const start = (vm, anchor, pointerId) =>
  vm.startDrag(
    {
      pointerId,
      button: 0,
      clientX: 0,
      clientY: 0,
      currentTarget: {
        setPointerCapture: vi.fn(),
        releasePointerCapture: vi.fn(),
        hasPointerCapture: vi.fn(() => true),
      },
      preventDefault: vi.fn(),
    },
    anchor,
  );

afterEach(() => vi.restoreAllMocks());

describe("multi-token pointer drag", () => {
  it("moves every selected token together with independent movement costs", () => {
    const tokens = [token(11, 0, 0, 6), token(12, 200, 100, 4)];
    const vm = context(tokens);
    start(vm, tokens[0], 8);
    window.dispatchEvent(pointer({ pointerId: 8, clientX: 200, clientY: 0 }));

    expect(vm.holdTokenPosition).toHaveBeenCalledTimes(2);
    expect(vm.$emit).toHaveBeenCalledWith("move-group", {
      moves: [
        expect.objectContaining({ token: tokens[0], x: 200, y: 0, cost: 2 }),
        expect.objectContaining({ token: tokens[1], x: 400, y: 100, cost: 2 }),
      ],
    });
  });

  it("blocks the complete selection when one token cannot afford the route", () => {
    const tokens = [token(21, 0, 0, 6), token(22, 100, 0, 1)];
    const vm = context(tokens);
    start(vm, tokens[0], 9);
    window.dispatchEvent(pointer({ pointerId: 9, clientX: 200, clientY: 0 }));

    expect(vm.holdTokenPosition).not.toHaveBeenCalled();
    expect(vm.$emit).toHaveBeenCalledWith("movement-depleted", {
      group: true,
      tokens: [tokens[1]],
    });
    expect(vm.$emit).not.toHaveBeenCalledWith("move-group", expect.anything());
  });
});

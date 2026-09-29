import { describe, expect, it, vi } from "vitest";
import { lightLayerEditorMethods } from "../lightLayerEditorMethods";

describe("light layer click interaction", () => {
  it("starts creation with the first LMB and finishes with the second", () => {
    const context = {
      busy: false,
      drag: null,
      contextMenu: {},
      startCreate: vi.fn(),
      finishCreate: vi.fn(),
    };
    const event = { button: 0 };

    lightLayerEditorMethods.canvasPointerDown.call(context, event);
    expect(context.startCreate).toHaveBeenCalledWith(event);
    expect(context.contextMenu).toBeNull();

    context.drag = { type: "create" };
    lightLayerEditorMethods.canvasPointerDown.call(context, event);
    expect(context.finishCreate).toHaveBeenCalledWith(event);
  });

  it("suppresses the context menu and cancels an active draft", () => {
    const cancel = vi.fn();
    const context = { drag: { type: "create" }, cancel };
    const event = { pointerId: 1 };

    lightLayerEditorMethods.openContextMenu.call(context, event);
    expect(cancel).toHaveBeenCalledWith(event);
    expect(context.contextMenu).toBeUndefined();
  });

  it("finishes a draft when the second click lands on another light", () => {
    const finishCreate = vi.fn();
    const context = {
      busy: false,
      drag: { type: "create" },
      finishCreate,
    };
    const event = { button: 0 };

    lightLayerEditorMethods.startMove.call(context, event, { id: 8 });
    expect(finishCreate).toHaveBeenCalledWith(event);
  });
});

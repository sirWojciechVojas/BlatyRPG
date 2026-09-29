import { afterEach, describe, expect, it, vi } from "vitest";
import { beginActorDrag, endActorDrag } from "@/lib/vtt/actorDragSession";
import {
  beginTokenTemplateDrag,
  endTokenTemplateDrag,
  TOKEN_TEMPLATE_MIME,
} from "@/lib/vtt/tokenTemplateDragSession";
import { sceneCanvasDropMethods } from "../sceneCanvasDropMethods";

const context = () => {
  const vm = {
    scene: { id: 2 },
    canCreateToken: true,
    canManageTiles: false,
    camera: { x: 10, y: 20, scale: 2 },
    mapDimensions: { padding: 5 },
    actorDropPreview: null,
    $emit: vi.fn(),
    $refs: {
      viewport: {
        getBoundingClientRect: () => ({ left: 30, top: 40 }),
        contains: () => false,
      },
    },
  };
  Object.entries(sceneCanvasDropMethods).forEach(([name, method]) => {
    vm[name] = method.bind(vm);
  });
  return vm;
};

afterEach(() => {
  endActorDrag();
  endTokenTemplateDrag();
});

describe("scene actor drop preview", () => {
  it("tracks the active actor in scene coordinates and creates its token", () => {
    const transfer = { setData: vi.fn(), setDragImage: vi.fn() };
    beginActorDrag(transfer, { id: 7, name: "Alda", imageUrl: "/alda.webp" });
    const vm = context();
    const dragEvent = {
      clientX: 250,
      clientY: 180,
      dataTransfer: { ...transfer, dropEffect: "none", getData: () => "" },
    };

    vm.previewDrop(dragEvent);
    expect(vm.actorDropPreview).toMatchObject({
      actor: { id: 7, name: "Alda" },
      x: 100,
      y: 55,
    });

    vm.dropContent(dragEvent);
    expect(vm.$emit).toHaveBeenCalledWith("token-create", {
      actor: { id: 7, name: "Alda", imageUrl: "/alda.webp" },
      x: 100,
      y: 55,
    });
    expect(vm.actorDropPreview).toBeNull();
  });

  it("keeps token templates on a distinct drag channel", () => {
    const transfer = { setData: vi.fn(), setDragImage: vi.fn() };
    beginTokenTemplateDrag(transfer, {
      id: 19,
      name: "Library orc",
      imageUrl: "/api/campaigns/7/token-template-assets/4/file",
      widthCells: 2,
      heightCells: 1.5,
    });
    const vm = context();
    const dragEvent = {
      clientX: 250,
      clientY: 180,
      dataTransfer: { ...transfer, dropEffect: "none", getData: () => "" },
    };

    vm.previewDrop(dragEvent);
    expect(vm.actorDropPreview.template).toMatchObject({
      id: 19,
      widthCells: 2,
      heightCells: 1.5,
    });
    vm.dropContent(dragEvent);

    expect(transfer.setData).toHaveBeenCalledWith(
      TOKEN_TEMPLATE_MIME,
      expect.any(String),
    );
    expect(vm.$emit).toHaveBeenCalledWith(
      "token-template-create",
      expect.objectContaining({
        template: expect.objectContaining({ id: 19 }),
      }),
    );
    expect(vm.$emit).not.toHaveBeenCalledWith(
      "token-create",
      expect.anything(),
    );
  });
});

import { afterEach, describe, expect, it, vi } from "vitest";
import { beginActorDrag, endActorDrag } from "@/lib/vtt/actorDragSession";
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

afterEach(endActorDrag);

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
});

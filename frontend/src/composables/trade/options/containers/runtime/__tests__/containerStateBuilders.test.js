import { describe, expect, it, vi } from "vitest";
import { buildServerContainerState } from "../serverContainerStateBuilder";

const createVm = () => ({
  actors: [{ id: 7, ownerCode: "BG1", name: "Heinz" }],
  isTemplateStackable: vi.fn(() => true),
  containerInstanceMeta: {},
  containerState: {},
  containerUndoStack: ["old"],
});

describe("buildServerContainerState", () => {
  it("normalizes server containers and instance metadata", () => {
    const vm = createVm();
    const handled = buildServerContainerState(
      vm,
      {},
      {
        templates: [{ ID: 10, NAME: "Miecz", ITEM_CLASS: "WEAPON", PRIZE: 12 }],
        shops: [{ id: 3, name: "Kuźnia" }],
        inventoryItems: [{ ID: 21, INV_ID: 10, PERSONAL_DESC: "Pamiątka" }],
        trashItems: [],
        serverState: {
          containers: [
            {
              id: 5,
              container_type: "CHARACTER",
              owner_code: "BG1",
              name: "Ekwipunek",
              capacity: 16,
            },
          ],
          itemInstances: [{ id: 21, template_id: 10, data_override: {} }],
          templateRows: [{ container_id: 5, template_id: 10, quantity: 2 }],
          instanceRows: [{ container_id: 5, instance_id: 21 }],
        },
      },
    );

    expect(handled).toBe(true);
    expect(vm.containerState.containers[0]).toMatchObject({
      id: 5,
      type: "CHARACTER",
      ownerCode: "BG1",
      actorId: 7,
      capacity: 16,
    });
    expect(vm.containerInstanceMeta[21].PERSONAL_DESC).toBe("Pamiątka");
    expect(vm.containerState.containerTemplateItems[0].quantity).toBe(2);
    expect(vm.containerUndoStack).toEqual([]);
  });

  it("lets the demo builder handle an empty server response", () => {
    const vm = createVm();
    expect(
      buildServerContainerState(
        vm,
        {},
        {
          templates: [],
          shops: [],
          inventoryItems: [],
          trashItems: [],
          serverState: { containers: [] },
        },
      ),
    ).toBe(false);
    expect(vm.containerUndoStack).toEqual(["old"]);
  });
});

import { beforeEach, describe, expect, it, vi } from "vitest";

const api = vi.hoisted(() => ({
  create: vi.fn(),
  listGames: vi.fn(),
}));

vi.mock("@/lib/character/characterApiClient", () => ({
  characterApiClient: { create: api.create },
}));
vi.mock("@/lib/character/characterCatalogApiClient", () => ({
  characterCatalogApiClient: { listGames: api.listGames },
}));

import { tableCharacterCreationMethods } from "../tableCharacterCreationMethods";

const context = () => {
  const vm = {
    campaignId: 5,
    canCreate: false,
    games: [],
    showingCreate: false,
    creating: false,
    catalogLoading: false,
    createError: "",
    notice: "",
    selectedId: null,
    selectedCharacter: null,
    listRequestSequence: 3,
    createRequestSequence: 0,
    replaceCharacter: vi.fn(),
    $emit: vi.fn(),
    $t: (key) => key,
  };
  Object.entries(tableCharacterCreationMethods).forEach(([name, method]) => {
    vm[name] = method.bind(vm);
  });
  return vm;
};

describe("table character creation", () => {
  beforeEach(() => vi.clearAllMocks());

  it("does not load creation data or submit without the GM capability", async () => {
    const vm = context();
    vm.configureCharacterCreation({ capabilities: { canCreate: false } }, 3);
    await vm.openCreate();
    await vm.createCharacter({ name: "Blocked" });

    expect(vm.showingCreate).toBe(false);
    expect(api.listGames).not.toHaveBeenCalled();
    expect(api.create).not.toHaveBeenCalled();
  });

  it("loads the catalog and creates one campaign character", async () => {
    const game = { systemId: 1, universeId: 2 };
    api.listGames.mockResolvedValue([game]);
    api.create.mockResolvedValue({ id: 9, name: "Mira" });
    const vm = context();

    vm.configureCharacterCreation({ capabilities: { canCreate: true } }, 3);
    await vi.waitFor(() => expect(vm.games).toEqual([game]));
    const draft = { name: "Mira", systemId: 1, universeId: 2 };
    const first = vm.createCharacter(draft);
    const duplicate = vm.createCharacter(draft);
    await Promise.all([first, duplicate]);

    expect(api.create).toHaveBeenCalledTimes(1);
    expect(api.create).toHaveBeenCalledWith(5, draft);
    expect(vm.replaceCharacter).toHaveBeenCalledWith({ id: 9, name: "Mira" });
    expect(vm.selectedId).toBe(9);
    expect(vm.showingCreate).toBe(false);
    expect(vm.$emit).toHaveBeenCalledWith("changed", { id: 9, name: "Mira" });
  });
});

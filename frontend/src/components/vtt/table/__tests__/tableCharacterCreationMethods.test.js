import { beforeEach, describe, expect, it, vi } from "vitest";

const api = vi.hoisted(() => ({
  create: vi.fn(),
}));

vi.mock("@/lib/character/characterApiClient", () => ({
  characterApiClient: { create: api.create },
}));
import { tableCharacterCreationMethods } from "../tableCharacterCreationMethods";

const context = () => {
  const vm = {
    campaignId: 5,
    campaign: { id: 5, systemId: 1, universeId: 2 },
    canCreate: false,
    showingCreate: false,
    creating: false,
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

  it("does not open or submit without the GM capability", async () => {
    const vm = context();
    vm.configureCharacterCreation({ capabilities: { canCreate: false } });
    await vm.openCreate();
    await vm.createCharacter({ name: "Blocked" });

    expect(vm.showingCreate).toBe(false);
    expect(api.create).not.toHaveBeenCalled();
  });

  it("inherits the campaign game and creates one character", async () => {
    api.create.mockResolvedValue({ id: 9, name: "Mira" });
    const vm = context();

    vm.configureCharacterCreation({ capabilities: { canCreate: true } });
    vm.openCreate();
    const draft = { name: "Mira", data: { details: {} } };
    const first = vm.createCharacter(draft);
    const duplicate = vm.createCharacter(draft);
    await Promise.all([first, duplicate]);

    expect(api.create).toHaveBeenCalledTimes(1);
    expect(api.create).toHaveBeenCalledWith(5, {
      ...draft,
      systemId: 1,
      universeId: 2,
    });
    expect(vm.replaceCharacter).toHaveBeenCalledWith({ id: 9, name: "Mira" });
    expect(vm.selectedId).toBe(9);
    expect(vm.showingCreate).toBe(false);
    expect(vm.$emit).toHaveBeenCalledWith("changed", { id: 9, name: "Mira" });
  });

  it("reports a campaign configuration error without calling the API", async () => {
    const vm = context();
    vm.canCreate = true;
    vm.campaign.universeId = null;

    await vm.createCharacter({ name: "Mira" });

    expect(vm.createError).toBe("characters.errors.campaign_game");
    expect(api.create).not.toHaveBeenCalled();
  });
});

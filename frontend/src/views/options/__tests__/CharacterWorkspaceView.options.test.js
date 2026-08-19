import { beforeEach, describe, expect, it, vi } from "vitest";

const api = vi.hoisted(() => ({
  create: vi.fn(),
  update: vi.fn(),
  delete: vi.fn(),
}));

vi.mock("@/components/characters/CharacterCreateDialog.vue", () => ({
  default: {},
}));
vi.mock("@/components/characters/CharacterList.vue", () => ({ default: {} }));
vi.mock("@/components/characters/CharacterSheetEditor.vue", () => ({
  default: {},
}));
vi.mock("@/lib/character/characterApiClient", () => ({
  characterApiClient: {
    list: vi.fn(),
    get: vi.fn(),
    create: api.create,
    update: api.update,
    delete: api.delete,
  },
}));
vi.mock("@/lib/character/characterCatalogApiClient", () => ({
  characterCatalogApiClient: { listGames: vi.fn() },
}));

import options from "@/views/options/CharacterWorkspaceView.options";

describe("CharacterWorkspaceView save synchronization", () => {
  beforeEach(() => vi.clearAllMocks());

  it("does not apply a save response after the campaign changes", async () => {
    let resolveUpdate;
    api.update.mockReturnValue(
      new Promise((resolve) => {
        resolveUpdate = resolve;
      }),
    );
    const context = {
      ...options.data(),
      campaignId: 1,
      selectedId: 7,
      $t: (key) => key,
      replaceCharacter: vi.fn(),
    };
    context.isMutating = options.methods.isMutating.bind(context);
    context.isCurrentSave = options.methods.isCurrentSave.bind(context);

    const save = options.methods.saveCharacter.call(context, {
      name: "Before route change",
      data: {},
    });
    context.campaignId = 2;
    options.methods.resetForCampaign.call(context);
    resolveUpdate({ id: 7, name: "Stale response" });
    await save;

    expect(context.replaceCharacter).not.toHaveBeenCalled();
    expect(context.selectedCharacter).toBeNull();
    expect(context.saving).toBe(false);
  });

  it("submits a save only once while its request is pending", async () => {
    let resolveUpdate;
    api.update.mockReturnValue(
      new Promise((resolve) => {
        resolveUpdate = resolve;
      }),
    );
    const context = {
      ...options.data(),
      campaignId: 1,
      selectedId: 7,
      $t: (key) => key,
      replaceCharacter: vi.fn(),
    };
    context.isMutating = options.methods.isMutating.bind(context);
    context.isCurrentSave = options.methods.isCurrentSave.bind(context);

    const draft = { name: "Single save", data: {} };
    const first = options.methods.saveCharacter.call(context, draft);
    const duplicate = options.methods.saveCharacter.call(context, draft);

    expect(api.update).toHaveBeenCalledTimes(1);
    resolveUpdate({ id: 7, name: "Single save" });
    await Promise.all([first, duplicate]);
    expect(context.saving).toBe(false);
  });

  it("submits character creation only once while pending", async () => {
    let resolveCreate;
    api.create.mockReturnValue(
      new Promise((resolve) => {
        resolveCreate = resolve;
      }),
    );
    const context = {
      ...options.data(),
      campaignId: 1,
      showingCreate: true,
      $t: (key) => key,
      replaceCharacter: vi.fn(),
    };
    context.isMutating = options.methods.isMutating.bind(context);
    context.isCurrentCreate = options.methods.isCurrentCreate.bind(context);

    const draft = { name: "Single create", systemId: 1, universeId: 2 };
    const first = options.methods.createCharacter.call(context, draft);
    const duplicate = options.methods.createCharacter.call(context, draft);

    expect(api.create).toHaveBeenCalledTimes(1);
    resolveCreate({ id: 8, name: "Single create" });
    await Promise.all([first, duplicate]);
    expect(context.creating).toBe(false);
    expect(context.selectedId).toBe(8);
  });

  it("does not start create while another mutation is pending", async () => {
    const context = {
      ...options.data(),
      campaignId: 1,
      saving: true,
    };
    context.isMutating = options.methods.isMutating.bind(context);

    await options.methods.createCharacter.call(context, {
      name: "Blocked",
      systemId: 1,
      universeId: 2,
    });

    expect(api.create).not.toHaveBeenCalled();
  });
});

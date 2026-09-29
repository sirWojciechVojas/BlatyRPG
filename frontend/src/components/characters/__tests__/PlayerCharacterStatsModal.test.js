import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it, vi } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/characters/PlayerCharacterStatsModal.vue",
);
const componentSource = readFileSync(componentPath, "utf8");
const { descriptor } = parse(componentSource, { filename: componentPath });

const deferred = () => {
  let resolvePromise;
  const promise = new Promise((resolveValue) => {
    resolvePromise = resolveValue;
  });
  return { promise, resolve: resolvePromise };
};

const componentOptions = (
  characterApiClient,
  shopApiClient = {},
  shopOwnerCodeForCharacter = () => null,
) => {
  const executable = descriptor.script.content
    .replace(/^import .*?;\n/gmu, "")
    .replace("export default {", "return {");
  return new Function(
    "CharacterStatsDetailsPanel",
    "CharacterStatsEquipmentPanel",
    "CharacterStatsTraitsPanel",
    "characterApiClient",
    "characterErrorKey",
    "createPlayerCharacterStatsModel",
    "resolveCharacterPortrait",
    "shopApiClient",
    "shopOwnerCodeForCharacter",
    executable,
  )(
    {},
    {},
    {},
    characterApiClient,
    () => "characters.errors.load",
    () => ({}),
    () => "/avatar.png",
    shopApiClient,
    shopOwnerCodeForCharacter,
  );
};

const createContext = (options, overrides = {}) => ({
  campaignId: 4,
  characterId: 9,
  character: null,
  loading: false,
  loadError: "",
  requestSequence: 0,
  inventoryItems: [],
  templateItems: [],
  inventoryOwnerCode: "",
  canEditInventory: false,
  inventorySaving: false,
  weaponSetSaving: false,
  inventoryError: "",
  loadedCampaignId: null,
  $t: (key) => key,
  $emit: vi.fn(),
  isCurrentRequest: options.methods.isCurrentRequest,
  loadCharacter: options.methods.loadCharacter,
  applyInventoryMoves: options.methods.applyInventoryMoves,
  ...overrides,
});

describe("PlayerCharacterStatsModal", () => {
  it("loads only the character selected in the player HUD", async () => {
    const api = {
      get: vi.fn().mockResolvedValue({ id: 9, name: "Alaric" }),
    };
    const shopApi = {
      getAccessOptions: vi
        .fn()
        .mockResolvedValue({ characters: [], modes: { player: true } }),
      bootstrap: vi.fn().mockResolvedValue({
        inventoryItems: [{ ID: 4, SLOT: "head", INV_ID: 12 }],
        templateItems: [{ ID: 12, CHARGE: 7 }],
      }),
    };
    const options = componentOptions(api, shopApi, () => "CHAR_9");
    const context = createContext(options);

    await options.methods.loadCharacter.call(context);

    expect(api.get).toHaveBeenCalledWith(4, 9);
    expect(context.character).toEqual({ id: 9, name: "Alaric" });
    expect(shopApi.bootstrap).toHaveBeenCalledWith({
      campaignId: 4,
      ownerCode: "CHAR_9",
      viewMode: "character",
    });
    expect(context.inventoryItems).toEqual([
      { ID: 4, SLOT: "head", INV_ID: 12 },
    ]);
    expect(context.templateItems).toEqual([{ ID: 12, CHARGE: 7 }]);
    expect(context.loading).toBe(false);
  });

  it("ignores a late response after the campaign and selected character change", async () => {
    const first = deferred();
    const second = deferred();
    const api = {
      get: vi
        .fn()
        .mockReturnValueOnce(first.promise)
        .mockReturnValueOnce(second.promise),
    };
    const options = componentOptions(api, {
      getAccessOptions: vi.fn().mockResolvedValue({ characters: [] }),
      bootstrap: vi.fn(),
    });
    const context = createContext(options);

    const oldRequest = options.methods.loadCharacter.call(context);
    context.campaignId = 5;
    context.characterId = 12;
    const currentRequest = options.methods.loadCharacter.call(context);

    second.resolve({ id: 12, name: "Current hero" });
    await currentRequest;
    first.resolve({ id: 9, name: "Stale hero" });
    await oldRequest;

    expect(context.character).toEqual({ id: 12, name: "Current hero" });
    expect(context.character.name).not.toBe("Stale hero");
  });

  it("does not reload an already displayed character", async () => {
    const api = { get: vi.fn() };
    const options = componentOptions(api, { getAccessOptions: vi.fn() });
    const context = createContext(options, {
      character: { id: 9, name: "Alaric" },
      loadedCampaignId: 4,
    });

    await options.methods.loadCharacter.call(context);

    expect(api.get).not.toHaveBeenCalled();
    expect(context.loading).toBe(false);
  });

  it("persists a Bountify slot move only for the selected character owner", async () => {
    const shopApi = {
      updateItemInstance: vi.fn().mockResolvedValue({ ok: true }),
    };
    const options = componentOptions({ get: vi.fn() }, shopApi);
    const context = createContext(options, {
      inventoryOwnerCode: "CHAR_9",
      canEditInventory: true,
      inventoryItems: [
        {
          ID: 41,
          SLOT: "0|0",
          ITEM_PLACE: "0|0",
          IMG_CLASS: "v0423",
          INSTANCE_META: { SLOT: "0|0", ITEM_PLACE: "0|0" },
        },
      ],
      moveInventoryItems: options.methods.moveInventoryItems,
      loadCharacter: vi.fn(),
    });

    await options.methods.moveInventoryItems.call(context, [
      { item: { ID: 41 }, slot: "handy1" },
    ]);

    expect(shopApi.updateItemInstance).toHaveBeenCalledWith(
      { campaignId: 4, ownerCode: "CHAR_9" },
      41,
      { slot: "handy1", itemPlace: "handy1" },
    );
    expect(context.inventoryItems).toEqual([
      expect.objectContaining({
        SLOT: "handy1",
        ITEM_PLACE: "handy1",
        IMG_CLASS: "v0423",
        INSTANCE_META: expect.objectContaining({
          SLOT: "handy1",
          ITEM_PLACE: "handy1",
        }),
      }),
    ]);
    expect(context.loadCharacter).not.toHaveBeenCalled();
  });

  it("persists the selected weapon set in character inventory data", async () => {
    const updatedCharacter = {
      id: 9,
      name: "Alaric",
      revision: 3,
      data: { inventory: { activeWeaponSet: 2 } },
    };
    const api = {
      get: vi.fn(),
      update: vi.fn().mockResolvedValue(updatedCharacter),
    };
    const options = componentOptions(api);
    const context = createContext(options, {
      character: {
        id: 9,
        name: "Alaric",
        avatarUrl: "/alaric.png",
        revision: 2,
        updatedAt: "2026-09-04T12:00:00Z",
        data: { notes: "keep", inventory: { activeWeaponSet: 1 } },
      },
      canEditInventory: true,
      activeWeaponSet: 1,
    });

    await options.methods.activateWeaponSet.call(context, 2);

    expect(api.update).toHaveBeenCalledWith(
      4,
      9,
      expect.objectContaining({
        data: {
          notes: "keep",
          inventory: { activeWeaponSet: 2 },
        },
      }),
    );
    expect(context.character).toEqual(updatedCharacter);
    expect(context.weaponSetSaving).toBe(false);
  });

  it("renders the original three-panel characterStats composition", () => {
    expect(descriptor.template.content).toContain('id="characterStats"');
    expect(descriptor.template.content).toContain(
      "CharacterStatsEquipmentPanel",
    );
    expect(descriptor.template.content).toContain("CharacterStatsTraitsPanel");
    expect(descriptor.template.content).toContain("CharacterStatsDetailsPanel");
    expect(descriptor.template.content).not.toContain("CharacterList");
  });
});

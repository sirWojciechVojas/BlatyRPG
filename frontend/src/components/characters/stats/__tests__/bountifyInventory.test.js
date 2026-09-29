import { describe, expect, it } from "vitest";
import {
  canPlaceItem,
  createInventoryPlacementPreview,
  createInventoryLayout,
  createItemPrimaryAction,
  createMovePlan,
  inventoryEncumbranceGroups,
  isTwoHanded,
  itemVisualTier,
} from "../bountifyInventory";

const item = (id, slot, genre = "handy", extra = {}) => ({
  ID: id,
  NAME: `Item ${id}`,
  SLOT: slot,
  ITEM_GENRE: genre,
  QUANTITY: 1,
  ...extra,
});

describe("bountifyInventory", () => {
  it("keeps legacy named slots and places unassigned items deterministically", () => {
    const layout = createInventoryLayout([
      item(4, "PLECY"),
      item(2, "head", "head"),
      item(3, "0|1"),
      item(1, "0|0"),
    ]);

    expect(layout.right.find((cell) => cell.slot === "head").item.ID).toBe(2);
    expect(layout.personal[0].item.ID).toBe(1);
    expect(layout.personal[1].item.ID).toBe(4);
    expect(layout.personal[10].item.ID).toBe(3);
  });

  it("selects the matching frame tile for each inventory position", () => {
    const layout = createInventoryLayout([]);

    expect(layout.personal[0].frame).toBe("top-left");
    expect(layout.personal[9].frame).toBe("top-right");
    expect(layout.personal[10].frame).toBe("middle-left");
    expect(layout.personal[29].frame).toBe("bottom-right");
    expect(layout.ground[0].frame).toBe("top-left");
    expect(layout.ground[7].frame).toBe("bottom-right");
    expect(layout.right.every((cell) => cell.frame === "single")).toBe(true);
    expect(layout.handy.every((cell) => cell.frame === "single")).toBe(true);
  });

  it("keeps the redesigned inventory groups at their fixed capacities", () => {
    const layout = createInventoryLayout([]);

    expect(layout.right).toHaveLength(8);
    expect(layout.left).toHaveLength(8);
    expect(
      layout.handy.filter(({ slot }) => slot.startsWith("quiver")),
    ).toHaveLength(3);
    expect(
      layout.handy.filter(({ slot }) => slot.startsWith("handy")),
    ).toHaveLength(3);
    expect(layout.personal).toHaveLength(30);
    expect(layout.ground).toHaveLength(8);
  });

  it("separates equipped and backpack items without counting ground items", () => {
    const layout = createInventoryLayout([
      item(1, "head", "head"),
      item(2, "quiver1", "quiver"),
      item(3, "0|0"),
      item(4, "ground:0|0"),
    ]);

    const groups = inventoryEncumbranceGroups(layout);

    expect(groups.equipment.map(({ ID }) => ID)).toEqual([1, 2]);
    expect(groups.backpack.map(({ ID }) => ID)).toEqual([3]);
  });

  it("only accepts an item in an equipment slot with the matching genre", () => {
    expect(canPlaceItem(item(1, "0|0", "head"), "head")).toEqual({ ok: true });
    expect(canPlaceItem(item(1, "0|0", "handy"), "head")).toEqual({
      ok: false,
      code: "wrong_slot",
    });
  });

  it("maps current shop weapon categories only to hand slots", () => {
    const rangedWeapon = item(1, "0|0", "RANGED", {
      ITEM_CLASS: "WEAPON",
      WEAPON: { TYPE: "ranged" },
    });

    expect(canPlaceItem(rangedWeapon, "armR1")).toEqual({ ok: true });
    expect(canPlaceItem(rangedWeapon, "armL2")).toEqual({ ok: true });
    expect(canPlaceItem(rangedWeapon, "armor")).toEqual({
      ok: false,
      code: "wrong_slot",
    });

    const malformedWeapon = {
      ...rangedWeapon,
      ITEM_GENRE: "ARMOR",
    };
    expect(canPlaceItem(malformedWeapon, "armR1")).toEqual({ ok: true });
    expect(canPlaceItem(malformedWeapon, "armor")).toEqual({
      ok: false,
      code: "wrong_slot",
    });
  });

  it("maps bolts to quiver slots instead of handy or hand slots", () => {
    const legacyBolts = item(1, "0|0", "handy", {
      NAME: "Bełty",
      ITEM_CLASS: "WEAPON",
    });
    const currentBolts = item(2, "0|1", "RANGED", {
      NAME: "Crossbow bolts",
      ITEM_CLASS: "WEAPON",
    });

    [legacyBolts, currentBolts].forEach((bolts) => {
      expect(canPlaceItem(bolts, "quiver1")).toEqual({ ok: true });
      expect(canPlaceItem(bolts, "quiver3")).toEqual({ ok: true });
      expect(canPlaceItem(bolts, "handy1")).toEqual({
        ok: false,
        code: "wrong_slot",
      });
      expect(canPlaceItem(bolts, "armR1")).toEqual({
        ok: false,
        code: "wrong_slot",
      });
    });
  });

  it("maps current body armour and clothing categories to the armour slot", () => {
    const bodyArmor = item(1, "0|0", "BODY", {
      ITEM_CLASS: "ARMOR",
    });
    const shirt = item(2, "0|1", "BODY", { ITEM_CLASS: "CLOTH" });

    expect(canPlaceItem(bodyArmor, "armor")).toEqual({ ok: true });
    expect(canPlaceItem(bodyArmor, "armR1")).toEqual({
      ok: false,
      code: "wrong_slot",
    });
    expect(canPlaceItem(shirt, "armor")).toEqual({ ok: true });
  });

  it("previews a weapon on hands and body armour on armor without crossover", () => {
    const weaponLayout = createInventoryLayout([
      item(1, "0|0", "RANGED", { ITEM_CLASS: "WEAPON" }),
    ]);
    const weaponPreview = createInventoryPlacementPreview(weaponLayout, "0|0");

    expect(weaponPreview.slots.armR1.target).toBe(true);
    expect(weaponPreview.slots.armL2.target).toBe(true);
    expect(weaponPreview.slots.armor.target).toBe(false);
    expect(weaponPreview.slots.armor.dimmed).toBe(true);

    const armorLayout = createInventoryLayout([
      item(2, "0|0", "BODY", { ITEM_CLASS: "ARMOR" }),
    ]);
    const armorPreview = createInventoryPlacementPreview(armorLayout, "0|0");

    expect(armorPreview.slots.armor.target).toBe(true);
    expect(armorPreview.slots.armR1.target).toBe(false);
    expect(armorPreview.slots.armR1.dimmed).toBe(true);
  });

  it("blocks a second hand when the opposite hand contains a two-handed weapon", () => {
    const twoHanded = item(1, "armR1", "arms", {
      WEAPON: { HANDED: "2H" },
    });
    expect(isTwoHanded(twoHanded)).toBe(true);
    expect(
      canPlaceItem(item(2, "0|0", "arms"), "armL1", {
        armR1: twoHanded,
      }),
    ).toEqual({ ok: false, code: "two_handed_block" });
  });

  it("recognizes the original WFRP two-handed weapon label", () => {
    const twoHanded = item(1, "armR1", "arms", {
      WEAPON: { HANDED: "broń dwuręczna" },
    });

    expect(isTwoHanded(twoHanded)).toBe(true);
    expect(
      canPlaceItem(item(2, "0|0", "arms"), "armL1", {
        armR1: twoHanded,
      }),
    ).toEqual({ ok: false, code: "two_handed_block" });
  });

  it("treats a legacy crossbow without HANDED metadata as two-handed", () => {
    expect(
      isTwoHanded({
        NAME: "Kusza samopowtarzalna",
        ITEM_CLASS: "WEAPON",
        ITEM_GENRE: "arms",
      }),
    ).toBe(true);
    expect(
      isTwoHanded({
        NAME: "Kusza pistoletowa",
        ITEM_CLASS: "WEAPON",
        ITEM_GENRE: "arms",
      }),
    ).toBe(false);
  });

  it("disables the opposite hand while a two-handed weapon is equipped", () => {
    const equippedTwoHanded = item(1, "armR1", "arms", {
      WEAPON: { HANDED: "2H" },
    });
    const layout = createInventoryLayout([
      equippedTwoHanded,
      item(2, "0|0", "head"),
    ]);

    const idle = createInventoryPlacementPreview(layout);
    expect(idle.slots.armL1).toEqual(
      expect.objectContaining({
        disabled: true,
        dimmed: false,
        blockedByTwoHanded: true,
        blockedItem: equippedTwoHanded,
      }),
    );
    expect(idle.slots.armL2.disabled).toBe(false);

    const secondPair = createInventoryPlacementPreview(
      createInventoryLayout([
        item(5, "armR2", "arms", { WEAPON: { HANDED: "2H" } }),
      ]),
    );
    expect(secondPair.slots.armL1.disabled).toBe(false);
    expect(secondPair.slots.armL2.disabled).toBe(true);

    const pickedUp = createInventoryPlacementPreview(layout, "0|0");
    expect(pickedUp.slots.head.target).toBe(true);
    expect(pickedUp.slots.armor.disabled).toBe(false);
    expect(pickedUp.slots.armor.dimmed).toBe(true);
    expect(pickedUp.slots["0|1"].target).toBe(false);
    expect(pickedUp.slots["0|1"].dimmed).toBe(false);
  });

  it("can move a two-handed weapon after releasing its source hand", () => {
    const layout = createInventoryLayout([
      item(1, "armR1", "arms", { WEAPON: { HANDED: "2H" } }),
    ]);

    expect(createMovePlan(layout, "armR1", "armL1").ok).toBe(true);
    expect(
      createInventoryPlacementPreview(layout, "armR1").slots.armL1,
    ).toEqual(
      expect.objectContaining({
        target: true,
        disabled: false,
        blockedByTwoHanded: false,
      }),
    );
  });

  it("does not swap another weapon into the hand blocked by a two-handed weapon", () => {
    const layout = createInventoryLayout([
      item(1, "armR1", "arms", { WEAPON: { HANDED: "2H" } }),
      item(2, "armL1", "arms"),
    ]);

    expect(createMovePlan(layout, "armR1", "armL1")).toEqual({
      ok: false,
      code: "two_handed_block",
    });
  });

  it("creates a two-way plan when dropping onto an occupied slot", () => {
    const layout = createInventoryLayout([
      item(1, "0|0", "handy"),
      item(2, "handy1", "handy"),
    ]);
    const plan = createMovePlan(layout, "0|0", "handy1");

    expect(plan.ok).toBe(true);
    expect(plan.moves).toEqual([
      { item: expect.objectContaining({ ID: 1 }), slot: "handy1" },
      { item: expect.objectContaining({ ID: 2 }), slot: "0|0" },
    ]);
  });

  it("equips body armour and clothing in the armour slot", () => {
    const armorLayout = createInventoryLayout([
      item(1, "0|0", "BODY", { ITEM_CLASS: "ARMOR" }),
    ]);
    const shirtLayout = createInventoryLayout([
      item(2, "0|0", "BODY", { ITEM_CLASS: "CLOTH" }),
    ]);

    expect(createItemPrimaryAction(armorLayout, "0|0")).toEqual(
      expect.objectContaining({
        ok: true,
        action: "equip",
        targetSlot: "armor",
      }),
    );
    expect(createItemPrimaryAction(shirtLayout, "0|0")).toEqual(
      expect.objectContaining({
        ok: true,
        action: "equip",
        targetSlot: "armor",
      }),
    );
  });

  it("chooses the type-specific quiver and quick-item destinations", () => {
    const layout = createInventoryLayout([
      item(1, "0|0", "RANGED", {
        NAME: "Bełty",
        ITEM_CLASS: "WEAPON",
      }),
      item(2, "1|0", "HEALING", { ITEM_CLASS: "ALCHEMY" }),
    ]);

    expect(createItemPrimaryAction(layout, "0|0")).toEqual(
      expect.objectContaining({
        ok: true,
        action: "quiver",
        targetSlot: "quiver1",
      }),
    );
    expect(createItemPrimaryAction(layout, "1|0")).toEqual(
      expect.objectContaining({
        ok: true,
        action: "ready",
        targetSlot: "handy1",
      }),
    );
  });

  it("puts equipped items back into the first free backpack slot", () => {
    const layout = createInventoryLayout([item(1, "head", "head")]);

    expect(createItemPrimaryAction(layout, "head")).toEqual(
      expect.objectContaining({ ok: true, action: "store", targetSlot: "0|0" }),
    );
  });

  it("picks an unsupported item up from the ground", () => {
    const layout = createInventoryLayout([
      item(1, "ground:0|0", "QUEST", { ITEM_CLASS: "QUEST" }),
    ]);

    expect(createItemPrimaryAction(layout, "ground:0|0")).toEqual(
      expect.objectContaining({
        ok: true,
        action: "pickup",
        targetSlot: "0|0",
      }),
    );
  });

  it("uses the original Bountify tiers from item metadata and attributes", () => {
    expect(itemVisualTier({ ITEM_CLASS: "MAGIC" })).toBe("magic");
    expect(itemVisualTier({ ATTRIBUTES: ["MAGICAL", "RARE"] })).toBe("rare");
    expect(itemVisualTier({ INSTANCE_META: { RARITY: "unique" } })).toBe(
      "unique",
    );
    expect(itemVisualTier({ ITEM_CLASS: "WEAPON" })).toBe("");
  });
});

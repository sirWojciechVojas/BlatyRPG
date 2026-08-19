import { describe, expect, it } from "vitest";
import {
  normalizeInventoryRecord,
  normalizeTemplateRecord,
  normalizeTrashRecord,
  normalizeTrashKind,
} from "@/lib/trade/normalizers";
import {
  validateInventoryRecord,
  validateTemplateRecord,
  validateTrashRecord,
} from "@/lib/trade/validators";
import { TRASH_KINDS } from "@/lib/trade/constants";
import {
  normalizeLegacyInventoryRecord,
  normalizeLegacyTemplateRecord,
} from "@/lib/trade/adapters";

describe("trade domain normalizers", () => {
  it("normalizes template record into canonical shape", () => {
    const record = normalizeTemplateRecord({
      ID: "12",
      NAME: "  Miecz  ",
      DESCRIPTION: "  Ostrze  ",
      ITEM_CLASS: "weapon",
      ITEM_ID: 44,
      ITEM_GENRE: " slashing ",
      IMG_CLASS: "V0042",
      PRIZE: "123",
      CHARGE: "7",
    });

    expect(record).toMatchObject({
      id: 12,
      name: "Miecz",
      description: "Ostrze",
      itemClass: "WEAPON",
      itemId: "44",
      itemGenre: "SLASHING",
      imgClass: "v0042",
      prize: 123,
      charge: 7,
    });
  });

  it("normalizes inventory and trash records with owner/kind defaults", () => {
    const inventory = normalizeInventoryRecord({
      ID: "7",
      INV_ID: "5",
      OWNER: "bg12",
      OWNER_OPT: "default",
      QUANTITY: "4",
      PERSONAL_COST: "55",
    });
    const trash = normalizeTrashRecord({
      ...inventory,
      OWNER: "trash",
      TRASH_KIND: "template",
      TRASH_SOURCE_ID: "7",
    });

    expect(inventory.ownerCode).toBe("DEFAULT");
    expect(inventory.ownerOpt).toBe("DEFAULT");
    expect(inventory.quantity).toBe(4);
    expect(trash.ownerCode).toBe("TRASH");
    expect(trash.ownerOpt).toBe("TRASH");
    expect(trash.trashKind).toBe(TRASH_KINDS.TEMPLATE);
    expect(trash.trashSourceId).toBe(7);
  });

  it("falls back to ITEM trash kind for unknown values", () => {
    expect(normalizeTrashKind("unknown_kind")).toBe(TRASH_KINDS.ITEM);
  });

  it("keeps camelCase as the domain shape while exposing legacy API aliases", () => {
    const template = normalizeLegacyTemplateRecord({
      ID: "12",
      NAME: "Miecz",
      PRIZE: "30",
    });
    const inventory = normalizeLegacyInventoryRecord({
      ID: "8",
      INV_ID: "12",
      OWNER: "PC-ALICE",
      OWNER_OPT: "PC-ALICE",
      QUANTITY: "2",
    });

    expect(template).toMatchObject({
      id: 12,
      name: "Miecz",
      prize: 30,
      ID: 12,
      NAME: "Miecz",
      PRIZE: 30,
    });
    expect(inventory).toMatchObject({
      id: 8,
      templateId: 12,
      ownerCode: "PC-ALICE",
      quantity: 2,
      ID: 8,
      INV_ID: 12,
      OWNER: "PC-ALICE",
    });
  });
});

describe("trade domain validators", () => {
  it("returns field errors for invalid template input", () => {
    const result = validateTemplateRecord({
      ID: 0,
      NAME: "",
      DESCRIPTION: "",
      ITEM_CLASS: "",
      PRIZE: -1,
      CHARGE: -5,
    });

    expect(result.valid).toBe(false);
    expect(result.errors).toHaveProperty("ID");
    expect(result.errors).toHaveProperty("NAME");
    expect(result.errors).toHaveProperty("DESCRIPTION");
    expect(result.errors).toHaveProperty("ITEM_CLASS");
    expect(result.errors).toHaveProperty("PRIZE");
    expect(result.errors).toHaveProperty("CHARGE");
  });

  it("returns field errors for invalid inventory input", () => {
    const result = validateInventoryRecord({
      ID: 0,
      INV_ID: -3,
      PERSONAL_COST: -1,
      QUANTITY: -2,
      PRIZE: -4,
      CHARGE: -6,
    });

    expect(result.valid).toBe(false);
    expect(result.errors).toHaveProperty("ID");
    expect(result.errors).toHaveProperty("INV_ID");
    expect(result.errors).toHaveProperty("PERSONAL_COST");
    expect(result.errors).toHaveProperty("QUANTITY");
    expect(result.errors).toHaveProperty("PRIZE");
    expect(result.errors).toHaveProperty("CHARGE");
  });

  it("validates trash kind alongside inventory fields", () => {
    const result = validateTrashRecord({
      ID: 1,
      INV_ID: 2,
      TRASH_KIND: "",
      OWNER: "TRASH",
      QUANTITY: 1,
      PERSONAL_COST: 0,
      PRIZE: 0,
      CHARGE: 0,
    });

    expect(result.valid).toBe(true);
    expect(result.record.trashKind).toBe(TRASH_KINDS.ITEM);
  });
});

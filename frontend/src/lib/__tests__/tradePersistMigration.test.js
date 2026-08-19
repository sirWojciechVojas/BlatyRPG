import { describe, expect, it } from "vitest";
import { TRADE_PERSIST_SCHEMA_VERSION } from "@/lib/trade/constants";
import {
  buildPersistedTradePayload,
  normalizePersistedTradePayload,
} from "@/lib/trade/persistMigration";

describe("trade persistence migration", () => {
  it("normalizes legacy payload without data loss", () => {
    const legacyPayload = {
      version: 1,
      savedAt: "2025-02-01T10:00:00.000Z",
      shops: [{ id: 1, name: "Test shop", shopEntries: [] }],
      templateItems: [
        {
          ID: "10",
          NAME: "Mlot",
          DESCRIPTION: "Opis",
          ITEM_CLASS: "TOOL",
          IMG_CLASS: "V0002",
          PRIZE: "50",
          CHARGE: "3",
        },
      ],
      inventoryItems: [
        {
          ID: "21",
          INV_ID: "10",
          OWNER_OPT: "DEFAULT",
          OWNER: "BG1",
          QUANTITY: "2",
          PERSONAL_COST: "45",
        },
      ],
      trashItems: [
        {
          ID: "31",
          INV_ID: "10",
          OWNER_OPT: "TRASH",
          OWNER: "TRASH",
          TRASH_KIND: "template",
          TRASH_SOURCE_ID: "21",
          QUANTITY: "1",
          PERSONAL_COST: "0",
          PRIZE: "0",
          CHARGE: "0",
        },
      ],
      activeShopId: "1",
    };

    const normalized = normalizePersistedTradePayload(legacyPayload);

    expect(normalized.schemaVersion).toBe(TRADE_PERSIST_SCHEMA_VERSION);
    expect(normalized.version).toBe(1);
    expect(normalized.templateItems[0]).toMatchObject({
      ID: 10,
      IMG_CLASS: "v0002",
      PRIZE: 50,
      CHARGE: 3,
    });
    expect(normalized.inventoryItems[0]).toMatchObject({
      ID: 21,
      INV_ID: 10,
      QUANTITY: 2,
      OWNER_OPT: "DEFAULT",
      OWNER: "BG1",
    });
    expect(normalized.trashItems[0]).toMatchObject({
      ID: 31,
      OWNER_OPT: "TRASH",
      OWNER: "TRASH",
      TRASH_KIND: "TEMPLATE",
      TRASH_SOURCE_ID: 21,
    });
    expect(normalized.activeShopId).toBe(1);
  });

  it("builds schema-versioned payload in canonical legacy-safe shape", () => {
    const payload = buildPersistedTradePayload({
      shops: [{ id: 2, name: "Another shop", shopEntries: [] }],
      templateItems: [
        {
          ID: "9",
          NAME: "Latarnia",
          DESCRIPTION: "Swiatlo",
          ITEM_CLASS: "TOOL",
          PRIZE: "22",
          CHARGE: "1",
        },
      ],
      inventoryItems: [
        {
          ID: "44",
          INV_ID: "9",
          OWNER_OPT: "DEFAULT",
          OWNER: "BG2",
          QUANTITY: "3",
          PERSONAL_COST: "25",
        },
      ],
      trashItems: [
        {
          ID: "55",
          INV_ID: "9",
          OWNER_OPT: "TRASH",
          OWNER: "TRASH",
          TRASH_KIND: "ITEM",
          QUANTITY: "1",
          PERSONAL_COST: "0",
          PRIZE: "0",
          CHARGE: "0",
        },
      ],
      activeShopId: "2",
    });

    expect(payload.schemaVersion).toBe(TRADE_PERSIST_SCHEMA_VERSION);
    expect(payload.version).toBe(TRADE_PERSIST_SCHEMA_VERSION);
    expect(payload.templateItems[0].ID).toBe(9);
    expect(payload.inventoryItems[0].ID).toBe(44);
    expect(payload.inventoryItems[0].OWNER).toBe("BG2");
    expect(payload.trashItems[0].TRASH_KIND).toBe("ITEM");
    expect(payload.activeShopId).toBe(2);
  });
});

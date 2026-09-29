import { describe, expect, it, vi } from "vitest";
import {
  createCharacterApiClient,
  normalizeCharacter,
} from "@/lib/character/characterApiClient";

describe("characterApiClient", () => {
  it("normalizes legacy character fields and capabilities", () => {
    expect(
      normalizeCharacter({
        id: "8",
        campaign_id: "2",
        system_id: "1",
        name: "Roch",
        data: { attributes: { actual: { ww: 31 } } },
        primary_currency_code: "wfrp_empire",
        revision: "4",
        capabilities: { can_edit: true },
      }),
    ).toMatchObject({
      id: 8,
      campaignId: 2,
      systemId: 1,
      primaryCurrencyCode: "wfrp_empire",
      revision: 4,
      capabilities: { canEdit: true, canDelete: false },
    });
  });

  it("loads the campaign-scoped list", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [{ id: 3, name: "Adele" }],
      capabilities: { canCreate: true },
    });

    await expect(
      createCharacterApiClient({ request }).list(4),
    ).resolves.toMatchObject({
      characters: [{ id: 3, name: "Adele" }],
      capabilities: { canCreate: true },
    });
    expect(request).toHaveBeenCalledWith("/characters?campaignId=4", {});
  });

  it("can exclude unassigned legacy records from a table list", async () => {
    const request = vi.fn().mockResolvedValue({ items: [] });

    await createCharacterApiClient({ request }).list(4, {
      assignedOnly: true,
      signal: "abort-signal",
    });

    expect(request).toHaveBeenCalledWith(
      "/characters?campaignId=4&assignedOnly=true",
      { signal: "abort-signal" },
    );
  });

  it("sends only editable sheet fields with the concurrency version", async () => {
    const request = vi.fn().mockResolvedValue({
      character: { id: 3, name: "Adele II", data: {} },
    });
    const api = createCharacterApiClient({ request });

    await api.update(4, 3, {
      name: "  Adele II ",
      data: { details: { race: "human" } },
      avatarUrl: "/avatars/adele.webp",
      revision: 7,
      updatedAt: "2026-08-19 12:00:00",
      userId: 99,
    });

    expect(request).toHaveBeenCalledWith("/characters/3?campaignId=4", {
      method: "PUT",
      body: {
        name: "Adele II",
        data: { details: { race: "human" } },
        avatarUrl: "/avatars/adele.webp",
        revision: 7,
        updatedAt: "2026-08-19 12:00:00",
      },
    });
  });

  it("rejects an invalid campaign id before a request is sent", async () => {
    const request = vi.fn();
    await expect(
      createCharacterApiClient({ request }).list("nope"),
    ).rejects.toThrow("campaignId");
    expect(request).not.toHaveBeenCalled();
  });

  it("loads and saves separate currency wallets", async () => {
    const request = vi.fn().mockResolvedValue({ wallets: [] });
    const api = createCharacterApiClient({ request });

    await api.wallets(4, 9);
    await api.updateWallets(
      4,
      9,
      [
        { currencyCode: "wfrp_empire", balance: 252.8 },
        { currencyCode: "wfrp_bretonnia", balance: -2 },
      ],
      "wfrp_empire",
    );

    expect(request.mock.calls).toEqual([
      ["/campaigns/4/characters/9/wallets", {}],
      [
        "/campaigns/4/characters/9/wallets",
        {
          method: "PUT",
          body: {
            primaryCurrencyCode: "wfrp_empire",
            wallets: [
              { currencyCode: "wfrp_empire", balance: 252 },
              { currencyCode: "wfrp_bretonnia", balance: 0 },
            ],
          },
        },
      ],
    ]);
  });
});

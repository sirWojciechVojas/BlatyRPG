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
});

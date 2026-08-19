import { describe, expect, it, vi } from "vitest";
import { createCharacterApiClient } from "@/lib/character/characterApiClient";
import { createCharacterCatalogApiClient } from "@/lib/character/characterCatalogApiClient";

describe("character mutations", () => {
  it("creates a character in the selected catalog game", async () => {
    const request = vi.fn().mockResolvedValue({
      character: { id: 9, campaignId: 4, name: "New NPC" },
    });
    const api = createCharacterApiClient({ request });

    await expect(
      api.create(4, {
        name: "  New NPC  ",
        systemId: "1",
        universeId: "2",
        avatarUrl: "",
        data: { details: {} },
      }),
    ).resolves.toMatchObject({ id: 9, name: "New NPC" });
    expect(request).toHaveBeenCalledWith("/characters", {
      method: "POST",
      body: {
        campaignId: 4,
        systemId: 1,
        universeId: 2,
        name: "New NPC",
        data: { details: {} },
        avatarUrl: "",
      },
    });
  });

  it("deletes only from the selected campaign scope", async () => {
    const request = vi.fn().mockResolvedValue({ id: 9 });
    await createCharacterApiClient({ request }).delete(4, 9);
    expect(request).toHaveBeenCalledWith("/characters/9?campaignId=4", {
      method: "DELETE",
    });
  });

  it("loads only active system and universe pairs", async () => {
    const request = vi.fn().mockResolvedValue({
      games: [
        {
          system_id: "1",
          universe_id: "2",
          system_name: "WFRP",
          universe_name: "Old World",
          is_active: 1,
        },
        { system_id: 3, universe_id: 4, is_active: "0" },
      ],
    });

    await expect(
      createCharacterCatalogApiClient({ request }).listGames(),
    ).resolves.toEqual([
      {
        systemId: 1,
        universeId: 2,
        systemName: "WFRP",
        universeName: "Old World",
        active: true,
      },
    ]);
  });
});

import { describe, expect, it, vi } from "vitest";
import { createCombatApiClient } from "@/lib/vtt/combatApiClient";

describe("combatApiClient", () => {
  it("normalizes the scene combat snapshot", async () => {
    const request = vi.fn().mockResolvedValue({
      combat: {
        id: 3,
        sceneId: 4,
        active: true,
        round: 2,
        turnIndex: 1,
        activeTokenId: 9,
        revision: 5,
        combatants: [
          {
            id: 7,
            tokenId: 9,
            initiative: "12.500",
            token: { id: 9, sceneId: 4, name: "Guard", revision: 2 },
          },
        ],
      },
      capabilities: { canManage: true },
    });

    const result = await createCombatApiClient({ request }).get(2, 4);

    expect(request).toHaveBeenCalledWith("/campaigns/2/scenes/4/combat");
    expect(result).toMatchObject({
      combat: {
        active: true,
        round: 2,
        turnIndex: 1,
        activeTokenId: 9,
        combatants: [{ tokenId: 9, initiative: 12.5 }],
      },
      capabilities: { canManage: true },
    });
  });
});

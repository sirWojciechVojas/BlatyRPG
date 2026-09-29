import { describe, expect, it, vi } from "vitest";
import { createTokenTemplateApiClient } from "../tokenTemplateApiClient";

describe("tokenTemplateApiClient", () => {
  it("loads and normalizes the manager template catalog", async () => {
    const request = vi.fn().mockResolvedValue({
      items: [
        {
          id: "7",
          name: "Wolf",
          imageUrl: "/api/campaigns/4/token-template-assets/2/file",
          widthCells: "2.000",
          heightCells: "1.500",
          revision: "3",
        },
      ],
      capabilities: { canCreateToken: true },
    });

    const result = await createTokenTemplateApiClient({ request }).list(4);

    expect(request).toHaveBeenCalledWith("/campaigns/4/token-templates", {});
    expect(result.items[0]).toMatchObject({
      id: 7,
      name: "Wolf",
      widthCells: 2,
      heightCells: 1.5,
      revision: 3,
    });
  });

  it("instantiates a template around a scene center", async () => {
    const request = vi.fn().mockResolvedValue({
      token: {
        id: 12,
        sceneId: 9,
        tokenTemplateId: 7,
        characterId: null,
        name: "Wolf",
        width: 200,
        height: 100,
      },
    });
    const client = createTokenTemplateApiClient({ request });

    const token = await client.instantiate(4, 9, 7, 500, 300);

    expect(request).toHaveBeenCalledWith(
      "/campaigns/4/scenes/9/tokens/from-template",
      {
        method: "POST",
        body: { templateId: 7, centerX: 500, centerY: 300 },
      },
    );
    expect(token).toMatchObject({
      id: 12,
      sceneId: 9,
      tokenTemplateId: 7,
      characterId: null,
    });
  });
});

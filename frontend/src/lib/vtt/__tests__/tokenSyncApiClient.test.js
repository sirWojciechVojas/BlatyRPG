import { describe, expect, it, vi } from "vitest";
import { createTokenSyncApiClient } from "../tokenSyncApiClient";

describe("tokenSyncApiClient", () => {
  it("uses the campaign-scoped catalog and preview endpoints", async () => {
    const request = vi
      .fn()
      .mockResolvedValueOnce({
        scenes: [{ id: 4, name: "Ruins" }],
        tokens: [{ id: 9, sceneId: 4, characterId: 12, name: "Hero" }],
        links: [],
        capabilities: { canManage: true },
      })
      .mockResolvedValueOnce({
        sourceTokenId: 9,
        sourceRevision: 3,
        targets: [{ tokenId: 10, revision: 7, changes: [] }],
      });
    const api = createTokenSyncApiClient({ request });

    await api.list(7);
    await api.preview(7, 9, [10]);

    expect(request).toHaveBeenNthCalledWith(1, "/campaigns/7/token-sync");
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/7/token-sync/preview",
      { method: "POST", body: { sourceTokenId: 9, targetTokenIds: [10] } },
    );
  });

  it("keeps target revisions in transfer and live-link writes", async () => {
    const request = vi.fn().mockResolvedValue({ synchronizedTokens: [] });
    const api = createTokenSyncApiClient({ request });
    const payload = {
      sourceTokenId: 9,
      sourceRevision: 3,
      targets: [{ tokenId: 10, revision: 7 }],
    };

    await api.transfer(7, payload);
    await api.createLinks(7, payload);

    expect(request).toHaveBeenNthCalledWith(
      1,
      "/campaigns/7/token-sync/transfer",
      { method: "POST", body: payload },
    );
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/7/token-sync/links",
      { method: "POST", body: payload },
    );
  });

  it("manages a concrete link without changing token payloads", async () => {
    const request = vi
      .fn()
      .mockResolvedValue({ link: { id: 33, enabled: false } });
    const api = createTokenSyncApiClient({ request });

    await api.updateLink(7, 33, false);
    await api.applyLink(7, 33);
    await api.deleteLink(7, 33);

    expect(request).toHaveBeenNthCalledWith(
      1,
      "/campaigns/7/token-sync/links/33",
      { method: "PATCH", body: { enabled: false } },
    );
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/7/token-sync/links/33/apply",
      { method: "POST" },
    );
    expect(request).toHaveBeenNthCalledWith(
      3,
      "/campaigns/7/token-sync/links/33",
      { method: "DELETE" },
    );
  });
});

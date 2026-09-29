import { describe, expect, it, vi } from "vitest";
import {
  COMPENDIUM_PAGE_SIZE,
  createCompendiumApiClient,
} from "../compendiumApiClient";

describe("compendiumApiClient", () => {
  it("coalesces repeated reads and supports an explicit fresh reload", async () => {
    const request = vi.fn().mockResolvedValue({ items: [{ id: 1 }] });
    const client = createCompendiumApiClient({
      client: { request },
      tokenResolver: () => "reader-1",
    });

    const first = client.campaignEntries(7, { page: 1, limit: 50 });
    const second = client.campaignEntries(7, { page: 1, limit: 50 });

    await Promise.all([first, second]);
    expect(request).toHaveBeenCalledTimes(1);

    await client.campaignEntries(7, { page: 1, limit: 50 });
    expect(request).toHaveBeenCalledTimes(1);

    client.clearCache();
    await client.campaignEntries(7, { page: 1, limit: 50 });
    expect(request).toHaveBeenCalledTimes(2);
  });

  it("invalidates cached reads before a write", async () => {
    const request = vi.fn().mockResolvedValue({ entry: { id: 9 } });
    const client = createCompendiumApiClient({
      client: { request },
      tokenResolver: () => "editor-1",
    });

    await client.worldEntry(4, 9);
    await client.worldEntry(4, 9);
    await client.updateEntry(4, 9, { title: "Citadel", revision: 11 });
    await client.worldEntry(4, 9);

    expect(request).toHaveBeenCalledTimes(3);
  });

  it("records read telemetry without evicting the article cache", async () => {
    const request = vi.fn().mockResolvedValue({ entry: { id: 9 } });
    const client = createCompendiumApiClient({
      client: { request },
      tokenResolver: () => "reader-1",
    });

    await client.campaignEntry(7, 9);
    await client.recordRead(7, 9);
    await client.campaignEntry(7, 9);

    expect(request).toHaveBeenCalledTimes(2);
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/7/compendium/entries/9/read",
      { method: "POST", body: {} },
    );
  });

  it("preloads the initial campaign view without duplicating later reads", async () => {
    const request = vi.fn().mockResolvedValue({ items: [] });
    const client = createCompendiumApiClient({
      client: { request },
      tokenResolver: () => "reader-1",
    });

    await client.preloadCampaign(7);
    await client.campaignOverview(7);
    await client.campaignEntries(7, {
      status: "active",
      page: 1,
      limit: COMPENDIUM_PAGE_SIZE,
    });

    expect(request).toHaveBeenCalledTimes(2);
    expect(request).toHaveBeenNthCalledWith(1, "/campaigns/7/compendium", {});
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/7/compendium/entries?status=active&page=1&limit=25",
      {},
    );
  });

  it("derives campaign paths and encodes reader filters", async () => {
    const request = vi.fn().mockResolvedValue({ items: [] });
    const client = createCompendiumApiClient({ client: { request } });

    await client.campaignEntries(7, {
      q: "old forest",
      type: 3,
      favorites: false,
      recent: false,
    });

    expect(request).toHaveBeenCalledWith(
      "/campaigns/7/compendium/entries?q=old+forest&type=3",
      {},
    );
  });

  it("sends optimistic revisions for drafts and publication", async () => {
    const request = vi.fn().mockResolvedValue({ entry: { id: 9 } });
    const client = createCompendiumApiClient({ client: { request } });

    await client.updateEntry(4, 9, { title: "Citadel", revision: 11 });
    await client.publish(4, 9, 12);

    expect(request).toHaveBeenNthCalledWith(
      1,
      "/universes/4/compendium/entries/9",
      { method: "PATCH", body: { title: "Citadel", revision: 11 } },
    );
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/universes/4/compendium/entries/9/publish",
      { method: "POST", body: { revision: 12 } },
    );
  });

  it("uploads registered corpus files through the authenticated API", async () => {
    const response = {
      ok: true,
      json: vi.fn().mockResolvedValue({ asset: { id: 8 } }),
    };
    const fetchImpl = vi.fn().mockResolvedValue(response);
    const client = createCompendiumApiClient({
      client: { request: vi.fn() },
      fetchImpl,
      baseUrl: "/api",
      tokenResolver: () => "secret-token",
    });

    await client.uploadCorpusAsset(4, 8, new Blob(["image"]));

    expect(fetchImpl).toHaveBeenCalledWith(
      "/api/universes/4/compendium/corpus-assets/8/file",
      expect.objectContaining({
        method: "POST",
        headers: expect.objectContaining({
          Authorization: "Bearer secret-token",
        }),
        body: expect.any(FormData),
      }),
    );
  });

  it("scopes bestiary source assets to the selected hero", async () => {
    const response = { ok: true, blob: vi.fn().mockResolvedValue(new Blob()) };
    const fetchImpl = vi.fn().mockResolvedValue(response);
    const client = createCompendiumApiClient({
      client: { request: vi.fn() },
      fetchImpl,
      baseUrl: "/api",
      tokenResolver: () => "secret-token",
    });

    await client.fetchCorpusAssetBlob(8, {
      campaignId: 4,
      characterId: 9,
    });

    expect(fetchImpl).toHaveBeenCalledWith(
      "/api/compendium-corpus-assets/8/file?campaignId=4&characterId=9",
      expect.objectContaining({
        headers: expect.objectContaining({
          Authorization: "Bearer secret-token",
        }),
      }),
    );
  });
});

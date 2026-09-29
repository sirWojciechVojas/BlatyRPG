import { describe, expect, it, vi } from "vitest";
import { createAdminApiClient } from "../adminApiClient";

describe("admin media API client", () => {
  it("serializes server filters and normalizes list pagination", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({
        items: [
          {
            id: "12",
            name: "Mapa",
            fileSize: "2048",
            revision: "3",
            tags: ["city"],
          },
        ],
        pagination: { page: 2, perPage: 30, total: 31, pages: 2 },
        facets: { provider: [{ value: "r2", count: 1 }] },
      }),
    };
    const result = await createAdminApiClient(client).mediaAssets({
      page: 2,
      q: "Mapa",
      provider: "r2",
      category: "",
    });

    expect(client.request).toHaveBeenCalledWith(
      "/admin/media-assets?page=2&q=Mapa&provider=r2",
      {},
    );
    expect(result.items[0]).toMatchObject({
      id: 12,
      fileSize: 2048,
      revision: 3,
      tags: ["city"],
    });
    expect(result.pagination.total).toBe(31);
  });

  it("uses revision-aware edits, bulk operations and collection membership", async () => {
    const client = { request: vi.fn().mockResolvedValue({ asset: { id: 8 } }) };
    const api = createAdminApiClient(client);
    await api.updateMediaAsset(8, { revision: 4, name: "Nowa" });
    await api.bulkUpdateMediaAssets({
      ids: [8, 9],
      tagMode: "add",
      tags: ["map"],
    });
    await api.changeMediaCollectionAssets(3, [8, 9], true);
    await api.changeMediaCollectionAssets(3, [9], false);

    expect(client.request).toHaveBeenNthCalledWith(1, "/admin/media-assets/8", {
      method: "PATCH",
      body: { revision: 4, name: "Nowa" },
    });
    expect(client.request).toHaveBeenNthCalledWith(
      2,
      "/admin/media-assets/bulk",
      {
        method: "PATCH",
        body: { ids: [8, 9], tagMode: "add", tags: ["map"] },
      },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      3,
      "/admin/media-collections/3/assets",
      { method: "POST", body: { ids: [8, 9] } },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      4,
      "/admin/media-collections/3/assets",
      { method: "DELETE", body: { ids: [9] } },
    );
  });

  it("registers and normalizes external map references", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({
        asset: {
          id: "24",
          provider: "external",
          sourceUrl: "https://cdn.example.test/maps/library.webp",
          availabilityStatus: "unknown",
        },
      }),
    };

    const asset = await createAdminApiClient(client).createExternalMediaAsset({
      sourceUrl: "https://cdn.example.test/maps/library.webp",
      category: "maps",
    });

    expect(client.request).toHaveBeenCalledWith(
      "/admin/media-assets/external",
      {
        method: "POST",
        body: {
          sourceUrl: "https://cdn.example.test/maps/library.webp",
          category: "maps",
        },
      },
    );
    expect(asset).toMatchObject({
      id: 24,
      provider: "external",
      sourceUrl: "https://cdn.example.test/maps/library.webp",
      availabilityStatus: "unknown",
    });
  });

  it("searches paged Game Master audio libraries and publishes a personal track", async () => {
    const client = {
      request: vi
        .fn()
        .mockResolvedValueOnce({
          items: [
            {
              id: "3",
              name: "GM Library",
              owner: { id: "7", username: "Mistrz" },
              trackCount: "4",
              assetCount: "3",
            },
          ],
          pagination: { page: 2, perPage: 25, total: 501, pages: 21 },
        })
        .mockResolvedValueOnce({ mode: "copy", trackId: 12 }),
    };
    const api = createAdminApiClient(client);

    const result = await api.mediaAudioLibraries({ q: "Mistrz", page: 2 });
    await api.publishPersonalAudioTrack(11, {
      targetLibraryId: 8,
      mode: "copy",
    });

    expect(result.items[0]).toMatchObject({
      id: 3,
      owner: { id: 7, username: "Mistrz" },
      trackCount: 4,
      assetCount: 3,
    });
    expect(result.pagination).toMatchObject({ total: 501, pages: 21 });
    expect(client.request).toHaveBeenNthCalledWith(
      1,
      "/admin/media-audio-libraries?q=Mistrz&page=2",
      {},
    );
    expect(client.request).toHaveBeenNthCalledWith(
      2,
      "/admin/media-assets/audio-tracks/11/publish",
      { method: "POST", body: { targetLibraryId: 8, mode: "copy" } },
    );
  });
});

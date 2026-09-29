import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mediaApi } from "@/lib/mediaApi";

describe("media API", () => {
  let originalFetch;

  beforeEach(() => {
    vi.restoreAllMocks();
    originalFetch = window.fetch;
    window.localStorage.clear();
  });

  afterEach(() => {
    window.fetch = originalFetch;
  });

  it("uses provider-neutral upload instructions from the backend", async () => {
    const file = new File(["audio"], "ambience.ogg", { type: "audio/ogg" });
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          asset: { id: 41 },
          upload: {
            method: "PUT",
            url: "https://upload.example.test/signed",
            encoding: "binary",
            headers: { "Content-Type": "audio/ogg" },
            fields: {},
          },
        }),
      })
      .mockResolvedValueOnce({
        ok: true,
        headers: { get: () => '"etag-value"' },
      })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ asset: { id: 41, status: "ready" } }),
      });
    window.fetch = fetchMock;

    const result = await mediaApi.upload(file, {
      category: "audio",
      visibility: "private",
    });

    expect(result.asset.status).toBe("ready");
    expect(fetchMock).toHaveBeenCalledTimes(3);
    expect(fetchMock.mock.calls[1][0]).toBe(
      "https://upload.example.test/signed",
    );
    expect(fetchMock.mock.calls[1][1].body).toBe(file);
  });
});

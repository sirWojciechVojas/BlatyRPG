import { describe, expect, it, vi } from "vitest";
import {
  createJsonApiClient,
  isJsonApiAuthorizationError,
  JsonApiError,
} from "@/lib/api/jsonApiClient";

const response = (status, body = "") => ({
  ok: status >= 200 && status < 300,
  status,
  text: async () => body,
});

describe("jsonApiClient", () => {
  it("adds bearer authentication and serializes a JSON body", async () => {
    const fetchImpl = vi.fn().mockResolvedValue(response(200, '{"ok":true}'));
    const client = createJsonApiClient({
      baseUrl: "/api/",
      fetchImpl,
      tokenResolver: () => "access-token",
    });

    await expect(
      client.request("/campaigns/7/scenes", {
        method: "POST",
        body: { name: "Ruins" },
      }),
    ).resolves.toEqual({ ok: true });
    expect(fetchImpl).toHaveBeenCalledWith(
      "/api/campaigns/7/scenes",
      expect.objectContaining({
        method: "POST",
        body: '{"name":"Ruins"}',
        headers: expect.objectContaining({
          Authorization: "Bearer access-token",
          "Content-Type": "application/json",
        }),
      }),
    );
  });

  it("accepts an empty successful response", async () => {
    const client = createJsonApiClient({
      fetchImpl: vi.fn().mockResolvedValue(response(204)),
      tokenResolver: () => "",
    });
    await expect(
      client.request("/resource", { method: "DELETE" }),
    ).resolves.toBeNull();
  });

  it("exposes authorization failures as typed errors", async () => {
    const client = createJsonApiClient({
      fetchImpl: vi
        .fn()
        .mockResolvedValue(response(403, '{"code":"forbidden"}')),
      tokenResolver: () => "",
    });
    const error = await client.request("/resource").catch((reason) => reason);
    expect(error).toBeInstanceOf(JsonApiError);
    expect(error.code).toBe("forbidden");
    expect(isJsonApiAuthorizationError(error)).toBe(true);
  });

  it("notifies the session handler on an authenticated 401", async () => {
    const onUnauthorized = vi.fn();
    const client = createJsonApiClient({
      fetchImpl: vi
        .fn()
        .mockResolvedValue(response(401, '{"code":"session_expired"}')),
      tokenResolver: () => "expired-token",
      onUnauthorized,
    });

    const error = await client.request("/campaigns").catch((reason) => reason);

    expect(onUnauthorized).toHaveBeenCalledWith(error);
    expect(error).toMatchObject({ status: 401, code: "session_expired" });
  });
});

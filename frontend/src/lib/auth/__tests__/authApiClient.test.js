import { describe, expect, it, vi } from "vitest";
import { createAuthApiClient } from "@/lib/auth/authApiClient";

describe("authApiClient", () => {
  it("uses the canonical login endpoint and normalizes its response", async () => {
    const request = vi.fn().mockResolvedValue({
      access_token: "jwt",
      expires_in: 3600,
      user: { id: "2", login: "admin", role: "ADMIN" },
    });
    const client = createAuthApiClient({ request });

    await expect(
      client.login({ login: " admin ", password: "secret" }),
    ).resolves.toEqual({
      token: "jwt",
      expiresIn: 3600,
      user: {
        id: 2,
        username: "admin",
        email: "",
        role: "admin",
        avatarUrl: null,
      },
    });
    expect(request).toHaveBeenCalledWith("/auth/login", {
      method: "POST",
      body: { login: "admin", password: "secret" },
    });
  });

  it("reads the authenticated user from a wrapped response", async () => {
    const request = vi.fn().mockResolvedValue({
      data: { user: { id: 4, username: "gm", avatar_url: "/gm.png" } },
    });
    await expect(createAuthApiClient({ request }).me()).resolves.toMatchObject({
      id: 4,
      username: "gm",
      avatarUrl: "/gm.png",
    });
  });
});

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
      expiresAt: null,
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

  it("uses the backend field aliases for registration and password reset", async () => {
    const request = vi
      .fn()
      .mockResolvedValueOnce({ user: { id: 5, role: "user" } })
      .mockResolvedValueOnce({ message: "ok" })
      .mockResolvedValueOnce({
        access_token: "renewed",
        expires_in: 60,
        user: { id: 5, role: "player" },
      });
    const client = createAuthApiClient({ request });

    await client.register({
      username: " Ada ",
      email: " ada@example.test ",
      password: "long-password",
      confirmPassword: "long-password",
    });
    await client.requestReset({ email: " ada@example.test " });
    await client.confirmReset({
      token: " token ",
      password: "long-password",
      confirmPassword: "long-password",
    });

    expect(request.mock.calls[0]).toEqual([
      "/auth/register",
      {
        method: "POST",
        body: {
          username: "Ada",
          email: "ada@example.test",
          password: "long-password",
          confirm_password: "long-password",
        },
      },
    ]);
    expect(request.mock.calls[1][0]).toBe("/auth/password-reset/request");
    expect(request.mock.calls[2][0]).toBe("/auth/password-reset/confirm");
  });

  it("keeps the regular application account role", async () => {
    const request = vi.fn().mockResolvedValue({
      user: { id: 4, username: "player", role: "user" },
    });
    await expect(createAuthApiClient({ request }).me()).resolves.toMatchObject({
      role: "user",
    });
  });

  it("lists and revokes only explicitly selected account sessions", async () => {
    const request = vi
      .fn()
      .mockResolvedValueOnce({
        sessions: [
          {
            id: "8",
            is_current: true,
            last_seen_at: "2026-08-23 12:00:00",
          },
        ],
      })
      .mockResolvedValue({ message: "ok" });
    const client = createAuthApiClient({ request });

    await expect(client.sessions()).resolves.toEqual([
      {
        id: 8,
        isCurrent: true,
        createdAt: null,
        lastSeenAt: "2026-08-23 12:00:00",
        expiresAt: null,
      },
    ]);
    await client.revokeSession(9);
    await client.revokeOtherSessions();

    expect(request.mock.calls.slice(1)).toEqual([
      ["/auth/sessions/9", { method: "DELETE" }],
      ["/auth/sessions/revoke-others", { method: "POST" }],
    ]);
    expect(() => client.revokeSession("foreign")).toThrow(TypeError);
  });
});

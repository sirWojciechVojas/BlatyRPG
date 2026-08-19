import { describe, expect, it } from "vitest";
import {
  ACCESS_TOKEN_KEY,
  AUTH_SESSION_KEY,
  createAuthSession,
  TOKEN_ALIASES,
} from "@/lib/auth/authSession";

const createStorage = () => {
  const values = new Map();
  return {
    getItem: (key) => values.get(key) ?? null,
    setItem: (key, value) => values.set(key, String(value)),
    removeItem: (key) => values.delete(key),
  };
};

describe("authSession", () => {
  it("stores only the canonical token and durable user metadata", () => {
    const storage = createStorage();
    const session = createAuthSession({ storage, now: () => 1_000 });

    session.save({
      token: "signed-token",
      expiresIn: 60,
      user: { id: "7", login: "Ada", role: "ADMIN" },
    });

    expect(storage.getItem(ACCESS_TOKEN_KEY)).toBe("signed-token");
    expect(JSON.parse(storage.getItem(AUTH_SESSION_KEY))).toEqual({
      expiresAt: 61_000,
      user: {
        id: 7,
        username: "Ada",
        email: "",
        role: "admin",
        avatarUrl: null,
      },
    });
  });

  it("clears the session and every legacy token alias on logout", () => {
    const storage = createStorage();
    TOKEN_ALIASES.forEach((key) => storage.setItem(key, "legacy"));
    storage.setItem(AUTH_SESSION_KEY, "{}");

    createAuthSession({ storage }).clear();

    TOKEN_ALIASES.forEach((key) => expect(storage.getItem(key)).toBeNull());
    expect(storage.getItem(AUTH_SESSION_KEY)).toBeNull();
  });

  it("migrates a legacy token alias into the canonical session", () => {
    const storage = createStorage();
    storage.setItem("blatyrpg.jwt", "legacy-token");

    const result = createAuthSession({ storage }).read();

    expect(result).toMatchObject({ token: "legacy-token", user: null });
    expect(storage.getItem(ACCESS_TOKEN_KEY)).toBe("legacy-token");
    expect(storage.getItem("blatyrpg.jwt")).toBeNull();
    expect(JSON.parse(storage.getItem(AUTH_SESSION_KEY))).toEqual({
      expiresAt: null,
      user: null,
    });
  });

  it("invalidates an expired session", () => {
    const storage = createStorage();
    const session = createAuthSession({ storage, now: () => 20_000 });
    storage.setItem(ACCESS_TOKEN_KEY, "token");
    storage.setItem(
      AUTH_SESSION_KEY,
      JSON.stringify({ expiresAt: 10_000, user: { id: 1 } }),
    );

    expect(session.read()).toBeNull();
    expect(storage.getItem(ACCESS_TOKEN_KEY)).toBeNull();
  });
});

/* global globalThis */
import { afterEach, describe, expect, it, vi } from "vitest";
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
  afterEach(() => vi.useRealTimers());
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

  it("rejects a legacy token without a verifiable expiry", () => {
    const storage = createStorage();
    storage.setItem("blatyrpg.jwt", "legacy-token");

    const result = createAuthSession({ storage }).read();

    expect(result).toBeNull();
    expect(storage.getItem(ACCESS_TOKEN_KEY)).toBeNull();
    expect(storage.getItem("blatyrpg.jwt")).toBeNull();
    expect(storage.getItem(AUTH_SESSION_KEY)).toBeNull();
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

  it("accepts expires_at and normalizes epoch seconds", () => {
    const storage = createStorage();
    const session = createAuthSession({ storage, now: () => 1_000 });

    expect(
      session.save({
        token: "signed-token",
        expires_at: 61,
        user: { id: 1, role: "user" },
      }),
    ).toMatchObject({ expiresAt: 61_000, user: { role: "player" } });
  });

  it("expires proactively and notifies subscribers", () => {
    vi.useFakeTimers();
    vi.setSystemTime(1_000);
    const storage = createStorage();
    const session = createAuthSession({ storage });
    const listener = vi.fn();
    session.subscribe(listener);
    session.save({ token: "token", expiresIn: 1, user: { id: 1 } });

    vi.advanceTimersByTime(1_000);

    expect(session.read()).toBeNull();
    expect(listener).toHaveBeenLastCalledWith(null, "expired");
  });

  it("isolates a failing subscriber from session persistence", () => {
    const storage = createStorage();
    const session = createAuthSession({ storage, now: () => 1_000 });
    const listener = vi.fn();
    session.subscribe(
      () => {
        throw new Error("stale_component");
      },
      { immediate: false },
    );
    session.subscribe(listener, { immediate: false });

    expect(() =>
      session.save({ token: "token", expiresIn: 60, user: { id: 1 } }),
    ).not.toThrow();
    expect(listener).toHaveBeenLastCalledWith(
      expect.objectContaining({ token: "token" }),
      "saved",
    );
    expect(session.read()).toMatchObject({ token: "token" });
  });

  it("reports an unavailable browser storage without leaving a token", () => {
    const values = new Map();
    const storage = {
      getItem: (key) => values.get(key) ?? null,
      removeItem: (key) => values.delete(key),
      setItem: (key, value) => {
        if (key === ACCESS_TOKEN_KEY) values.set(key, String(value));
        else throw new DOMException("Quota exceeded", "QuotaExceededError");
      },
    };
    const session = createAuthSession({ storage, now: () => 1_000 });

    expect(() =>
      session.save({ token: "token", expiresIn: 60, user: { id: 1 } }),
    ).toThrow(
      expect.objectContaining({
        code: "session_storage_unavailable",
        message: "session_storage_unavailable",
      }),
    );
    expect(storage.getItem(ACCESS_TOKEN_KEY)).toBeNull();
  });

  it("invokes browser timers with the global receiver", () => {
    const storage = createStorage();
    const setTimer = vi.fn(function () {
      if (this !== globalThis) throw new TypeError("Illegal invocation");
      return 73;
    });
    const clearTimer = vi.fn(function () {
      if (this !== globalThis) throw new TypeError("Illegal invocation");
    });
    const session = createAuthSession({
      storage,
      now: () => 1_000,
      setTimeout: setTimer,
      clearTimeout: clearTimer,
    });

    expect(() =>
      session.save({ token: "token", expiresIn: 60, user: { id: 1 } }),
    ).not.toThrow();
    session.clear();

    expect(setTimer).toHaveBeenCalledOnce();
    expect(clearTimer).toHaveBeenCalledWith(73);
  });

  it("requires a reliable expiry when saving", () => {
    const storage = createStorage();
    expect(() =>
      createAuthSession({ storage }).save({ token: "opaque-token" }),
    ).toThrow("session_expiry_required");
  });
});

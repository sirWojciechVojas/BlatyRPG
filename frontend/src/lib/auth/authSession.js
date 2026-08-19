/* global globalThis */
import {
  ACCESS_TOKEN_KEYS,
  registerJsonApiUnauthorizedHandler,
} from "@/lib/api/jsonApiClient";

export const ACCESS_TOKEN_KEY = "access_token";
export const AUTH_SESSION_KEY = "blatyrpg.auth.session";
export const TOKEN_ALIASES = ACCESS_TOKEN_KEYS;

const storageOrDefault = (storage) => {
  if (storage) return storage;
  return typeof window === "undefined" ? null : window.localStorage;
};

const parseJson = (value) => {
  try {
    return JSON.parse(value);
  } catch (_error) {
    return null;
  }
};

const jwtExpiry = (token) => {
  try {
    const encoded = String(token).split(".")[1];
    if (!encoded) return null;
    const unpadded = encoded.replace(/-/g, "+").replace(/_/g, "/");
    const normalized = unpadded.padEnd(Math.ceil(unpadded.length / 4) * 4, "=");
    const payload = JSON.parse(atob(normalized));
    return Number(payload.exp) > 0 ? Number(payload.exp) * 1000 : null;
  } catch (_error) {
    return null;
  }
};

const absoluteExpiry = (value, secondsAllowed = false) => {
  if (value === null || value === undefined || value === "") return null;
  const numeric = Number(value);
  if (Number.isFinite(numeric) && numeric > 0) {
    return secondsAllowed && numeric < 1e12 ? numeric * 1000 : numeric;
  }
  const parsed = Date.parse(String(value));
  return Number.isFinite(parsed) ? parsed : null;
};

const normalizeUser = (user) => {
  if (!user || typeof user !== "object") return null;
  const role = String(user.role || "player").toLowerCase();
  return {
    id: Number(user.id) || user.id || null,
    username: String(user.username || user.login || ""),
    email: String(user.email || ""),
    role: role === "user" ? "player" : role,
    avatarUrl: user.avatarUrl || user.avatar_url || null,
  };
};

const browserEventTarget = () =>
  typeof window === "undefined" ? null : window;

export const createAuthSession = (options = {}) => {
  const storage = storageOrDefault(options.storage);
  const now = options.now || Date.now;
  const setTimer = options.setTimeout || globalThis.setTimeout;
  const clearTimer = options.clearTimeout || globalThis.clearTimeout;
  const timers = {
    set: (...args) => Reflect.apply(setTimer, globalThis, args),
    clear: (...args) => Reflect.apply(clearTimer, globalThis, args),
  };
  const eventTarget = options.eventTarget ?? browserEventTarget();
  const listeners = new Set();
  let expiryTimer = null;
  let listeningToStorage = false;

  const cancelExpiryTimer = () => {
    if (expiryTimer !== null && timers.clear) timers.clear(expiryTimer);
    expiryTimer = null;
  };

  const removeStoredSession = () => {
    if (!storage) return;
    try {
      TOKEN_ALIASES.forEach((key) => storage.removeItem(key));
      storage.removeItem(AUTH_SESSION_KEY);
    } catch (_error) {
      // A blocked storage behaves like an anonymous session.
    }
  };

  const emit = (session, reason) => {
    listeners.forEach((listener) => {
      try {
        listener(session, reason);
      } catch (_error) {
        // One stale UI subscriber must not invalidate a saved session.
      }
    });
  };

  const findToken = () =>
    TOKEN_ALIASES.map((key) => ({
      key,
      token: String(storage?.getItem(key) || "").trim(),
    })).find((entry) => entry.token);

  let read;
  const scheduleExpiry = (expiresAt) => {
    cancelExpiryTimer();
    if (!timers.set || !expiresAt) return;
    const remaining = expiresAt - now();
    if (remaining <= 0) return;
    expiryTimer = timers.set(
      () => {
        expiryTimer = null;
        const session = read();
        if (session) scheduleExpiry(session.expiresAt);
        else emit(null, "expired");
      },
      Math.min(remaining, 2_147_483_647),
    );
  };

  read = () => {
    if (!storage) return null;
    try {
      const tokenEntry = findToken();
      if (!tokenEntry) {
        cancelExpiryTimer();
        return null;
      }
      const token = tokenEntry.token;
      const metadata = parseJson(storage.getItem(AUTH_SESSION_KEY)) || {};
      const expiresAt =
        absoluteExpiry(metadata.expiresAt) ||
        absoluteExpiry(metadata.expires_at, true) ||
        jwtExpiry(token);
      if (!expiresAt || expiresAt <= now()) {
        cancelExpiryTimer();
        removeStoredSession();
        return null;
      }
      const user = normalizeUser(metadata.user);
      if (
        tokenEntry.key !== ACCESS_TOKEN_KEY ||
        metadata.expiresAt !== expiresAt ||
        metadata.expires_at !== undefined
      ) {
        TOKEN_ALIASES.forEach((key) => storage.removeItem(key));
        storage.setItem(ACCESS_TOKEN_KEY, token);
        storage.setItem(AUTH_SESSION_KEY, JSON.stringify({ expiresAt, user }));
      }
      scheduleExpiry(expiresAt);
      return { token, expiresAt, user };
    } catch (_error) {
      cancelExpiryTimer();
      return null;
    }
  };

  const clear = (reason = "cleared") => {
    cancelExpiryTimer();
    removeStoredSession();
    emit(null, reason);
  };

  const save = ({
    token,
    user,
    expiresIn,
    expiresAt,
    expires_at: snakeExpiry,
  }) => {
    const normalizedToken = String(token || "").trim();
    if (!storage || !normalizedToken) throw new TypeError("token_required");
    const calculatedExpiry =
      absoluteExpiry(expiresAt) ||
      absoluteExpiry(snakeExpiry, true) ||
      (Number(expiresIn) > 0 ? now() + Number(expiresIn) * 1000 : null) ||
      jwtExpiry(normalizedToken);
    if (!calculatedExpiry) throw new TypeError("session_expiry_required");
    if (calculatedExpiry <= now()) throw new TypeError("session_expired");

    const session = { user: normalizeUser(user), expiresAt: calculatedExpiry };
    try {
      removeStoredSession();
      storage.setItem(ACCESS_TOKEN_KEY, normalizedToken);
      storage.setItem(AUTH_SESSION_KEY, JSON.stringify(session));
    } catch (cause) {
      removeStoredSession();
      const error = new TypeError("session_storage_unavailable");
      error.code = "session_storage_unavailable";
      error.cause = cause;
      throw error;
    }
    const result = { ...session, token: normalizedToken };
    scheduleExpiry(calculatedExpiry);
    emit(result, "saved");
    return result;
  };

  const updateUser = (user) => {
    const current = read();
    if (!current) return null;
    return save({ ...current, user });
  };

  const onStorage = (event) => {
    if (event?.storageArea && event.storageArea !== storage) return;
    if (
      event?.key &&
      event.key !== AUTH_SESSION_KEY &&
      !TOKEN_ALIASES.includes(event.key)
    ) {
      return;
    }
    emit(read(), "storage");
  };

  const subscribe = (listener, subscribeOptions = {}) => {
    if (typeof listener !== "function")
      throw new TypeError("listener_required");
    listeners.add(listener);
    if (!listeningToStorage && eventTarget?.addEventListener) {
      eventTarget.addEventListener("storage", onStorage);
      listeningToStorage = true;
    }
    if (subscribeOptions.immediate !== false) listener(read(), "initial");
    return () => {
      listeners.delete(listener);
      if (!listeners.size && listeningToStorage) {
        eventTarget?.removeEventListener?.("storage", onStorage);
        listeningToStorage = false;
      }
    };
  };

  return {
    clear,
    isAuthenticated: () => Boolean(read()),
    read,
    save,
    subscribe,
    updateUser,
  };
};

export const authSession = createAuthSession();

registerJsonApiUnauthorizedHandler(() => authSession.clear("unauthorized"));

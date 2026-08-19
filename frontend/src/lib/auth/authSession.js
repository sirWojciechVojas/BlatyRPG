import { ACCESS_TOKEN_KEYS } from "@/lib/api/jsonApiClient";

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

const normalizeUser = (user) => {
  if (!user || typeof user !== "object") return null;
  return {
    id: Number(user.id) || user.id || null,
    username: String(user.username || user.login || ""),
    email: String(user.email || ""),
    role: String(user.role || "user").toLowerCase(),
    avatarUrl: user.avatarUrl || user.avatar_url || null,
  };
};

export const createAuthSession = (options = {}) => {
  const storage = storageOrDefault(options.storage);
  const now = options.now || Date.now;

  const clear = () => {
    if (!storage) return;
    try {
      TOKEN_ALIASES.forEach((key) => storage.removeItem(key));
      storage.removeItem(AUTH_SESSION_KEY);
    } catch (_error) {
      // A blocked storage must behave like an anonymous session.
    }
  };

  const read = () => {
    if (!storage) return null;
    try {
      const tokenEntry = TOKEN_ALIASES.map((key) => ({
        key,
        token: String(storage.getItem(key) || "").trim(),
      })).find((entry) => entry.token);
      if (!tokenEntry) return null;
      const token = tokenEntry.token;
      const metadata = parseJson(storage.getItem(AUTH_SESSION_KEY)) || {};
      const expiresAt = Number(metadata.expiresAt) || jwtExpiry(token);
      if (expiresAt && expiresAt <= now()) {
        clear();
        return null;
      }
      const user = normalizeUser(metadata.user);
      if (tokenEntry.key !== ACCESS_TOKEN_KEY || !metadata.expiresAt) {
        TOKEN_ALIASES.forEach((key) => storage.removeItem(key));
        storage.setItem(ACCESS_TOKEN_KEY, token);
        storage.setItem(AUTH_SESSION_KEY, JSON.stringify({ expiresAt, user }));
      }
      return { token, expiresAt, user };
    } catch (_error) {
      return null;
    }
  };

  const save = ({ token, user, expiresIn, expiresAt }) => {
    const normalizedToken = String(token || "").trim();
    if (!storage || !normalizedToken) throw new TypeError("token_required");
    const calculatedExpiry =
      Number(expiresAt) ||
      (Number(expiresIn) > 0 ? now() + Number(expiresIn) * 1000 : null) ||
      jwtExpiry(normalizedToken);
    const session = {
      user: normalizeUser(user),
      expiresAt: calculatedExpiry,
    };
    clear();
    storage.setItem(ACCESS_TOKEN_KEY, normalizedToken);
    storage.setItem(AUTH_SESSION_KEY, JSON.stringify(session));
    return { ...session, token: normalizedToken };
  };

  const updateUser = (user) => {
    const current = read();
    if (!current) return null;
    return save({
      token: current.token,
      expiresAt: current.expiresAt,
      user,
    });
  };

  return {
    clear,
    isAuthenticated: () => Boolean(read()),
    read,
    save,
    updateUser,
  };
};

export const authSession = createAuthSession();

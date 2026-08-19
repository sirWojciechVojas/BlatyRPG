import { jsonApiClient } from "@/lib/api/jsonApiClient";

const firstObject = (...values) =>
  values.find((value) => value && typeof value === "object") || null;

const canonicalRole = (role) => {
  const value = String(role || "player").toLowerCase();
  return value === "user" ? "player" : value;
};

export const normalizeAuthUser = (user) => {
  if (!user) return null;
  return {
    id: Number(user.id) || user.id || null,
    username: String(user.username || user.login || ""),
    email: String(user.email || ""),
    role: canonicalRole(user.role),
    avatarUrl: user.avatarUrl || user.avatar_url || null,
  };
};

const sessionResult = (payload) => {
  const data = firstObject(payload?.data, payload) || {};
  return {
    token: data.accessToken || data.access_token || data.token || "",
    expiresIn: Number(data.expiresIn || data.expires_in) || null,
    expiresAt: data.expiresAt || data.expires_at || null,
    user: normalizeAuthUser(firstObject(data.user, payload?.user)),
  };
};

const trim = (value) => String(value || "").trim();

export const createAuthApiClient = (client = jsonApiClient) => ({
  async login({ login, password }) {
    const payload = await client.request("/auth/login", {
      method: "POST",
      body: { login: trim(login), password: String(password || "") },
    });
    return sessionResult(payload);
  },

  async register({ username, email, password, confirmPassword }) {
    const payload = await client.request("/auth/register", {
      method: "POST",
      body: {
        username: trim(username),
        email: trim(email),
        password: String(password || ""),
        confirm_password: String(confirmPassword || ""),
      },
    });
    return sessionResult(payload);
  },

  async me(options = {}) {
    const payload = await client.request("/auth/me", options);
    const user = normalizeAuthUser(
      firstObject(payload?.user, payload?.data?.user, payload?.data),
    );
    if (!user?.id) throw new TypeError("invalid_user_response");
    return user;
  },

  logout() {
    return client.request("/auth/logout", { method: "POST" });
  },

  async updateProfile(changes) {
    const body = {};
    if (changes.username !== undefined) body.username = trim(changes.username);
    if (changes.email !== undefined) body.email = trim(changes.email);
    if (changes.avatarUrl !== undefined) {
      body.avatarUrl = trim(changes.avatarUrl) || null;
    }
    const payload = await client.request("/auth/profile", {
      method: "PATCH",
      body,
    });
    const user = normalizeAuthUser(
      firstObject(payload?.user, payload?.data?.user, payload?.data),
    );
    if (!user?.id) throw new TypeError("invalid_user_response");
    return user;
  },

  async changePassword({ currentPassword, newPassword, confirmPassword }) {
    const payload = await client.request("/auth/change-password", {
      method: "POST",
      body: {
        currentPassword: String(currentPassword || ""),
        newPassword: String(newPassword || ""),
        confirmPassword: String(confirmPassword || ""),
      },
    });
    return sessionResult(payload);
  },

  requestReset({ email }) {
    return client.request("/auth/password-reset/request", {
      method: "POST",
      body: { email: trim(email) },
    });
  },

  async confirmReset({ token, password, confirmPassword }) {
    const payload = await client.request("/auth/password-reset/confirm", {
      method: "POST",
      body: {
        token: trim(token),
        password: String(password || ""),
        confirmPassword: String(confirmPassword || ""),
      },
    });
    return sessionResult(payload);
  },
});

export const authApiClient = createAuthApiClient();

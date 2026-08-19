import { jsonApiClient } from "@/lib/api/jsonApiClient";

const firstObject = (...values) =>
  values.find((value) => value && typeof value === "object") || null;

const normalizeUser = (user) => {
  if (!user) return null;
  return {
    id: Number(user.id) || user.id || null,
    username: String(user.username || user.login || ""),
    email: String(user.email || ""),
    role: String(user.role || "user").toLowerCase(),
    avatarUrl: user.avatarUrl || user.avatar_url || null,
  };
};

export const createAuthApiClient = (client = jsonApiClient) => ({
  async login({ login, password }) {
    const payload = await client.request("/auth/login", {
      method: "POST",
      body: { login: String(login || "").trim(), password: String(password) },
    });
    const data = firstObject(payload?.data, payload) || {};
    return {
      token: data.accessToken || data.access_token || data.token || "",
      expiresIn: Number(data.expiresIn || data.expires_in) || null,
      user: normalizeUser(firstObject(data.user, payload?.user)),
    };
  },

  async me(options = {}) {
    const payload = await client.request("/auth/me", options);
    const user = normalizeUser(
      firstObject(payload?.user, payload?.data?.user, payload?.data),
    );
    if (!user?.id) throw new TypeError("invalid_user_response");
    return user;
  },
});

export const authApiClient = createAuthApiClient();

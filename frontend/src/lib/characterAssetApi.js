const apiBase = String(process.env.VUE_APP_API_BASE || "/api").replace(
  /\/+$/u,
  "",
);

const accessToken = () => {
  if (typeof window === "undefined") return "";
  for (const key of [
    "access_token",
    "blatyrpg.access_token",
    "blatyrpg.jwt",
    "blatyrpg.token",
    "auth_token",
    "token",
    "jwt",
  ]) {
    const value = String(window.localStorage.getItem(key) || "").trim();
    if (value) return value;
  }
  return "";
};

const request = async (path, options = {}) => {
  const token = accessToken();
  const response = await fetch(`${apiBase}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      ...(options.body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
    credentials: "same-origin",
  });
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    const error = new Error(
      payload?.code || payload?.error || `http_${response.status}`,
    );
    error.status = response.status;
    error.payload = payload;
    throw error;
  }
  return payload;
};

export const characterAssetApi = {
  availableSets: () => request("/character-asset-sets/available"),
  forCharacter: (characterId) =>
    request(`/characters/${Number(characterId)}/assets`),
  assign: (characterId, assetSetId) =>
    request(`/characters/${Number(characterId)}/asset-set`, {
      method: "PUT",
      body: JSON.stringify({ assetSetId: Number(assetSetId) }),
    }),
};

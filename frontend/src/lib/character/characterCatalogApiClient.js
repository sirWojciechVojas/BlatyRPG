import { jsonApiClient } from "@/lib/api/jsonApiClient";

const gamesFrom = (payload) => {
  for (const value of [payload?.games, payload?.items, payload?.data?.games]) {
    if (Array.isArray(value)) return value;
  }
  return [];
};

const isActive = (value) => {
  if (value === undefined || value === null) return true;
  return value === true || value === 1 || value === "1";
};

export const normalizeCharacterGame = (source = {}) => ({
  systemId: Number(source.systemId ?? source.system_id) || null,
  universeId: Number(source.universeId ?? source.universe_id) || null,
  systemName: String(source.systemName ?? source.system_name ?? ""),
  universeName: String(source.universeName ?? source.universe_name ?? ""),
  active: isActive(source.active ?? source.is_active),
});

export const createCharacterCatalogApiClient = (client = jsonApiClient) => ({
  async listGames(options = {}) {
    const payload = await client.request("/games", options);
    return gamesFrom(payload)
      .map(normalizeCharacterGame)
      .filter((game) => game.active && game.systemId && game.universeId);
  },
});

export const characterCatalogApiClient = createCharacterCatalogApiClient();

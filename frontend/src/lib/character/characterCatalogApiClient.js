import { createGameCatalogApiClient } from "@/lib/catalog/gameCatalogApiClient";

export const normalizeCharacterGame = (game = {}) => ({
  systemId: Number(game.systemId) || null,
  universeId: Number(game.universeId) || null,
  systemName: String(game.systemName || ""),
  universeName: String(game.universeName || ""),
  active: game.active !== false,
});

export const createCharacterCatalogApiClient = (client) => {
  const catalog = createGameCatalogApiClient(client);
  return {
    async listGames(options = {}) {
      return (await catalog.listGames(options)).map(normalizeCharacterGame);
    },
  };
};

export const characterCatalogApiClient = createCharacterCatalogApiClient();

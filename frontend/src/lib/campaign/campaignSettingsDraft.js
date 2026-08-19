export const defaultTableSettings = Object.freeze({
  tableVisibility: "invite_only",
  diceVisibility: "public",
  allowPlayerDrawing: false,
  allowPlayerTokenMovement: true,
  autoOpenLastScene: true,
  showPlayerCursors: true,
  defaultGridSize: 50,
});

export const campaignSettingsDraft = (campaign = {}) => ({
  name: String(campaign.name || ""),
  description: String(campaign.description || ""),
  bannerUrl: String(campaign.bannerUrl || ""),
  status: String(campaign.status || "active"),
  systemId: Number(campaign.systemId) || null,
  universeId: Number(campaign.universeId) || null,
  settings: {
    ...defaultTableSettings,
    ...(campaign.settings || {}),
  },
});

export const systemsFromGames = (games = []) => {
  const systems = new Map();
  for (const game of games) {
    if (!game.systemId || systems.has(game.systemId)) continue;
    systems.set(game.systemId, {
      id: Number(game.systemId),
      code: String(game.systemCode || ""),
      name: String(game.systemName || ""),
    });
  }
  return [...systems.values()].sort((left, right) =>
    left.name.localeCompare(right.name),
  );
};

export const worldsForSystem = (games = [], systemId) =>
  games
    .filter((game) => Number(game.systemId) === Number(systemId))
    .map((game) => ({
      id: Number(game.universeId),
      code: String(game.universeCode || ""),
      name: String(game.universeName || ""),
    }))
    .sort((left, right) => left.name.localeCompare(right.name));

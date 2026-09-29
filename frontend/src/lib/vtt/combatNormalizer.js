import { normalizeToken } from "./tokenNormalizer";

const number = (value, fallback = 0) => {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
};

export const emptyCombat = (sceneId = null) => ({
  id: null,
  sceneId: sceneId === null ? null : number(sceneId),
  active: false,
  round: 0,
  turnIndex: 0,
  activeTokenId: null,
  combatants: [],
  revision: 0,
});

export const normalizeCombat = (source = {}, fallbackSceneId = null) => ({
  id: source.id ? number(source.id) : null,
  sceneId: source.sceneId
    ? number(source.sceneId)
    : fallbackSceneId === null
      ? null
      : number(fallbackSceneId),
  active: source.active === true,
  round: Math.max(0, number(source.round)),
  turnIndex: Math.max(0, number(source.turnIndex)),
  activeTokenId: source.activeTokenId ? number(source.activeTokenId) : null,
  combatants: (source.combatants || []).map((combatant) => ({
    id: number(combatant.id),
    tokenId: number(combatant.tokenId),
    initiative: number(combatant.initiative),
    sortOrder: number(combatant.sortOrder),
    hidden: combatant.hidden === true,
    defeated: combatant.defeated === true,
    token: normalizeToken(combatant.token),
  })),
  revision: Math.max(0, number(source.revision)),
});

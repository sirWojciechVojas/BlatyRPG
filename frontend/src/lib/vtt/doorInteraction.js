const positiveId = (value) => {
  const id = Number(value);
  return Number.isSafeInteger(id) && id > 0 ? id : null;
};

export const wallDoorType = (wall = {}) => {
  const type = String(wall.doorType || wall.type || "none").toLowerCase();
  return ["door", "secret", "window"].includes(type) ? type : "none";
};

export const selectedDoorTokens = (
  tokens = [],
  selectedTokenId = null,
  selectedTokenIds = [],
) => {
  const selected =
    Array.isArray(selectedTokenIds) && selectedTokenIds.length
      ? selectedTokenIds
      : selectedTokenId === null || selectedTokenId === undefined
        ? []
        : [selectedTokenId];
  const ids = new Set(selected.map(positiveId).filter(Boolean));
  return tokens.filter((token) => ids.has(positiveId(token?.id)));
};

export const isOtherUsersCharacterToken = (
  token,
  characters = [],
  currentUserId = null,
) => {
  const characterId = positiveId(token?.characterId);
  if (!characterId) return false;
  const character = characters.find(
    (item) => positiveId(item?.id) === characterId,
  );
  const ownerUserId = positiveId(character?.ownerUserId);
  if (!ownerUserId) return false;
  const userId = positiveId(currentUserId);
  return userId === null || ownerUserId !== userId;
};

export const secretDoorBlockedBySelection = (
  wall,
  selectedTokens = [],
  characters = [],
  currentUserId = null,
) =>
  wallDoorType(wall) === "secret" &&
  selectedTokens.some((token) =>
    isOtherUsersCharacterToken(token, characters, currentUserId),
  );

export const actingTokenIds = (tokens = []) => [
  ...new Set(tokens.map((token) => positiveId(token?.id)).filter(Boolean)),
];

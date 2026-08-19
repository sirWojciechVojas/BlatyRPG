const STORAGE_KEY = "blatyrpg.shop.development-access.v1";

const hasWindow = () => typeof window !== "undefined";

export const getShopAccessSession = () => {
  if (!hasWindow()) return null;
  try {
    const value = JSON.parse(
      window.localStorage.getItem(STORAGE_KEY) || "null",
    );
    if (!value || !["gm", "player"].includes(value.mode)) return null;
    if (value.mode === "player" && !String(value.ownerCode || "").trim()) {
      return null;
    }
    return {
      mode: value.mode,
      ownerCode: String(value.ownerCode || "")
        .trim()
        .toUpperCase(),
      characterId: Number(value.characterId) || null,
      name: String(value.name || ""),
      playerId: String(value.playerId || ""),
      playerLabel: String(value.playerLabel || ""),
    };
  } catch (error) {
    return null;
  }
};

export const setShopAccessSession = (value) => {
  if (!hasWindow()) return;
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
  window.dispatchEvent(
    new CustomEvent("shop-access-changed", { detail: value }),
  );
};

export const shopAccessHeaders = () => {
  const session = getShopAccessSession();
  if (!session) return {};
  return {
    "X-Shop-Access-Mode": session.mode,
    "X-Shop-View-Mode": "character",
    ...(session.ownerCode ? { "X-Shop-Owner-Code": session.ownerCode } : {}),
    ...(session.characterId
      ? { "X-Shop-Character-Id": String(session.characterId) }
      : {}),
  };
};

export default getShopAccessSession;

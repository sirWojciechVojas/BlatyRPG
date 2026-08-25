export const TOKEN_PERMISSION_MODES = Object.freeze([
  "gm",
  "users",
  "everyone",
  "inherit",
]);

const fallbackScope = (mode) => ({ mode, userIds: [] });

const parsedScope = (value) => {
  if (typeof value !== "string") return value;
  try {
    return JSON.parse(value);
  } catch (_error) {
    return null;
  }
};

export const normalizeTokenPermissionScope = (value, fallback = "gm") => {
  const source = parsedScope(value);
  if (!source || typeof source !== "object" || Array.isArray(source)) {
    return fallbackScope(fallback);
  }
  const mode = String(source.mode || "").toLocaleLowerCase();
  if (!TOKEN_PERMISSION_MODES.includes(mode)) return fallbackScope(fallback);
  const userIds = Array.isArray(source.userIds)
    ? [
        ...new Set(
          source.userIds
            .map(Number)
            .filter((id) => Number.isSafeInteger(id) && id > 0),
        ),
      ].sort((left, right) => left - right)
    : [];
  if (mode === "users" && !userIds.length) return fallbackScope(fallback);
  return { mode, userIds: mode === "users" ? userIds : [] };
};

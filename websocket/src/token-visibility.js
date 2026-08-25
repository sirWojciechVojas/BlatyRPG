const normalizedScope = (value) => {
  if (value === undefined || value === null) {
    return { mode: "everyone", userIds: [] };
  }
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return { mode: "gm", userIds: [] };
  }
  const mode = String(value.mode || "").toLowerCase();
  if (mode === "everyone" || mode === "inherit") return { mode, userIds: [] };
  if (mode !== "users" || !Array.isArray(value.userIds)) {
    return { mode: "gm", userIds: [] };
  }
  const userIds = value.userIds
    .map(Number)
    .filter((id) => Number.isSafeInteger(id) && id > 0)
    .slice(0, 200);
  return userIds.length
    ? { mode, userIds: [...new Set(userIds)] }
    : { mode: "gm", userIds: [] };
};

export const canReceiveToken = (recipient, result) => {
  if (recipient.capabilities?.canManage === true) return true;
  const scope = normalizedScope(result.token?.visibleTo);
  if (scope.mode === "gm") return false;
  if (
    result.publishToPlayers !== true &&
    recipient.capabilities?.canViewHidden !== true
  ) {
    return false;
  }
  return scope.mode !== "users" || scope.userIds.includes(recipient.userId);
};

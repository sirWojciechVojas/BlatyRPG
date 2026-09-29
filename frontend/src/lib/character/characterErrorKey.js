export const characterErrorKey = (error, scope) => {
  if (error?.status === 401) return "characters.errors.session";
  if (error?.status === 403) return "characters.errors.forbidden";
  if (error?.status === 409 || error?.code === "character_conflict") {
    return "characters.errors.conflict";
  }
  if (error?.network) return "characters.errors.network";
  return `characters.errors.${scope}`;
};

const normalize = (value) =>
  String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/gu, "")
    .toLocaleLowerCase()
    .replace(/[^a-z0-9]+/gu, "");

export const matchingSpellIngredients = (spell, inventory = []) => {
  const expected = normalize(spell?.ingredient?.name);
  if (!expected) return [];
  return inventory.filter((item) => {
    const actual = normalize(item?.name);
    return (
      actual &&
      (actual === expected ||
        actual.includes(expected) ||
        expected.includes(actual))
    );
  });
};

export const spellMatchesFilters = (
  spell,
  { search = "", kind = "all", favorites = false } = {},
) => {
  const phrase = normalize(search);
  return (
    (!phrase ||
      normalize(spell?.name).includes(phrase) ||
      normalize(spell?.effect).includes(phrase)) &&
    (kind === "all" || spell?.targetType === kind) &&
    (!favorites || spell?.favorite === true)
  );
};

export const newIdempotencyKey = (prefix = "magic") => {
  const uuid = window.crypto?.randomUUID?.();
  if (uuid) return `${prefix}:${uuid}`;
  return `${prefix}:${Date.now().toString(36)}:${Math.random()
    .toString(36)
    .slice(2, 14)}`;
};

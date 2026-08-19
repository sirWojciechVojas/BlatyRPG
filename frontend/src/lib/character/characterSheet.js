const cloneObject = (value) => {
  try {
    const cloned = JSON.parse(JSON.stringify(value || {}));
    return cloned && typeof cloned === "object" && !Array.isArray(cloned)
      ? cloned
      : {};
  } catch (_error) {
    return {};
  }
};

const ensureObject = (container, key) => {
  if (
    !container[key] ||
    typeof container[key] !== "object" ||
    Array.isArray(container[key])
  ) {
    container[key] = {};
  }
  return container[key];
};

export const createCharacterDraft = (character = {}) => {
  const data = cloneObject(character.data);
  ensureObject(data, "details");
  const attributes = ensureObject(data, "attributes");
  ensureObject(attributes, "actual");
  if (!Array.isArray(attributes.skills)) attributes.skills = [];
  if (!Array.isArray(attributes.talents)) attributes.talents = [];

  return {
    name: String(character.name || ""),
    avatarUrl: String(character.avatarUrl || ""),
    data,
    revision: Math.max(1, Number(character.revision) || 1),
    updatedAt: character.updatedAt || null,
  };
};

export const humanizeCharacterKey = (key = "") =>
  String(key)
    .replace(/_/gu, " ")
    .replace(/\b\p{L}/gu, (letter) => letter.toUpperCase());

export const listToText = (items) =>
  (Array.isArray(items) ? items : []).map(String).join("\n");

export const textToList = (value) =>
  String(value || "")
    .split(/\r?\n/u)
    .map((item) => item.trim())
    .filter(Boolean);

export const parseCharacterJson = (raw) => {
  try {
    const parsed = JSON.parse(String(raw || "{}"));
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
      return { ok: false, error: "json_object_required" };
    }
    return { ok: true, data: cloneObject(parsed) };
  } catch (_error) {
    return { ok: false, error: "invalid_json" };
  }
};

export const serializeCharacterData = (data) =>
  JSON.stringify(data || {}, null, 2);

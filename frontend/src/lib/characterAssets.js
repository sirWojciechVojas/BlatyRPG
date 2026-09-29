export const CHARACTER_ASSET_TYPES = [
  "avatar",
  "portrait",
  "token",
  "fullbody",
];

export const resolveCharacterAssetSource = (source, type = "avatar") => {
  const candidates =
    source && typeof source === "object" && !Array.isArray(source)
      ? [
          source.assets?.[type],
          source[type],
          source[`${type}Url`],
          source[`${type}_url`],
          source,
        ]
      : [source];
  for (const selected of candidates) {
    const resolved = resolveAssetReference(selected, type);
    if (resolved) return resolved;
  }
  return "";
};

const resolveAssetReference = (selected, type) => {
  if (selected && typeof selected === "object") {
    const variant = {
      avatar: "avatar-md",
      portrait: "portrait-card",
      token: "token",
      fullbody: "portrait-large",
    }[type];
    return String(selected.url || selected.variants?.[variant] || "").trim();
  }
  const value = String(selected || "").trim();
  if (!value) return "";
  if (/^(?:https?:|data:image\/|blob:|\/)/iu.test(value)) return value;
  return "";
};

import { resolveCharacterAssetSource } from "@/lib/characterAssets";

const initialsFor = (name = "") => {
  const parts = String(name || "")
    .trim()
    .split(/\s+/u)
    .filter(Boolean);
  return (
    parts.length > 1
      ? `${parts[0][0]}${parts[parts.length - 1][0]}`
      : parts[0]?.slice(0, 2) || "?"
  ).toUpperCase();
};

const colorFor = (value = "") => {
  const hash = Array.from(String(value)).reduce(
    (result, character) => (result * 31 + character.charCodeAt(0)) >>> 0,
    0,
  );
  return hash % 360;
};

export const createCharacterInitialsAvatar = (name = "") => {
  const initials = initialsFor(name);
  const hue = colorFor(name);
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="hsl(${hue} 35% 30%)"/><stop offset="1" stop-color="hsl(${hue} 45% 12%)"/></linearGradient></defs><rect width="128" height="128" rx="16" fill="url(#g)"/><circle cx="64" cy="43" r="23" fill="#d6b985" opacity=".72"/><path d="M22 116c5-28 20-43 42-43s37 15 42 43" fill="#d6b985" opacity=".72"/><text x="64" y="120" text-anchor="middle" fill="#fff5dd" font-family="serif" font-size="20" font-weight="700">${initials}</text></svg>`;
  return `data:image/svg+xml,${encodeURIComponent(svg)}`;
};

export const resolveCharacterAvatar = (source = "", name = "") => {
  return (
    resolveCharacterAssetSource(source, "avatar") ||
    createCharacterInitialsAvatar(name)
  );
};

export const resolveCharacterPortrait = (
  portraitSource = "",
  avatarSource = "",
  name = "",
) => {
  return (
    resolveCharacterAssetSource(portraitSource, "portrait") ||
    resolveCharacterAvatar(avatarSource, name)
  );
};

export const resolveCharacterToken = (
  tokenSource = "",
  portraitSource = "",
  avatarSource = "",
  name = "",
) => {
  return (
    resolveCharacterAssetSource(tokenSource, "token") ||
    resolveCharacterPortrait(portraitSource, avatarSource, name)
  );
};

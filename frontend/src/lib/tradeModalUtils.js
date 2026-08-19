export const formatCoin = (brass) => {
  const total = Number(brass) || 0;
  const crown = Math.floor(total / 240);
  const shilling = Math.floor((total % 240) / 12);
  const penny = total % 12;
  return {
    crown: `${crown} zk`,
    shilling: `${shilling} s`,
    brass: `${penny} p`,
  };
};

export const toNumber = (value, fallback = 0) => {
  const num = Number(value);
  return Number.isFinite(num) ? num : fallback;
};

export const clampQuantity = (value, min = 1, max = null) => {
  const numeric = toNumber(value, min);
  const floorApplied = Math.max(min, numeric);
  if (max === null || max === undefined) {
    return floorApplied;
  }
  return Math.max(min, Math.min(floorApplied, max));
};

export const shortDescription = (text, maxLength = 24) => {
  const value = text || "";
  if (value.length <= maxLength) {
    return value;
  }
  return `${value.slice(0, maxLength)}...`;
};

const firstFilledValue = (item, keys = []) => {
  if (!item || typeof item !== "object") {
    return "";
  }
  for (const key of keys) {
    if (!Object.prototype.hasOwnProperty.call(item, key)) {
      continue;
    }
    const value = item[key];
    if (value === null || value === undefined) {
      continue;
    }
    const text = String(value).trim();
    if (text) {
      return text;
    }
  }
  return "";
};

const looksLikeLegacyIcon = (value) => {
  const text = String(value || "").trim();
  return /^v?\d{1,4}$/i.test(text) || /^v\d{4}$/i.test(text);
};

const looksLikeImagePath = (value) => {
  const text = String(value || "").trim();
  if (!text || looksLikeLegacyIcon(text)) {
    return false;
  }
  return (
    /^(https?:)?\/\//i.test(text) ||
    /^data:image\//i.test(text) ||
    text.startsWith("/") ||
    text.startsWith("./") ||
    text.startsWith("../") ||
    /\.(avif|gif|jpe?g|png|svg|webp)(\?.*)?$/i.test(text)
  );
};

export const resolveItemImageSource = (item = {}) => {
  const direct = firstFilledValue(item, [
    "IMAGE",
    "image",
    "IMAGE_URL",
    "imageUrl",
    "IMAGE_PATH",
    "image_path",
    "imagePath",
    "THUMBNAIL",
    "thumbnail",
    "THUMBNAIL_URL",
    "thumbnailUrl",
    "SPRITE_URL",
    "spriteUrl",
    "ICON_URL",
    "iconUrl",
  ]);
  if (looksLikeImagePath(direct)) {
    return direct;
  }
  const icon = firstFilledValue(item, ["ICON", "icon", "sprite", "SPRITE"]);
  return looksLikeImagePath(icon) ? icon : "";
};

export const resolveItemIconToken = (item = {}) =>
  firstFilledValue(item, [
    "IMG_CLASS",
    "imgClass",
    "iconClass",
    "ICON_CLASS",
    "ICON",
    "icon",
    "ASSET_ID",
    "asset_id",
    "assetId",
    "SPRITE",
    "sprite",
    "THUMBNAIL",
    "thumbnail",
  ]);

export const normalizeLegacyIconClass = (
  rawClass,
  { pattern, max, namedPositions, fallback = "v0001" },
) => {
  const value = String(rawClass || "")
    .trim()
    .toLowerCase();
  if (!value) {
    return fallback;
  }
  if (pattern.test(value)) {
    const numeric = Number(value.slice(1));
    if (Number.isFinite(numeric) && numeric >= 1 && numeric <= max) {
      return `v${String(numeric).padStart(4, "0")}`;
    }
  }
  if (namedPositions[value]) {
    return value;
  }
  const numeric = value.replace(/^v/i, "");
  if (/^\d{1,4}$/.test(numeric)) {
    const parsed = Number(numeric);
    if (Number.isFinite(parsed) && parsed >= 1 && parsed <= max) {
      return `v${String(parsed).padStart(4, "0")}`;
    }
  }
  return fallback;
};

export const nextIdFromItems = (items, fallback = 0) => {
  const ids = (items || [])
    .map((item) => Number(item.ID))
    .filter(Number.isFinite);
  const maxId = ids.length ? Math.max(...ids) : fallback;
  return maxId + 1;
};

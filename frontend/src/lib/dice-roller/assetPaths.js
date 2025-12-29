const DEFAULT_ASSET_BASE_URL = "/dice_roller";

const isAbsoluteUrl = (url) =>
  /^([a-z][a-z\d+\-.]*:)?\/\//i.test(url) ||
  url.startsWith("data:") ||
  url.startsWith("blob:");

const normalizeBaseUrl = (baseUrl = DEFAULT_ASSET_BASE_URL) =>
  (baseUrl || DEFAULT_ASSET_BASE_URL).replace(/\/+$/, "");

const resolveAssetPath = (baseUrl, assetPath) => {
  if (!assetPath) return assetPath;
  if (isAbsoluteUrl(assetPath) || assetPath.startsWith("/")) return assetPath;
  const normalizedBase = normalizeBaseUrl(baseUrl);
  const trimmedPath = assetPath.replace(/^\.\//, "");
  return `${normalizedBase}/${trimmedPath}`;
};

const resolveTextureList = (textureList, baseUrl) => {
  const resolved = {};
  Object.entries(textureList).forEach(([key, value]) => {
    resolved[key] = {
      ...value,
      source: resolveAssetPath(baseUrl, value.source),
      bump: resolveAssetPath(baseUrl, value.bump),
    };
  });
  return resolved;
};

export {
  DEFAULT_ASSET_BASE_URL,
  normalizeBaseUrl,
  resolveAssetPath,
  resolveTextureList,
};

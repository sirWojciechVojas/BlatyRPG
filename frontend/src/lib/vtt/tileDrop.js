import { clamp } from "./grid";

export const TILE_ASSET_MIME = "application/x-blatyrpg-map-asset";

export const inferTileMediaType = (url) =>
  /\.(?:webm|mp4|ogv)(?:[?#].*)?$/iu.test(String(url || ""))
    ? "video"
    : "image";

export const writeTileAssetDrag = (dataTransfer, asset) => {
  if (!dataTransfer || !asset?.url) return false;
  dataTransfer.effectAllowed = "copy";
  dataTransfer.setData(
    TILE_ASSET_MIME,
    JSON.stringify({
      name: String(asset.label || "Tile").slice(0, 150),
      assetUrl: String(asset.url),
      mediaType: inferTileMediaType(asset.url),
    }),
  );
  return true;
};

export const readDroppedTileAsset = (dataTransfer) => {
  try {
    const value = JSON.parse(dataTransfer?.getData(TILE_ASSET_MIME) || "");
    return value?.assetUrl ? value : null;
  } catch (_error) {
    return null;
  }
};

export const tileDraftFromAsset = (asset, position, scene) => {
  const grid = Math.max(8, Number(scene?.gridSize) || 100);
  return {
    ...asset,
    layer: "background",
    x: clamp(Number(position.x) || 0, 0, Number(scene?.width) || 0),
    y: clamp(Number(position.y) || 0, 0, Number(scene?.height) || 0),
    width: grid * 4,
    height: grid * 4,
  };
};

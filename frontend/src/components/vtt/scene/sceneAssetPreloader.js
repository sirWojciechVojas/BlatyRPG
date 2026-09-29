import {
  sceneAssetApiClient,
  sceneAssetLocation,
} from "@/lib/vtt/sceneAssetApiClient";
import { resolveAccessToken } from "@/lib/api/jsonApiClient";

const protectedAsset = (src) =>
  /\/(?:api\/)?(?:admin\/)?token-template-assets\/\d+\/file(?:[?#]|$)/u.test(
    src,
  ) ||
  /\/(?:api\/)?campaigns\/\d+\/token-template-assets\/\d+\/file(?:[?#]|$)/u.test(
    src,
  ) ||
  /\/(?:api\/)?campaigns\/\d+\/maps\/assets\/\d+\/file(?:[?#]|$)/u.test(src) ||
  /\/(?:api\/)?profession-assets\/\d+\/file(?:[?#]|$)/u.test(src);

const requestUrl = (src) => {
  if (/^https?:\/\//iu.test(src) || src.startsWith("/api/")) return src;
  const base = String(process.env.VUE_APP_API_BASE || "/api").replace(
    /\/+$/u,
    "",
  );
  return `${base}/${src.replace(/^\/+/, "")}`;
};

const loadImage = (src) =>
  new Promise((resolve, reject) => {
    const image = new Image();
    image.decoding = "async";
    image.onload = async () => {
      try {
        await image.decode?.();
      } catch (_error) {
        // A decoded image may report an unsupported format after it rendered.
      }
      resolve();
    };
    image.onerror = reject;
    image.src = src;
  });

const loadVideo = (src) =>
  new Promise((resolve, reject) => {
    const video = document.createElement("video");
    video.preload = "auto";
    video.muted = true;
    video.playsInline = true;
    video.onloadeddata = resolve;
    video.onerror = reject;
    video.src = src;
    video.load();
  });

const imageSource = async (src) => {
  const location = sceneAssetLocation(src);
  if (location) {
    const blob = await sceneAssetApiClient.fetchBlobFromUrl(src);
    return { src: URL.createObjectURL(blob), objectUrl: true };
  }
  if (!protectedAsset(src)) return { src, objectUrl: false };

  const token = resolveAccessToken();
  const response = await window.fetch(requestUrl(src), {
    headers: {
      Accept: "image/*",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    credentials: "same-origin",
  });
  if (!response.ok) throw new Error(`http_${response.status}`);
  return { src: URL.createObjectURL(await response.blob()), objectUrl: true };
};

export const preloadSceneAsset = async (asset = {}) => {
  const source = String(asset.src || "").trim();
  if (!source) return { status: "ready" };

  let objectUrl = "";
  try {
    if (asset.type === "video") await loadVideo(source);
    else {
      const resolved = await imageSource(source);
      objectUrl = resolved.objectUrl ? resolved.src : "";
      await loadImage(resolved.src);
    }
    return { status: "ready" };
  } catch (_error) {
    return { status: "error" };
  } finally {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
  }
};

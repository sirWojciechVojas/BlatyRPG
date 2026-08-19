export const CHARACTER_ASSET_TYPES = [
  "avatar",
  "portrait",
  "token",
  "fullbody",
];

const transformations = {
  avatar: "f_auto,q_auto,c_fill,g_auto,w_128,h_128",
  portrait: "f_auto,q_auto,c_fill,g_auto,w_512,h_768",
  token: "f_auto,q_auto,c_fill,g_auto,w_256,h_256",
  fullbody: "f_auto,q_auto,c_fit,w_1024,h_1536",
};

const cloudName = String(
  process.env.VUE_APP_CLOUDINARY_CLOUD_NAME || "dajzxmjyc",
).trim();

const encodePublicId = (publicId) =>
  String(publicId || "")
    .replace(/^\/+|\/+$/gu, "")
    .split("/")
    .map(encodeURIComponent)
    .join("/");

export const buildCharacterAssetUrl = (publicId, type = "avatar") => {
  const normalizedType = CHARACTER_ASSET_TYPES.includes(type) ? type : "avatar";
  const encodedPublicId = encodePublicId(publicId);
  if (!encodedPublicId || !cloudName) return "";
  return `https://res.cloudinary.com/${encodeURIComponent(
    cloudName,
  )}/image/upload/${transformations[normalizedType]}/${encodedPublicId}`;
};

export const resolveCharacterAssetSource = (source, type = "avatar") => {
  const selected = source?.assets?.[type] ?? source?.[type] ?? source;
  if (selected && typeof selected === "object") {
    const generated = buildCharacterAssetUrl(
      selected.publicId ?? selected.public_id,
      type,
    );
    // The backend-generated URL is preferred; regenerating from publicId keeps
    // the UI usable with cached/older API responses.
    return String(selected.url || generated || "").trim();
  }
  const value = String(selected || "").trim();
  if (!value) return "";
  if (/^(?:https?:|data:image\/|blob:|\/)/iu.test(value)) return value;
  return buildCharacterAssetUrl(
    value.replace(/\.(?:png|jpe?g|webp)$/iu, ""),
    type,
  );
};

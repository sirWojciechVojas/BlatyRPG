const apiBase = String(process.env.VUE_APP_API_BASE || "/api").replace(
  /\/+$/u,
  "",
);

const accessToken = () => {
  if (typeof window === "undefined") return "";
  for (const key of [
    "access_token",
    "blatyrpg.access_token",
    "blatyrpg.jwt",
    "blatyrpg.token",
    "auth_token",
    "token",
    "jwt",
  ]) {
    const value = String(window.localStorage.getItem(key) || "").trim();
    if (value) return value;
  }
  return "";
};

const apiRequest = async (path, options = {}) => {
  const token = accessToken();
  const response = await fetch(`${apiBase}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      ...(options.body ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    },
    credentials: "same-origin",
  });
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    const error = new Error(payload?.code || `http_${response.status}`);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }
  return payload;
};

const sendDirect = async (instruction, file) => {
  if (instruction.encoding === "multipart/form-data") {
    const body = new FormData();
    Object.entries(instruction.fields || {}).forEach(([key, value]) => {
      body.append(key, String(value));
    });
    body.append("file", file);
    const response = await fetch(instruction.url, {
      method: instruction.method,
      headers: instruction.headers || {},
      body,
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload)
      throw new Error(`media_upload_${response.status}`);
    return payload;
  }

  const response = await fetch(instruction.url, {
    method: instruction.method,
    headers: instruction.headers || {},
    body: file,
  });
  if (!response.ok) throw new Error(`media_upload_${response.status}`);
  return {
    eTag: response.headers.get("ETag") || "",
    fileSize: file.size,
  };
};

export const mediaApi = {
  createUpload: (input) =>
    apiRequest("/media/uploads", {
      method: "POST",
      body: JSON.stringify(input),
    }),

  completeUpload: (assetId, uploadResult) =>
    apiRequest(`/media/uploads/${Number(assetId)}/complete`, {
      method: "POST",
      body: JSON.stringify({ uploadResult }),
    }),

  get: (assetId, variant = "") =>
    apiRequest(
      `/media/${Number(assetId)}${
        variant ? `?variant=${encodeURIComponent(variant)}` : ""
      }`,
    ),

  async upload(file, input = {}) {
    const initiated = await this.createUpload({
      ...input,
      filename: file.name,
      mimeType: file.type || "application/octet-stream",
      fileSize: file.size,
    });
    const uploadResult = await sendDirect(initiated.upload, file);
    return this.completeUpload(initiated.asset.id, uploadResult);
  },
};

const TOKEN_KEYS = [
  "access_token",
  "blatyrpg.access_token",
  "blatyrpg.jwt",
  "blatyrpg.token",
  "auth_token",
  "token",
  "jwt",
];

const errorCode = (payload, fallback) => {
  for (const candidate of [
    payload?.code,
    payload?.error,
    payload?.message,
    payload?.messages?.error,
  ]) {
    if (typeof candidate === "string" && candidate.trim()) {
      return candidate.trim();
    }
  }
  return fallback;
};

export class JsonApiError extends Error {
  constructor(code, options = {}) {
    super(code);
    this.name = "JsonApiError";
    this.code = code;
    this.status = Number(options.status || 0);
    this.payload = options.payload ?? null;
    this.network = options.network === true;
  }
}

export const resolveAccessToken = (storage) => {
  let source = storage;
  if (!source && typeof window !== "undefined") {
    source = window.localStorage;
  }
  if (!source) return "";

  try {
    for (const key of TOKEN_KEYS) {
      const token = String(source.getItem(key) || "").trim();
      if (token) return token;
    }
  } catch (_error) {
    return "";
  }
  return "";
};

const joinUrl = (baseUrl, path) =>
  `${String(baseUrl || "").replace(/\/+$/u, "")}/${String(path || "").replace(
    /^\/+/,
    "",
  )}`;

export const createJsonApiClient = (options = {}) => {
  const baseUrl = options.baseUrl || process.env.VUE_APP_API_BASE || "/api";
  const tokenResolver = options.tokenResolver || resolveAccessToken;

  return {
    async request(path, requestOptions = {}) {
      const fetchImpl = options.fetchImpl || window.fetch.bind(window);
      const token = tokenResolver();
      const hasBody = requestOptions.body !== undefined;
      const body =
        hasBody && typeof requestOptions.body !== "string"
          ? JSON.stringify(requestOptions.body)
          : requestOptions.body;
      const headers = {
        Accept: "application/json",
        ...(hasBody ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(requestOptions.headers || {}),
      };

      let response;
      try {
        response = await fetchImpl(joinUrl(baseUrl, path), {
          method: requestOptions.method || "GET",
          headers,
          body,
          credentials: requestOptions.credentials || "same-origin",
          signal: requestOptions.signal,
        });
      } catch (cause) {
        throw new JsonApiError("network_error", {
          network: true,
          payload: cause,
        });
      }

      const raw = await response.text();
      let payload = null;
      if (raw) {
        try {
          payload = JSON.parse(raw);
        } catch (_error) {
          throw new JsonApiError("invalid_json", {
            status: response.status,
            payload: raw,
          });
        }
      }
      if (!response.ok) {
        const fallback = `http_${response.status}`;
        throw new JsonApiError(errorCode(payload, fallback), {
          status: response.status,
          payload,
        });
      }
      return payload;
    },
  };
};

export const isJsonApiAuthorizationError = (error) =>
  error?.status === 401 || error?.status === 403;

export const jsonApiClient = createJsonApiClient();

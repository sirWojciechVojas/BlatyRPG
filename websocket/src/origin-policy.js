const normalize = (origin) => {
  try {
    const parsed = new URL(origin);
    if (!["http:", "https:"].includes(parsed.protocol)) return null;
    return parsed.origin;
  } catch {
    return null;
  }
};

export const isOriginAllowed = (origin, allowedOrigins, allowMissing = false) => {
  if (!origin) return allowMissing;
  const normalized = normalize(origin);
  return normalized !== null && allowedOrigins.includes(normalized);
};

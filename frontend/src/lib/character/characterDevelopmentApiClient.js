import { jsonApiClient } from "@/lib/api/jsonApiClient";

const positiveId = (value, name) => {
  const id = Number(value);
  if (!Number.isInteger(id) || id < 1) throw new TypeError(`${name}_required`);
  return id;
};

const normalizeDefinition = (source = {}) => ({
  id: Number(source.id) || null,
  name: String(source.name || "").trim(),
  description: String(source.description || "").trim(),
  category: String(source.category || "").trim(),
  metadata:
    source.metadata && typeof source.metadata === "object"
      ? source.metadata
      : {},
});

export const createCharacterDevelopmentApiClient = (
  client = jsonApiClient,
) => ({
  async definitions(systemId, category, options = {}) {
    const payload = await client.request(
      `/systems/${positiveId(systemId, "system_id")}/data?category=${encodeURIComponent(
        String(category || ""),
      )}`,
      { signal: options.signal },
    );
    return (Array.isArray(payload?.items) ? payload.items : [])
      .map(normalizeDefinition)
      .filter((item) => item.id && item.name);
  },
});

export const characterDevelopmentApiClient =
  createCharacterDevelopmentApiClient();

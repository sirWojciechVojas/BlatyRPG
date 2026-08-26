import { jsonApiClient } from "@/lib/api/jsonApiClient";
import { normalizeMovementRequest } from "./tokenMovementRequest";

const segment = (value) => encodeURIComponent(String(value));

export const createTokenMovementRequestApiClient = (
  client = jsonApiClient,
) => ({
  async list(campaignId, options = {}) {
    const payload = await client.request(
      `/campaigns/${segment(campaignId)}/token-movement-requests`,
      options,
    );
    return {
      items: (payload?.items || []).map(normalizeMovementRequest),
      capabilities: payload?.capabilities || { canResolve: false },
    };
  },
});

export const tokenMovementRequestApiClient =
  createTokenMovementRequestApiClient();

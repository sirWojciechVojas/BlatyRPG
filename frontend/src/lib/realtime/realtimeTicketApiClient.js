import { campaignApiClient } from "@/lib/campaign/campaignApiClient";

export const createRealtimeTicketApiClient = (
  campaigns = campaignApiClient,
) => ({
  async issue(campaignId, clientInstanceId) {
    const payload = await campaigns.realtimeTicket(
      campaignId,
      clientInstanceId,
    );
    const data = payload?.data ?? payload;
    const ticket = String(data?.ticket || "");
    if (!ticket) throw new TypeError("invalid_realtime_ticket_response");
    return {
      ticket,
      expiresAt: data?.expiresAt ?? data?.expires_at ?? null,
    };
  },
});

export const realtimeTicketApiClient = createRealtimeTicketApiClient();

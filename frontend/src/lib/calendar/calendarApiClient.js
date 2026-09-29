import { jsonApiClient } from "@/lib/api/jsonApiClient";

const positiveId = (value, name) => {
  const id = Number(value);
  if (!Number.isInteger(id) || id < 1) throw new TypeError(`${name}_invalid`);
  return id;
};

const base = (campaignId) =>
  `/campaigns/${positiveId(campaignId, "campaignId")}/calendar`;

const queryString = (query = {}) => {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(query)) {
    if (value !== null && value !== undefined && value !== "") {
      params.set(key, String(value));
    }
  }
  const value = params.toString();
  return value ? `?${value}` : "";
};

export const createCalendarApiClient = (client = jsonApiClient) => ({
  get(campaignId) {
    return client.request(base(campaignId));
  },
  setState(campaignId, payload) {
    return client.request(`${base(campaignId)}/state`, {
      method: "PUT",
      body: payload,
    });
  },
  advance(campaignId, payload) {
    return client.request(`${base(campaignId)}/advance`, {
      method: "POST",
      body: payload,
    });
  },
  events(campaignId, range) {
    return client.request(`${base(campaignId)}/events${queryString(range)}`);
  },
  createEvent(campaignId, payload) {
    return client.request(`${base(campaignId)}/events`, {
      method: "POST",
      body: payload,
    });
  },
  updateEvent(campaignId, eventId, payload) {
    return client.request(
      `${base(campaignId)}/events/${positiveId(eventId, "eventId")}`,
      { method: "PATCH", body: payload },
    );
  },
  deleteEvent(campaignId, eventId, payload) {
    return client.request(
      `${base(campaignId)}/events/${positiveId(eventId, "eventId")}`,
      { method: "DELETE", body: payload },
    );
  },
  setMorrslieb(campaignId, payload) {
    return client.request(`${base(campaignId)}/morrslieb`, {
      method: "PUT",
      body: payload,
    });
  },
});

export const calendarApiClient = createCalendarApiClient();

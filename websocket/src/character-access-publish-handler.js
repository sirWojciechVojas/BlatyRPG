import { createHmac, timingSafeEqual } from "node:crypto";
import { createServerEvent, sendEvent } from "./protocol.js";

export const CHARACTER_ACCESS_EVENT_TYPE = "character.access.changed";

const jsonResponse = (response, status, payload) => {
  const body = JSON.stringify(payload);
  response.writeHead(status, {
    "Content-Type": "application/json; charset=utf-8",
    "Content-Length": Buffer.byteLength(body),
    "Cache-Control": "no-store",
  });
  response.end(body);
};

const plainObject = (value) =>
  value !== null && typeof value === "object" && !Array.isArray(value);

const positiveId = (value, name) => {
  if (!Number.isSafeInteger(value) || value < 1) {
    throw new Error(`publication_${name}_invalid`);
  }
  return value;
};

export const parseCharacterAccessPublication = (value) => {
  if (!plainObject(value)) throw new Error("publication_object_required");
  const allowed = ["type", "campaignId", "characterId", "actorUserId"];
  if (Object.keys(value).some((key) => !allowed.includes(key))) {
    throw new Error("publication_unexpected_field");
  }
  if (value.type !== CHARACTER_ACCESS_EVENT_TYPE) {
    throw new Error("publication_type_invalid");
  }
  return {
    type: value.type,
    campaignId: positiveId(value.campaignId, "campaignId"),
    characterId: positiveId(value.characterId, "characterId"),
    actorUserId: positiveId(value.actorUserId, "actorUserId"),
  };
};

export const verifyCharacterAccessPublishSignature = ({
  body,
  timestamp,
  signature,
  secret,
  now = Date.now,
}) => {
  if (!/^\d{10}$/.test(String(timestamp || ""))) return false;
  if (!/^[0-9a-f]{64}$/i.test(String(signature || ""))) return false;
  if (Math.abs(Math.floor(now() / 1000) - Number(timestamp)) > 30) return false;
  const expected = createHmac("sha256", secret)
    .update(`${timestamp}.${body}`)
    .digest();
  const supplied = Buffer.from(String(signature), "hex");
  return supplied.length === expected.length && timingSafeEqual(supplied, expected);
};

const readBody = (request, maxBytes) =>
  new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    request.on("data", (chunk) => {
      size += chunk.length;
      if (size > maxBytes) {
        reject(new Error("publication_too_large"));
        request.destroy();
        return;
      }
      chunks.push(chunk);
    });
    request.on("end", () => resolve(Buffer.concat(chunks).toString("utf8")));
    request.on("error", reject);
  });

/** Publishes a permission-safe invalidation to every connected campaign client. */
export const createCharacterAccessPublishHandler = ({ config, rooms }) => {
  const processRequest = async (request, response) => {
    try {
      const body = await readBody(request, config.maxPayloadBytes);
      const timestamp = request.headers["x-realtime-timestamp"];
      const signature = request.headers["x-realtime-signature"];
      if (
        !verifyCharacterAccessPublishSignature({
          body,
          timestamp,
          signature,
          secret: config.ticketSecret,
        })
      ) {
        jsonResponse(response, 401, { code: "publish_unauthorized" });
        return;
      }
      const publication = parseCharacterAccessPublication(JSON.parse(body));
      const event = createServerEvent({
        type: publication.type,
        campaignId: publication.campaignId,
        sequence: rooms.nextSequence(publication.campaignId),
        actorUserId: publication.actorUserId,
        payload: { characterId: publication.characterId },
      });
      for (const session of rooms.sessions(publication.campaignId)) {
        sendEvent(session.ws, event);
      }
      jsonResponse(response, 202, { accepted: true, eventId: event.eventId });
    } catch (error) {
      jsonResponse(response, 400, {
        code: String(error?.message || "publication_invalid"),
      });
    }
  };

  return (request, response, path) => {
    if (path !== config.characterAccessPublishPath) return false;
    if (request.method !== "POST") {
      jsonResponse(response, 405, { code: "method_not_allowed" });
      return true;
    }
    void processRequest(request, response);
    return true;
  };
};

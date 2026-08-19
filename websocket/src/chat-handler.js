import { BackendChatError } from "./backend-chat-client.js";
import { createServerEvent, sendEvent } from "./protocol.js";

const eventFor = (session, type, payload, actorUserId = session.userId) =>
  createServerEvent({
    type,
    campaignId: session.campaignId,
    sequence: null,
    actorUserId,
    payload,
  });

const modeFor = (request) =>
  request.beforeRevision ? "older" : request.afterRevision ? "missing" : "initial";

export const createChatHandler = ({ backend, rooms, onAuthenticationFailure }) => {
  const campaignWrites = new Map();
  const error = (session, requestId, cause) => {
    const failure =
      cause instanceof BackendChatError
        ? cause
        : new BackendChatError("chat_unavailable", 503);
    if (failure.status === 401) {
      onAuthenticationFailure(session);
      return;
    }
    sendEvent(
      session.ws,
      eventFor(session, "chat.error", {
        requestId,
        code: failure.code,
        status: failure.status,
        ...(failure.details?.errors ? { errors: failure.details.errors } : {}),
        ...(failure.details?.retryAfter
          ? { retryAfter: failure.details.retryAfter }
          : {}),
      }),
    );
  };

  const canReceive = async (recipient, revision) => {
    try {
      const result = await backend.sync(recipient, {
        beforeRevision: revision + 1,
        limit: 1,
      });
      return result.items.some((item) => item.revision === revision);
    } catch {
      return false;
    }
  };

  const publish = async (sender, result, request) => {
    const committed = result.message;
    const event = eventFor(
      sender,
      "chat.message",
      { message: committed, revision: committed.revision },
      committed.author.id,
    );
    const recipients = rooms.sessions(sender.campaignId);
    await Promise.all(
      recipients.map(async (recipient) => {
        if (recipient.id === sender.id || (await canReceive(recipient, committed.revision))) {
          sendEvent(recipient.ws, event);
        }
      }),
    );
    sendEvent(
      sender.ws,
      eventFor(sender, "chat.ack", {
        requestId: request.requestId,
        clientNonce: request.clientNonce,
        messageId: committed.id,
        revision: committed.revision,
        duplicate: result.duplicate,
        capabilities: result.capabilities,
      }),
    );
  };

  const synchronize = async (session, request) => {
    const result = await backend.sync(session, request);
    sendEvent(
      session.ws,
      eventFor(session, "chat.snapshot", {
        requestId: request.requestId,
        mode: modeFor(request),
        ...result,
      }),
    );
  };

  const orderedSend = (session, request) => {
    const campaignId = session.campaignId;
    const previous = campaignWrites.get(campaignId) || Promise.resolve();
    const current = previous
      .catch(() => {})
      .then(() => backend.send(session, request))
      .then((result) => publish(session, result, request));
    campaignWrites.set(campaignId, current);
    const cleanup = () => {
      if (campaignWrites.get(campaignId) === current) campaignWrites.delete(campaignId);
    };
    current.then(cleanup, cleanup);
    return current;
  };

  const handle = (session, request) => {
    const operation =
      request.type === "chat.send"
        ? orderedSend(session, request)
        : synchronize(session, request);
    Promise.resolve(operation).catch((cause) => error(session, request.requestId, cause));
  };

  return { handle };
};

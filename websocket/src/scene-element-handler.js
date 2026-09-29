import { createServerEvent, sendEvent } from "./protocol.js";

const eventFor = (session, type, payload, sequence = null) =>
  createServerEvent({
    type,
    campaignId: session.campaignId,
    sequence,
    actorUserId: session.userId,
    payload,
  });

export const createSceneElementHandler = ({
  backend,
  rooms,
  onAuthenticationFailure,
  resource,
  publicUpdates = false,
  hideHidden = false,
  publicItem = null,
  afterPublish = null,
}) => {
  const writes = new Map();
  const idKey = `${resource}Id`;

  const failure = (session, request, cause) => {
    const status = Number(cause?.status) || 503;
    if (status === 401) {
      onAuthenticationFailure(session);
      return;
    }
    sendEvent(
      session.ws,
      eventFor(session, `${resource}.error`, {
        requestId: request.requestId,
        code: String(cause?.code || `${resource}_unavailable`),
        status,
        ...(cause?.details?.errors ? { errors: cause.details.errors } : {}),
      }),
    );
  };

  const publish = (session, request, result) => {
    const sequence = rooms.nextSequence(session.campaignId);
    const type = result.scene
      ? "scene.updated"
      : result.operation === "delete"
        ? `${resource}.deleted`
        : `${resource}.updated`;
    const item = result[resource];
    const payload = result.scene
      ? { scene: result.scene }
      : item
        ? {
            [resource]: item,
            ...(result.sound ? { sound: String(result.sound) } : {}),
          }
        : { sceneId: result.sceneId, [idKey]: result[idKey] };
    const event = eventFor(session, type, payload, sequence);
    const publicPayload =
      item && typeof publicItem === "function"
        ? {
            [resource]: publicItem(item),
            ...(result.sound ? { sound: String(result.sound) } : {}),
          }
        : payload;
    const publicEvent = eventFor(session, type, publicPayload, sequence);
    const marker = createServerEvent({
      type: "sync.marker",
      campaignId: session.campaignId,
      sequence,
      actorUserId: null,
      payload: {},
    });
    const hiddenEvent = item?.hidden
      ? eventFor(
          session,
          `${resource}.deleted`,
          { sceneId: item.sceneId, [idKey]: item.id },
          sequence,
        )
      : null;
    const hiddenScene =
      result.scene &&
      (result.scene.isVisible === false || result.scene.is_visible === false);
    const recipients = rooms.sessions(session.campaignId);
    for (const recipient of recipients) {
      const canManage =
        recipient.id === session.id ||
        recipient.capabilities?.canManage === true ||
        recipient.capabilities?.canViewHidden === true;
      let recipientEvent = publicUpdates ? publicEvent : marker;
      if (hiddenScene && !canManage) recipientEvent = marker;
      if (hideHidden && result.operation === "delete" && result.hidden) {
        recipientEvent = marker;
      } else if (hideHidden && hiddenEvent) {
        recipientEvent = result.operation === "create" ? marker : hiddenEvent;
      }
      sendEvent(recipient.ws, canManage ? event : recipientEvent);
    }
    sendEvent(
      session.ws,
      eventFor(session, `${resource}.ack`, {
        requestId: request.requestId,
        operation: result.operation,
        [idKey]: item?.id ?? result[idKey] ?? null,
        revision: item?.revision ?? result.scene?.revision ?? null,
      }),
    );
    if (typeof afterPublish === "function") {
      Promise.resolve(afterPublish({ session, request, result, recipients })).catch(
        () => {},
      );
    }
  };

  const handle = (session, request) => {
    const key = `${session.campaignId}:${request.sceneId}:${request[idKey] || request.requestId}`;
    const previous = writes.get(key) || Promise.resolve();
    const current = previous
      .catch(() => {})
      .then(() => backend.change(session, request))
      .then((result) => publish(session, request, result));
    writes.set(key, current);
    const cleanup = () => {
      if (writes.get(key) === current) writes.delete(key);
    };
    current.then(cleanup, cleanup);
    current.catch((cause) => failure(session, request, cause));
  };

  return { handle };
};

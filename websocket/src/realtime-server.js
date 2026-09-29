import { randomUUID } from "node:crypto";
import { WebSocket } from "ws";
import { BackendChatClient } from "./backend-chat-client.js";
import { BackendTokenClient } from "./backend-token-client.js";
import { BackendSceneSnapshotClient } from "./backend-scene-snapshot-client.js";
import { BackendHandoutClient } from "./backend-handout-client.js";
import { createChatHandler } from "./chat-handler.js";
import { createHttpTransport } from "./http-transport.js";
import { routeRealtimeFeature } from "./feature-router.js";
import { PresenceRegistry } from "./presence-registry.js";
import {
  CLOSE_CODES,
  ProtocolError,
  createServerEvent,
  parseAuthMessage,
  parseAuthenticatedMessage,
  parseMessage,
  sendEvent,
} from "./protocol.js";
import { FixedWindowRateLimiter } from "./rate-limiter.js";
import { RoomRegistry } from "./room-registry.js";
import { createSceneFeatureHandlers } from "./scene-feature-handlers.js";
import { TicketError, TicketVerifier } from "./ticket-verifier.js";
import { createTokenHandler } from "./token-handler.js";
import { createFogHandler } from "./fog-handler.js";
import { createHandoutHandler } from "./handout-handler.js";
import { BackendJukeboxClient } from "./backend-jukebox-client.js";
import { createJukeboxHandler } from "./jukebox-handler.js";
import { BackendSoundEffectsClient } from "./backend-sound-effects-client.js";
import { createSoundEffectsHandler } from "./sound-effects-handler.js";
import { BackendTokenSyncClient } from "./backend-token-sync-client.js";
import { createTokenSyncHandler } from "./token-sync-handler.js";
import { createCalendarPublishHandler } from "./calendar-publish-handler.js";
import { createCharacterAccessPublishHandler } from "./character-access-publish-handler.js";

const closeReason = (value) =>
  String(value || "connection_closed").slice(0, 100);

export const createRealtimeServer = (config, dependencies = {}) => {
  const sessions = new Set();
  const rooms = dependencies.rooms || new RoomRegistry();
  const ticketVerifier =
    dependencies.ticketVerifier ||
    new TicketVerifier(config, dependencies.ticketOptions);
  let stopping = false;

  const broadcastPresence = (change) => {
    if (!change) return;
    const eventSequence = rooms.nextSequence(change.campaignId);
    const event = createServerEvent({
      type: "presence.changed",
      campaignId: change.campaignId,
      sequence: eventSequence,
      actorUserId: change.userId,
      payload: { change: change.change, user: change.user },
    });
    for (const session of rooms.sessions(change.campaignId))
      sendEvent(session.ws, event);
  };

  const presence =
    dependencies.presence ||
    new PresenceRegistry({
      graceMs: config.offlineGraceMs,
      onExpired: broadcastPresence,
    });

  const healthResponse = () => ({
    ok: true,
    service: "blatyrpg-websocket",
    protocolVersion: 1,
    connections: sessions.size,
  });

  const calendarPublishHandler = createCalendarPublishHandler({ config, rooms });
  const characterAccessPublishHandler = createCharacterAccessPublishHandler({
    config,
    rooms,
  });
  const { httpServer, wss } = createHttpTransport(
    config,
    healthResponse,
    (request, response, path) =>
      calendarPublishHandler(request, response, path) ||
      characterAccessPublishHandler(request, response, path),
  );

  const sendProtocolError = (session, code, requestId = null) =>
    sendEvent(
      session.ws,
      createServerEvent({
        type: "protocol.error",
        campaignId: session.campaignId || null,
        sequence: session.campaignId
          ? rooms.currentSequence(session.campaignId)
          : null,
        actorUserId: session.userId || null,
        payload: { code, requestId },
      }),
    );

  const closeSession = (session, code, reason, protocolCode = reason) => {
    sendProtocolError(session, protocolCode);
    if (session.ws.readyState === WebSocket.OPEN) {
      session.ws.close(code, closeReason(reason));
    } else if (session.ws.readyState === WebSocket.CONNECTING) {
      session.ws.terminate();
    }
  };

  const chat = createChatHandler({
    backend: dependencies.chatBackend || new BackendChatClient(config),
    rooms,
    onAuthenticationFailure: (session) =>
      closeSession(session, CLOSE_CODES.AUTH, "auth_failed", "unauthorized"),
  });
  const tokens = createTokenHandler({
    backend: dependencies.tokenBackend || new BackendTokenClient(config),
    rooms,
    onAuthenticationFailure: (session) =>
      closeSession(session, CLOSE_CODES.AUTH, "auth_failed", "unauthorized"),
  });
  const tokenSync = createTokenSyncHandler({
    backend:
      dependencies.tokenSyncBackend || new BackendTokenSyncClient(config),
    rooms,
    onAuthenticationFailure: (session) =>
      closeSession(session, CLOSE_CODES.AUTH, "auth_failed", "unauthorized"),
  });
  const snapshots =
    dependencies.snapshotBackend || new BackendSceneSnapshotClient(config);
  const sceneFeatures = createSceneFeatureHandlers(
    config,
    dependencies,
    rooms,
    (session) =>
      closeSession(session, CLOSE_CODES.AUTH, "auth_failed", "unauthorized"),
  );
  const fog = createFogHandler({ rooms });
  const handouts = createHandoutHandler({
    backend: dependencies.handoutBackend || new BackendHandoutClient(config),
    rooms,
    onAuthenticationFailure: (session) =>
      closeSession(session, CLOSE_CODES.AUTH, "auth_failed", "unauthorized"),
  });
  const jukebox = createJukeboxHandler({
    backend: dependencies.jukeboxBackend || new BackendJukeboxClient(config),
    rooms,
    onAuthenticationFailure: (session) =>
      closeSession(session, CLOSE_CODES.AUTH, "auth_failed", "unauthorized"),
  });
  const soundEffects = createSoundEffectsHandler({
    backend:
      dependencies.soundEffectsBackend || new BackendSoundEffectsClient(config),
    rooms,
    onAuthenticationFailure: (session) =>
      closeSession(session, CLOSE_CODES.AUTH, "auth_failed", "unauthorized"),
  });

  const sessionEvent = (session, type, payload = {}) =>
    createServerEvent({
      type,
      campaignId: session.campaignId,
      sequence: rooms.currentSequence(session.campaignId),
      actorUserId: session.userId,
      payload,
    });

  const sendPresenceSnapshot = (
    session,
    lastSequence,
    requestId = null,
    type = "presence.snapshot",
  ) => {
    const latestSequence = rooms.currentSequence(session.campaignId);
    sendEvent(
      session.ws,
      sessionEvent(session, type, {
        requestId,
        requestedAfter: lastSequence,
        latestSequence,
        resyncRequired: lastSequence !== latestSequence,
        users: presence.snapshot(session.campaignId),
      }),
    );
  };

  const sendSyncSnapshot = async (
    session,
    lastSequence,
    requestId = null,
    sceneId = null,
  ) => {
    let snapshot = null;
    let snapshotError = null;
    let jukeboxError = null;
    let soundEffectsError = null;
    if (sceneId !== null) {
      try {
        snapshot = await snapshots.get(session, sceneId);
      } catch (error) {
        if (Number(error?.status) === 401) {
          closeSession(
            session,
            CLOSE_CODES.AUTH,
            "auth_failed",
            "unauthorized",
          );
          return;
        }
        snapshotError = String(error?.code || "snapshot_unavailable");
      }
    }
    let jukeboxSnapshot = null;
    try {
      jukeboxSnapshot = await jukebox.snapshot(session);
    } catch (_error) {
      jukeboxError = "jukebox_unavailable";
    }
    let soundEffectsSnapshot = null;
    try {
      soundEffectsSnapshot = await soundEffects.state(session);
    } catch (_error) {
      soundEffectsError = "sound_effects_unavailable";
    }
    const latestSequence = rooms.currentSequence(session.campaignId);
    sendEvent(
      session.ws,
      sessionEvent(session, "sync.snapshot", {
        requestId,
        requestedAfter: lastSequence,
        latestSequence,
        resyncRequired: lastSequence !== latestSequence,
        users: presence.snapshot(session.campaignId),
        ...(jukeboxSnapshot ? { jukebox: jukeboxSnapshot } : {}),
        ...(soundEffectsSnapshot ? { soundEffects: soundEffectsSnapshot } : {}),
        ...(snapshot ? { snapshot } : {}),
        ...(snapshotError ? { snapshotError } : {}),
        ...(jukeboxError ? { jukeboxError } : {}),
        ...(soundEffectsError ? { soundEffectsError } : {}),
      }),
    );
  };

  const detach = (session, options = {}) => {
    if (!session.authenticated || session.detached || session.superseded)
      return;
    session.detached = true;
    rooms.remove(session);
    broadcastPresence(presence.remove(session, options));
  };

  const authenticate = (session, auth) => {
    const identity = ticketVerifier.verify(auth.ticket, {
      clientInstanceId: auth.clientInstanceId,
    });
    Object.assign(session, identity, {
      authenticated: true,
      realtimeTicket: auth.ticket,
    });
    clearTimeout(session.authTimer);
    session.authTimer = null;

    const replaced = rooms.add(session);
    const change = replaced
      ? presence.replace(replaced, session)
      : presence.add(session);
    if (replaced) {
      replaced.superseded = true;
      clearTimeout(replaced.expiryTimer);
      replaced.expiryTimer = null;
    }

    const lifetime = Math.max(0, session.expiresAt - Date.now());
    session.expiryTimer = setTimeout(
      () =>
        closeSession(
          session,
          CLOSE_CODES.AUTH,
          "auth_expired",
          "ticket_expired",
        ),
      lifetime,
    );
    session.expiryTimer.unref?.();

    sendEvent(
      session.ws,
      sessionEvent(session, "session.ready", {
        requestId: auth.requestId,
        connectionId: session.id,
        clientInstanceId: session.clientInstanceId,
        authExpiresAt: new Date(session.expiresAt).toISOString(),
        heartbeatIntervalMs: config.heartbeatIntervalMs,
        capabilities: session.capabilities,
      }),
    );
    broadcastPresence(change);
    sendPresenceSnapshot(session, auth.lastSequence, auth.requestId);
    void jukebox.sendState(session);
    void soundEffects.sendState(session);

    if (replaced) {
      sendEvent(replaced.ws, sessionEvent(replaced, "session.replaced", {}));
      if (replaced.ws.readyState === WebSocket.OPEN) {
        replaced.ws.close(CLOSE_CODES.REPLACED, "connection_replaced");
      } else {
        replaced.ws.terminate();
      }
    }
  };

  const handleAuthenticatedMessage = (session, message) => {
    const parsed = parseAuthenticatedMessage(message);
    if (parsed.type === "map.publish.notify") {
      if (session.capabilities?.canManage !== true) {
        sendProtocolError(session, "forbidden", parsed.requestId);
        return;
      }
      const event = createServerEvent({
        type: "map.published",
        campaignId: session.campaignId,
        sequence: rooms.nextSequence(session.campaignId),
        actorUserId: session.userId,
        payload: {
          requestId: parsed.requestId,
          mapId: parsed.mapId,
          mapRevision: parsed.mapRevision,
          sceneId: parsed.sceneId,
          sceneRevision: parsed.sceneRevision,
        },
      });
      for (const recipient of rooms.sessions(session.campaignId)) {
        sendEvent(recipient.ws, event);
      }
      return;
    }
    if (
      routeRealtimeFeature(
        {
          chat,
          tokens,
          tokenSync,
          fog,
          handouts,
          jukebox,
          soundEffects,
          ...sceneFeatures,
        },
        session,
        parsed,
      )
    )
      return;
    if (parsed.type === "sync.request") {
      void sendSyncSnapshot(
        session,
        parsed.lastSequence,
        parsed.requestId,
        parsed.sceneId,
      );
      return;
    }
    if (parsed.type === "campaign.leave") {
      sendEvent(
        session.ws,
        sessionEvent(session, "session.left", { requestId: parsed.requestId }),
      );
      detach(session, { grace: false, reason: "left" });
      session.ws.close(1000, "campaign_left");
    }
  };

  const handleMessage = (session, data, isBinary) => {
    if (!session.rateLimiter.consume()) {
      closeSession(
        session,
        CLOSE_CODES.RATE_LIMIT,
        "rate_limited",
        "rate_limited",
      );
      return;
    }
    try {
      const message = parseMessage(data, isBinary);
      if (!session.authenticated)
        authenticate(session, parseAuthMessage(message));
      else handleAuthenticatedMessage(session, message);
    } catch (error) {
      const code =
        error instanceof ProtocolError || error instanceof TicketError
          ? error.code
          : "internal_error";
      if (!session.authenticated) {
        const reason =
          code === "ticket_expired" ? "auth_expired" : "auth_failed";
        closeSession(session, CLOSE_CODES.AUTH, reason, code);
      } else sendProtocolError(session, code);
    }
  };

  const attach = (ws) => {
    const session = {
      id: randomUUID(),
      ws,
      alive: true,
      authenticated: false,
      detached: false,
      superseded: false,
      rateLimiter: new FixedWindowRateLimiter({
        limit: config.rateLimitMessages,
        windowMs: config.rateLimitWindowMs,
      }),
      authTimer: null,
      expiryTimer: null,
    };
    sessions.add(session);
    session.authTimer = setTimeout(
      () =>
        closeSession(session, CLOSE_CODES.AUTH, "auth_timeout", "auth_timeout"),
      config.authTimeoutMs,
    );
    session.authTimer.unref?.();

    ws.on("pong", () => {
      session.alive = true;
    });
    ws.on("message", (data, isBinary) =>
      handleMessage(session, data, isBinary),
    );
    ws.on("error", () => {});
    ws.on("close", () => {
      sessions.delete(session);
      clearTimeout(session.authTimer);
      clearTimeout(session.expiryTimer);
      detach(session, { grace: !stopping, reason: "disconnected" });
    });
  };

  wss.on("connection", attach);
  wss.on("error", (error) => httpServer.emit("error", error));

  const heartbeat = setInterval(() => {
    for (const session of sessions) {
      if (session.ws.readyState !== WebSocket.OPEN) continue;
      if (!session.alive) {
        session.ws.terminate();
        continue;
      }
      session.alive = false;
      session.ws.ping();
    }
  }, config.heartbeatIntervalMs);
  heartbeat.unref?.();

  const start = () =>
    new Promise((resolve, reject) => {
      const onError = (error) => reject(error);
      httpServer.once("error", onError);
      httpServer.listen(config.port, config.host, () => {
        httpServer.off("error", onError);
        resolve(httpServer.address());
      });
    });

  const stop = () =>
    new Promise((resolve) => {
      stopping = true;
      clearInterval(heartbeat);
      presence.stop();
      for (const session of sessions) {
        session.detached = true;
        clearTimeout(session.authTimer);
        clearTimeout(session.expiryTimer);
        session.ws.terminate();
      }
      sessions.clear();
      wss.close(() => {
        if (!httpServer.listening) resolve();
        else httpServer.close(() => resolve());
      });
    });

  return {
    start,
    stop,
    httpServer,
    wss,
    sessions,
    rooms,
    presence,
    ticketVerifier,
  };
};

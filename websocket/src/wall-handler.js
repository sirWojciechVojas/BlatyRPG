import { createSceneElementHandler } from "./scene-element-handler.js";
import { createServerEvent, sendEvent } from "./protocol.js";

const publicWall = (wall) => {
  const secret = wall.type === "secret" || wall.doorType === "secret";
  return {
    ...wall,
    name: secret ? `Wall ${wall.id}` : wall.name,
    type: secret ? "wall" : wall.type,
    doorType: secret ? "none" : wall.doorType,
    doorState: secret ? null : wall.doorState,
    color: null,
    hidden: false,
    soundConfig: secret ? {} : wall.soundConfig,
    animationConfig: secret ? {} : wall.animationConfig,
    capabilities: { canManage: false },
  };
};

const eventFor = (session, type, payload) =>
  createServerEvent({
    type,
    campaignId: session.campaignId,
    sequence: null,
    actorUserId: session.userId,
    payload,
  });

const canManage = (session) =>
  session.capabilities?.canManage === true ||
  ["gm", "admin"].includes(String(session.campaignRole || "").toLowerCase());

export const createWallHandler = (options) => {
  const { backend, rooms, onAuthenticationFailure } = options;

  const sendState = async (session, sceneId, requestId = null) => {
    try {
      const payload = canManage(session)
        ? { sceneId, activeLoops: [], serverTime: Date.now() }
        : await backend.audioState(
            session,
            sceneId,
            session.wallAudioSelectedTokenId || null,
          );
      sendEvent(
        session.ws,
        eventFor(session, "wall.audio.state", {
          ...payload,
          ...(requestId ? { requestId } : {}),
        }),
      );
    } catch (error) {
      if (Number(error?.status) === 401) onAuthenticationFailure(session);
    }
  };

  const afterPublish = async ({ result, recipients }) => {
    const wall = result.wall;
    const sceneId = Number(wall?.sceneId || result.sceneId);
    if (!sceneId) return;
    await Promise.allSettled(
      recipients.map(async (recipient) => {
        if (!canManage(recipient)) await sendState(recipient, sceneId);
        if (
          !result.sound ||
          !wall?.id ||
          canManage(recipient) ||
          !(wall.type === "secret" || wall.doorType === "secret")
        ) {
          return;
        }
        const payload = await backend.audioCue(
          recipient,
          sceneId,
          wall.id,
          result.sound,
          recipient.wallAudioSelectedTokenId || null,
        );
        if (payload.items.length) {
          sendEvent(
            recipient.ws,
            eventFor(recipient, "wall.audio.cue", payload),
          );
        }
      }),
    );
  };

  const base = createSceneElementHandler({
    ...options,
    resource: "wall",
    publicUpdates: true,
    publicItem: publicWall,
    afterPublish,
  });

  return {
    ...base,
    syncAudio(session, request) {
      session.wallAudioSelectedTokenId = request.selectedTokenId || null;
      void sendState(session, request.sceneId, request.requestId);
    },
    sendAudioState: sendState,
  };
};

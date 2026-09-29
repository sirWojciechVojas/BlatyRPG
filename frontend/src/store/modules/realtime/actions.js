import {
  createRealtimeChatActions,
  restoreRealtimeChat,
  routeRealtimeChatEvent,
} from "./chatActions";
import {
  createRealtimeTokenActions,
  routeRealtimeTokenEvent,
} from "./tokenActions";
import {
  createRealtimeLightActions,
  routeRealtimeLightEvent,
} from "./lightActions";
import {
  createRealtimeTileActions,
  routeRealtimeTileEvent,
} from "./tileActions";
import {
  createRealtimeWallActions,
  routeRealtimeWallEvent,
} from "./wallActions";
import {
  createRealtimeCombatActions,
  routeRealtimeCombatEvent,
} from "./combatActions";
import { createRealtimeFogActions, routeRealtimeFogEvent } from "./fogActions";
import { routeRealtimeSceneSnapshot } from "./sceneSnapshot";

const defaultRestore = async (context, details) => {
  if (!details.reconnected) return;
  if (context.rootState.campaignContext) {
    await context.dispatch("campaignContext/reconcile", null, { root: true });
  }
};

export const createRealtimeActions = (
  sessionFactory,
  restore = defaultRestore,
) => {
  let session = null;

  const ensureSession = (context) => {
    if (session) return session;
    session = sessionFactory({
      getSyncContext: () => ({
        sceneId: context.rootState.vtt?.selectedSceneId || null,
      }),
      onStatus: (details) => context.commit("SET_STATUS", details),
      onPresenceSnapshot: (items) =>
        context.commit("SET_PRESENCE_SNAPSHOT", items),
      onPresenceChange: (item) => context.commit("APPLY_PRESENCE_CHANGE", item),
      onEvent: (event) => {
        if (event.sequence > 0) {
          context.commit("SET_LAST_SEQUENCE", event.sequence);
        }
        routeRealtimeSceneSnapshot(context, event);
        routeRealtimeChatEvent(context, ensureSession(context), event);
        routeRealtimeTokenEvent(context, event);
        routeRealtimeCombatEvent(context, event);
        routeRealtimeWallEvent(context, event);
        routeRealtimeLightEvent(context, event);
        routeRealtimeTileEvent(context, event);
        routeRealtimeFogEvent(context, event);
        if (
          event.type === "handout.available" &&
          typeof window !== "undefined"
        ) {
          window.dispatchEvent(
            new CustomEvent("blatyrpg:handout-available", { detail: event }),
          );
        }
      },
      onSequenceGap: ({ expected }) =>
        context.commit("SET_LAST_SEQUENCE", expected - 1),
      onRestore: async (details) => {
        context.commit("SET_LAST_SEQUENCE", details.lastSequence);
        await restore(context, details);
        restoreRealtimeChat(context, ensureSession(context));
        if (typeof window !== "undefined") {
          window.dispatchEvent(
            new CustomEvent("blatyrpg:handout-refresh", {
              detail: { campaignId: details.campaignId },
            }),
          );
        }
      },
    });
    return session;
  };

  return {
    ...createRealtimeChatActions(ensureSession),
    ...createRealtimeTokenActions(ensureSession),
    ...createRealtimeCombatActions(ensureSession),
    ...createRealtimeWallActions(ensureSession),
    ...createRealtimeLightActions(ensureSession),
    ...createRealtimeTileActions(ensureSession),
    ...createRealtimeFogActions(ensureSession),
    connect(context, campaignId) {
      const id = Number(campaignId);
      if (context.state.campaignId !== id) {
        context.commit("SET_CAMPAIGN", id);
      }
      ensureSession(context).connect(id);
    },
    disconnect(context) {
      session?.disconnect();
      context.commit("SET_CAMPAIGN", null);
      context.commit("SET_STATUS", { status: "disconnected" });
    },
    retry(context) {
      return ensureSession(context).retry();
    },
    requestSync(context) {
      const presence = ensureSession(context).requestSync();
      context.dispatch("syncChat");
      return presence;
    },
    sendHandoutAvailability(context, payload) {
      return ensureSession(context).notifyHandout(payload);
    },
  };
};

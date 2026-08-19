import {
  createRealtimeChatActions,
  restoreRealtimeChat,
  routeRealtimeChatEvent,
} from "./chatActions";

const defaultRestore = async (context, details) => {
  if (!details.reconnected) return;
  if (context.rootState.campaignContext) {
    await context.dispatch("campaignContext/refresh", null, { root: true });
  }
  if (context.rootState.vtt) {
    await context.dispatch("vtt/initialize", null, { root: true });
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
      onStatus: (details) => context.commit("SET_STATUS", details),
      onPresenceSnapshot: (items) =>
        context.commit("SET_PRESENCE_SNAPSHOT", items),
      onPresenceChange: (item) => context.commit("APPLY_PRESENCE_CHANGE", item),
      onEvent: (event) => {
        if (event.sequence > 0) {
          context.commit("SET_LAST_SEQUENCE", event.sequence);
        }
        routeRealtimeChatEvent(context, ensureSession(context), event);
      },
      onSequenceGap: ({ expected }) =>
        context.commit("SET_LAST_SEQUENCE", expected - 1),
      onRestore: async (details) => {
        context.commit("SET_LAST_SEQUENCE", details.lastSequence);
        await restore(context, details);
        restoreRealtimeChat(context, ensureSession(context));
      },
    });
    return session;
  };

  return {
    ...createRealtimeChatActions(ensureSession),
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
  };
};

import { describe, expect, it, vi } from "vitest";
import {
  normalizeRealtimeChatMessage,
  withCurrentChatUser,
} from "@/lib/chat/realtimeChatMessage";
import {
  createRealtimeChatActions,
  routeRealtimeChatEvent,
} from "../chatActions";
import { realtimeChatMutations } from "../chatMutations";
import { createRealtimeState } from "../state";

const message = (id, body = `message-${id}`) => ({
  id,
  revision: id,
  campaignId: 7,
  type: "text",
  body,
  author: { id: 3, name: "Ada" },
  createdAt: "2026-08-19 12:00:00",
});

const context = () => {
  const state = createRealtimeState();
  state.campaignId = 7;
  return {
    state,
    commit(type, payload) {
      realtimeChatMutations[type](state, payload);
    },
  };
};

describe("realtime chat store", () => {
  it("merges neutral messages and filters another campaign", () => {
    const ctx = context();
    routeRealtimeChatEvent(
      ctx,
      {},
      {
        type: "chat.snapshot",
        payload: {
          mode: "initial",
          items: [message(2), { ...message(9), campaignId: 8 }],
          pagination: { hasMoreBefore: true, hasMoreAfter: false },
          capabilities: { canRead: true, canSend: true },
          latestRevision: 2,
        },
      },
    );
    routeRealtimeChatEvent(
      ctx,
      {},
      {
        type: "chat.message",
        payload: { message: message(3) },
      },
    );

    expect(ctx.state.chat.messages.map((item) => item.id)).toEqual([2, 3]);
    expect(ctx.state.chat.messages[0].author.isCurrentUser).toBeUndefined();
    expect(ctx.state.chat.hasMoreBefore).toBe(true);
    expect(ctx.state.chat.latestRevision).toBe(3);
  });

  it("continues every missing-message page until caught up", () => {
    const ctx = context();
    const session = { syncChat: vi.fn(() => true) };
    routeRealtimeChatEvent(ctx, session, {
      type: "chat.snapshot",
      payload: {
        mode: "missing",
        items: [message(12)],
        pagination: { afterRevision: 12, hasMoreAfter: true },
        capabilities: { canRead: true, canSend: true },
        latestRevision: 20,
      },
    });

    expect(session.syncChat).toHaveBeenCalledWith(
      expect.objectContaining({ afterRevision: 12 }),
    );
    expect(ctx.state.chat.syncPages).toBe(1);
  });

  it("reuses a pending nonce until the backend acknowledges it", () => {
    const ctx = context();
    ctx.state.chat.capabilities.canSend = true;
    const session = {
      sendChat: vi.fn(() => true),
      syncChat: vi.fn(() => true),
    };
    const actions = createRealtimeChatActions(() => session);
    const first = actions.sendChatMessage(ctx, "Ready");
    const second = actions.sendChatMessage(ctx, "Ready");

    expect(second).toBe(first);
    expect(session.sendChat).toHaveBeenLastCalledWith({
      requestId: first,
      clientNonce: first,
      body: "Ready",
    });
    ctx.commit("APPLY_CHAT_ACK", {
      clientNonce: first,
      capabilities: { canRead: true, canSend: true },
    });
    expect(ctx.state.chat.pending).toBeNull();
    expect(ctx.state.chat.sending).toBe(false);
  });

  it("rejects malformed authoritative messages", () => {
    expect(
      normalizeRealtimeChatMessage({
        ...message(4),
        revision: 5,
      }),
    ).toBeNull();
  });

  it("keeps messages from deleted authors neutral", () => {
    const deleted = normalizeRealtimeChatMessage({
      ...message(6),
      author: { id: null, name: "Deleted user" },
    });
    expect(withCurrentChatUser(deleted, null).author.isCurrentUser).toBe(false);
  });
});

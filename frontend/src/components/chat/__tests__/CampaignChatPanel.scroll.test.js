import { describe, expect, it, vi } from "vitest";
import options from "../options/CampaignChatPanel.options";

vi.mock("@/lib/auth/authSession", () => ({
  authSession: { read: vi.fn(() => ({ user: { id: 3 } })) },
}));

describe("CampaignChatPanel automatic scrolling", () => {
  it("tracks the newest message without reacting to prepended history", () => {
    const key = options.computed.latestMessageKey;
    const current = key.call({ campaignId: 4, messages: [{ id: 8 }] });
    const withOlder = key.call({
      campaignId: 4,
      messages: [{ id: 2 }, { id: 8 }],
    });

    expect(current).toBe("4:8");
    expect(withOlder).toBe(current);
  });

  it("scrolls to every newly appended message", () => {
    const requestScrollToBottom = vi.fn();
    const markNewMessage = vi.fn();

    options.watch.latestMessageKey.call(
      {
        chatReadyObserved: false,
        messages: [{ id: 9 }],
        markNewMessage,
        requestScrollToBottom,
      },
      "4:9",
      "4:8",
    );

    expect(markNewMessage).toHaveBeenCalledWith(9);
    expect(requestScrollToBottom).toHaveBeenCalledOnce();
    expect(requestScrollToBottom).toHaveBeenCalledWith({ behavior: "smooth" });
  });

  it("does not mark existing history as new during initial synchronization", () => {
    const requestScrollToBottom = vi.fn();
    const markNewMessage = vi.fn();

    options.watch.latestMessageKey.call(
      {
        chatReadyObserved: false,
        messages: [{ id: 9 }],
        markNewMessage,
        requestScrollToBottom,
      },
      "4:9",
      "",
    );

    expect(markNewMessage).not.toHaveBeenCalled();
    expect(requestScrollToBottom).toHaveBeenCalledWith({ behavior: "auto" });
  });

  it("marks the first message that arrives after an empty chat synchronized", () => {
    const requestScrollToBottom = vi.fn();
    const markNewMessage = vi.fn();

    options.watch.latestMessageKey.call(
      {
        chatReadyObserved: true,
        messages: [{ id: 1 }],
        markNewMessage,
        requestScrollToBottom,
      },
      "4:1",
      "",
    );

    expect(markNewMessage).toHaveBeenCalledWith(1);
    expect(requestScrollToBottom).toHaveBeenCalledWith({ behavior: "smooth" });
  });

  it("shows the newest message after expanding the chat", () => {
    const observeMessageList = vi.fn();
    const requestScrollToBottom = vi.fn();
    const nextTick = vi.fn((callback) => callback());

    options.watch.collapsed.call(
      { $nextTick: nextTick, observeMessageList, requestScrollToBottom },
      false,
    );

    expect(observeMessageList).toHaveBeenCalledOnce();
    expect(requestScrollToBottom).toHaveBeenCalledOnce();
  });

  it("keeps a scroll pending while hidden and completes it when visible", () => {
    const list = { clientHeight: 0, scrollHeight: 480, scrollTop: 0 };
    const context = {
      $refs: { messageList: list },
      scrollPending: true,
      scrollBehavior: "auto",
    };

    expect(options.methods.scrollToBottom.call(context)).toBe(false);
    expect(context.scrollPending).toBe(true);

    list.clientHeight = 220;
    expect(options.methods.scrollToBottom.call(context)).toBe(true);
    expect(list.scrollTop).toBe(480);
    expect(context.scrollPending).toBe(false);
  });

  it("animates the scroll for an appended message", () => {
    const list = {
      clientHeight: 220,
      scrollHeight: 640,
      scrollTo: vi.fn(),
    };
    const context = {
      $refs: { messageList: list },
      scrollPending: true,
      scrollBehavior: "smooth",
    };

    expect(options.methods.scrollToBottom.call(context)).toBe(true);
    expect(list.scrollTo).toHaveBeenCalledWith({
      top: 640,
      behavior: "smooth",
    });
    expect(context.scrollBehavior).toBe("auto");
  });

  it("reconnects an offline chat instead of sending a doomed sync", () => {
    const dispatch = vi.fn(() => true);

    options.methods.refresh.call({
      realtime: { status: "reconnecting" },
      $store: { dispatch },
    });

    expect(dispatch).toHaveBeenCalledOnce();
    expect(dispatch).toHaveBeenCalledWith("realtime/retry");
  });
});

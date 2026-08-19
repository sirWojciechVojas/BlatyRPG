import { describe, expect, it } from "vitest";
import { chatCursor, mergeChatMessages } from "@/lib/chat/campaignChatState";

describe("campaignChatState", () => {
  it("deduplicates polling and optimistic responses by server id", () => {
    const result = mergeChatMessages(
      [{ id: 2, body: "old" }],
      [
        { id: 3, body: "new" },
        { id: 2, body: "updated" },
      ],
    );

    expect(result).toEqual([
      { id: 2, body: "updated" },
      { id: 3, body: "new" },
    ]);
    expect(chatCursor(result, "before")).toBe(2);
    expect(chatCursor(result, "after")).toBe(3);
  });
});

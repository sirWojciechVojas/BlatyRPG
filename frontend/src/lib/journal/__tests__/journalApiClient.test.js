import { describe, expect, it, vi } from "vitest";
import { createJournalApiClient } from "../journalApiClient";

describe("journalApiClient", () => {
  it("scopes list requests to campaign, character and type", async () => {
    const client = { request: vi.fn(async () => ({ items: [] })) };

    await createJournalApiClient(client).list(4, 9, { type: "npc" });

    expect(client.request).toHaveBeenCalledWith(
      "/campaigns/4/journal?characterId=9&type=npc",
      {},
    );
  });

  it("persists normalized nested entry content through PATCH", async () => {
    const client = {
      request: vi.fn(async () => ({
        entry: {
          id: 12,
          characterId: 9,
          title: "Tajemniczy kupiec",
          sections: [{ key: "suspicions", content: "Kłamie." }],
        },
      })),
    };
    const draft = { title: "Tajemniczy kupiec", revision: 2 };

    const result = await createJournalApiClient(client).update(4, 12, draft);

    expect(client.request).toHaveBeenCalledWith("/campaigns/4/journal/12", {
      method: "PATCH",
      body: draft,
    });
    expect(result.entry.sections[0]).toMatchObject({
      key: "suspicions",
      content: "Kłamie.",
    });
  });
});

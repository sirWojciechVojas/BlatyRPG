import { describe, expect, it, vi } from "vitest";
import { createCompendiumApiClient } from "../compendiumApiClient";

describe("compendiumApiClient", () => {
  it("derives campaign paths and encodes reader filters", async () => {
    const request = vi.fn().mockResolvedValue({ items: [] });
    const client = createCompendiumApiClient({ client: { request } });

    await client.campaignEntries(7, { q: "old forest", type: 3 });

    expect(request).toHaveBeenCalledWith(
      "/campaigns/7/compendium/entries?q=old+forest&type=3",
      {},
    );
  });

  it("sends optimistic revisions for drafts and publication", async () => {
    const request = vi.fn().mockResolvedValue({ entry: { id: 9 } });
    const client = createCompendiumApiClient({ client: { request } });

    await client.updateEntry(4, 9, { title: "Citadel", revision: 11 });
    await client.publish(4, 9, 12);

    expect(request).toHaveBeenNthCalledWith(
      1,
      "/universes/4/compendium/entries/9",
      { method: "PATCH", body: { title: "Citadel", revision: 11 } },
    );
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/universes/4/compendium/entries/9/publish",
      { method: "POST", body: { revision: 12 } },
    );
  });
});

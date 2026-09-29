import { describe, expect, it, vi } from "vitest";
import { createProfessionApiClient } from "../professionApiClient";

describe("professionApiClient", () => {
  it("loads a campaign catalog and the selected character history", async () => {
    const request = vi.fn().mockResolvedValue({});
    const api = createProfessionApiClient({ request });

    await api.catalog(4);
    await api.characterHistory(4, 9);
    await api.systemCatalog(1);

    expect(request).toHaveBeenNthCalledWith(1, "/campaigns/4/professions", {
      signal: undefined,
    });
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/4/characters/9/professions",
      { signal: undefined },
    );
    expect(request).toHaveBeenNthCalledWith(3, "/systems/1/professions", {
      signal: undefined,
    });
  });

  it("rejects invalid identifiers without sending a request", () => {
    const request = vi.fn();
    const api = createProfessionApiClient({ request });

    expect(() => api.catalog(0)).toThrow("campaign_id_required");
    expect(() => api.characterHistory(4, "hero")).toThrow(
      "character_id_required",
    );
    expect(request).not.toHaveBeenCalled();
  });

  it("uses dedicated GM profession history write endpoints", async () => {
    const request = vi.fn().mockResolvedValue({});
    const api = createProfessionApiClient({ request });

    await api.changeCharacterProfession(4, 9, 61);
    await api.reorderCharacterProfessions(4, 9, [12, 10]);
    await api.activateCharacterProfession(4, 9, 12);
    await api.deleteCharacterProfession(4, 9, 10);

    expect(request).toHaveBeenNthCalledWith(
      1,
      "/campaigns/4/characters/9/profession",
      { method: "PUT", body: { professionId: 61 } },
    );
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/4/characters/9/professions/history-order",
      { method: "PUT", body: { historyIds: [12, 10] } },
    );
    expect(request).toHaveBeenNthCalledWith(
      3,
      "/campaigns/4/characters/9/professions/12/activate",
      { method: "PUT" },
    );
    expect(request).toHaveBeenNthCalledWith(
      4,
      "/campaigns/4/characters/9/professions/10",
      { method: "DELETE" },
    );
  });
});

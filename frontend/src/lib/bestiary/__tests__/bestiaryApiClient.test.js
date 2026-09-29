import { describe, expect, it, vi } from "vitest";
import { createBestiaryApiClient } from "../bestiaryApiClient";

describe("bestiaryApiClient", () => {
  it("loads the character catalog and an unlocked entry", async () => {
    const request = vi.fn().mockResolvedValue({});
    const api = createBestiaryApiClient({ request });

    await api.list(4, 9);
    await api.entry(4, 9, 27);

    expect(request).toHaveBeenNthCalledWith(
      1,
      "/campaigns/4/characters/9/bestiary",
    );
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/4/characters/9/bestiary/27",
    );
  });

  it("rejects invalid resource identifiers before making a request", () => {
    const request = vi.fn();
    const api = createBestiaryApiClient({ request });

    expect(() => api.list(4, 0)).toThrow("character_id_required");
    expect(() => api.entry(4, 9, "locked")).toThrow("entry_id_required");
    expect(request).not.toHaveBeenCalled();
  });

  it("lets the GM manage one entry separately for every hero", async () => {
    const request = vi.fn().mockResolvedValue({});
    const api = createBestiaryApiClient({ request });

    await api.assignments(4, 27);
    await api.setAssignment(4, 27, 9, "full");
    await api.setAssignments(4, 27, [9, 12], "summary");
    await api.saveAssignments(4, 27, [
      { characterId: 9, level: "unknown" },
      { characterId: 12, level: "full" },
    ]);
    await api.saveAssignments(4, 27, [], ["lead", "habitat"]);

    expect(request).toHaveBeenNthCalledWith(
      1,
      "/campaigns/4/bestiary/entries/27/assignments",
    );
    expect(request).toHaveBeenNthCalledWith(
      2,
      "/campaigns/4/bestiary/entries/27/characters/9",
      { method: "PUT", body: { level: "full" } },
    );
    expect(request).toHaveBeenNthCalledWith(
      3,
      "/campaigns/4/bestiary/entries/27/assignments",
      {
        method: "PUT",
        body: { characterIds: [9, 12], level: "summary" },
      },
    );
    expect(request).toHaveBeenNthCalledWith(
      4,
      "/campaigns/4/bestiary/entries/27/assignments",
      {
        method: "PUT",
        body: {
          assignments: [
            { characterId: 9, level: "unknown" },
            { characterId: 12, level: "full" },
          ],
        },
      },
    );
    expect(request).toHaveBeenNthCalledWith(
      5,
      "/campaigns/4/bestiary/entries/27/assignments",
      {
        method: "PUT",
        body: {
          assignments: [],
          sectionKeys: ["lead", "habitat"],
        },
      },
    );
  });
});

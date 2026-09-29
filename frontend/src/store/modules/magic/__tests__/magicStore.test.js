import { beforeEach, describe, expect, it, vi } from "vitest";
import { parseMagicCastMessage } from "@/lib/chat/magicCastMessage";
import { magicApiClient } from "@/lib/magic/magicApiClient";
import magicModule, { keyOf } from "../index";

const cast = {
  id: 31,
  spell: { name: "Ognista kula", tradition: "Tradycja Ognia" },
  targets: [],
  result: {
    spell: { name: "Ognista kula" },
    powerDice: [8, 8],
    chaosDice: [8],
    modifier: 2,
    powerTotal: 18,
    castingNumber: 12,
    spellSucceeded: true,
    automaticFailure: false,
    willpowerTestRequired: false,
    manifestations: [{ face: 8, matchingDice: 3, severity: "major" }],
    channel: { attempted: false, succeeded: false, roll: null, bonus: 0 },
    ingredient: { name: "Siarka", bonus: 2, consumed: true },
    targetDefense: { message: "Brak osobnego testu." },
    effect: { message: "Jeden pocisk o Sile 3." },
  },
};

describe("magic store chat publication", () => {
  beforeEach(() => vi.restoreAllMocks());

  it("publishes a newly resolved server result to campaign chat", async () => {
    vi.spyOn(magicApiClient, "resolveCast").mockResolvedValue({
      cast,
      duplicate: false,
    });
    const state = magicModule.state();
    const key = keyOf(7, 9);
    state.books[key] = {
      phase: "ready",
      data: { character: { name: "Elsa" } },
      activeCast: null,
    };
    const commit = (type, payload) =>
      magicModule.mutations[type](state, payload);
    const dispatch = vi.fn(() => Promise.resolve());

    await magicModule.actions.resolveCast(
      { commit, dispatch, state },
      { campaignId: 7, characterId: 9, castId: 31 },
    );

    const publication = dispatch.mock.calls.find(
      ([type]) => type === "realtime/sendChatMessage",
    );
    expect(publication?.[2]).toEqual({ root: true });
    expect(parseMagicCastMessage(publication?.[1])).toMatchObject({
      character: "Elsa",
      spell: "Ognista kula",
      powerDice: [8, 8],
      chaosDice: [8],
      succeeded: true,
    });
  });

  it("does not publish an idempotent duplicate resolution", async () => {
    vi.spyOn(magicApiClient, "resolveCast").mockResolvedValue({
      cast,
      duplicate: true,
    });
    const state = magicModule.state();
    const commit = (type, payload) =>
      magicModule.mutations[type](state, payload);
    const dispatch = vi.fn(() => Promise.resolve());

    await magicModule.actions.resolveCast(
      { commit, dispatch, state },
      { campaignId: 7, characterId: 9, castId: 31 },
    );

    expect(dispatch).not.toHaveBeenCalledWith(
      "realtime/sendChatMessage",
      expect.anything(),
      expect.anything(),
    );
  });
});

import { describe, expect, it, vi } from "vitest";
import { createMagicApiClient } from "../magicApiClient";

describe("magicApiClient", () => {
  it("uses character-scoped spellbook and server-side cast resolution endpoints", async () => {
    const request = vi.fn().mockResolvedValue({ cast: { id: 9 } });
    const client = createMagicApiClient({ request });

    await client.get(3, 7);
    await client.createCast(3, 7, {
      spellId: 4,
      powerDice: 2,
      idempotencyKey: "cast:test-key",
    });
    await client.resolveCast(3, 9);

    expect(request.mock.calls).toEqual([
      ["/campaigns/3/characters/7/magic", {}],
      [
        "/campaigns/3/characters/7/magic/casts",
        {
          method: "POST",
          body: {
            spellId: 4,
            powerDice: 2,
            idempotencyKey: "cast:test-key",
          },
        },
      ],
      [
        "/campaigns/3/magic/casts/9/resolve",
        {
          method: "POST",
          body: {},
        },
      ],
    ]);
  });
});

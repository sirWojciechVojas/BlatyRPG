import { describe, expect, it } from "vitest";
import { combatCommandMessage } from "@/lib/realtime/realtimeProtocol";

describe("combat realtime protocol", () => {
  it("keeps campaign scope outside the client command", () => {
    expect(
      combatCommandMessage({
        requestId: "combat-1",
        sceneId: 4,
        command: { action: "next", revision: 3 },
      }),
    ).toEqual({
      v: 1,
      type: "combat.command",
      requestId: "combat-1",
      sceneId: 4,
      command: { action: "next", revision: 3 },
    });
  });
});

import { describe, expect, it, vi } from "vitest";
import { tokenMoveMessage } from "@/lib/realtime/realtimeProtocol";
import {
  createRealtimeTokenActions,
  routeRealtimeTokenEvent,
} from "@/store/modules/realtime/tokenActions";

describe("realtime token synchronization", () => {
  it("builds a scoped token movement protocol message", () => {
    expect(
      tokenMoveMessage({
        requestId: "move-1",
        sceneId: 4,
        tokenId: 9,
        revision: 3,
        x: 120.5,
        y: 240,
      }),
    ).toEqual({
      v: 1,
      type: "token.move",
      requestId: "move-1",
      sceneId: 4,
      tokenId: 9,
      revision: 3,
      x: 120.5,
      y: 240,
    });
  });

  it("applies only the authoritative token returned by the server", () => {
    const context = {
      rootState: { vtt: {} },
      commit: vi.fn(),
      dispatch: vi.fn(),
    };
    routeRealtimeTokenEvent(context, {
      type: "token.updated",
      payload: {
        token: { id: 9, sceneId: 4, name: "Guard", x: 30, y: 40, revision: 4 },
      },
    });
    expect(context.commit).toHaveBeenCalledWith(
      "vtt/UPSERT_TOKEN",
      expect.objectContaining({ id: 9, sceneId: 4, x: 30, revision: 4 }),
      { root: true },
    );
  });

  it("sends the loaded revision through the existing session", () => {
    const moveToken = vi.fn().mockReturnValue(true);
    const actions = createRealtimeTokenActions(() => ({ moveToken }));
    const sent = actions.moveToken(
      {},
      {
        token: { id: 9, sceneId: 4, revision: 3 },
        x: 50,
        y: 60,
      },
    );
    expect(sent).toBe(true);
    expect(moveToken).toHaveBeenCalledWith(
      expect.objectContaining({
        sceneId: 4,
        tokenId: 9,
        revision: 3,
        x: 50,
        y: 60,
      }),
    );
  });
});

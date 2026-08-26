import { describe, expect, it, vi } from "vitest";
import {
  tokenMoveMessage,
  tokenMovementRequestMessage,
  tokenMovementResolveMessage,
} from "@/lib/realtime/realtimeProtocol";
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
      waypoints: [],
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

  it("tracks an approval and applies its movement patch without replacing permissions", () => {
    const context = {
      rootState: { vtt: {} },
      commit: vi.fn(),
      dispatch: vi.fn(),
    };
    routeRealtimeTokenEvent(context, {
      type: "token.movement.resolved",
      payload: {
        request: {
          id: 31,
          sceneId: 4,
          tokenId: 9,
          requestedByUserId: 2,
          status: "approved",
        },
        tokenPatch: {
          id: 9,
          sceneId: 4,
          x: 500,
          y: 600,
          movementSpent: 10,
          revision: 4,
        },
      },
    });
    expect(context.commit).toHaveBeenCalledWith(
      "vtt/PATCH_TOKEN",
      expect.objectContaining({ id: 9, x: 500, movementSpent: 10 }),
      { root: true },
    );
  });

  it("builds request and GM decision messages", () => {
    expect(
      tokenMovementRequestMessage({
        requestId: "request-31",
        sceneId: 4,
        tokenId: 9,
        revision: 3,
        x: 500,
        y: 600,
      }),
    ).toMatchObject({ type: "token.movement.request", tokenId: 9 });
    expect(
      tokenMovementResolveMessage({
        requestId: "resolve-31",
        movementRequestId: 31,
        decision: "approve",
      }),
    ).toEqual({
      v: 1,
      type: "token.movement.resolve",
      requestId: "resolve-31",
      movementRequestId: 31,
      decision: "approve",
    });
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
        waypoints: [],
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

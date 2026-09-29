import { describe, expect, it, vi } from "vitest";
import { createSceneElementRequestTracker } from "../sceneElementRequestTracker";

const tracker = (resource = "wall") =>
  createSceneElementRequestTracker(resource, {
    setTimeout: vi.fn(() => 17),
    clearTimeout: vi.fn(),
  });

describe("scene element write acknowledgements", () => {
  it("does not report a WebSocket write as saved before the server ack", async () => {
    const requests = tracker();
    const pending = requests.send("wall-update-1", () => true);
    let settled = false;
    pending.finally(() => {
      settled = true;
    });

    await Promise.resolve();
    expect(settled).toBe(false);
    expect(requests.size()).toBe(1);

    requests.settle({
      type: "wall.ack",
      payload: { requestId: "wall-update-1" },
    });
    await expect(pending).resolves.toBe(true);
  });

  it("keeps the authoritative conflict instead of silently closing the editor", async () => {
    const requests = tracker("token");
    const pending = requests.send("token-update-1", () => true);

    requests.settle({
      type: "token.error",
      payload: {
        requestId: "token-update-1",
        code: "revision_conflict",
        status: 409,
      },
    });

    await expect(pending).rejects.toMatchObject({
      code: "revision_conflict",
      status: 409,
    });
  });
});

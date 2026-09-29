import { describe, expect, it, vi } from "vitest";
import { createLightRequestTracker } from "../lightRequestTracker";

const tracker = () =>
  createLightRequestTracker({
    setTimeout: vi.fn(() => 7),
    clearTimeout: vi.fn(),
  });

describe("light request acknowledgements", () => {
  it("resolves only after the authoritative acknowledgement", async () => {
    const requests = tracker();
    const promise = requests.send("light-1", () => true);
    expect(requests.size()).toBe(1);

    requests.settle({ type: "light.ack", payload: { requestId: "light-1" } });
    await expect(promise).resolves.toBe(true);
    expect(requests.size()).toBe(0);
  });

  it("rejects with the server error instead of reporting success", async () => {
    const requests = tracker();
    const promise = requests.send("light-2", () => true);
    requests.settle({
      type: "light.error",
      payload: { requestId: "light-2", code: "revision_conflict", status: 409 },
    });

    await expect(promise).rejects.toMatchObject({
      code: "revision_conflict",
      status: 409,
    });
  });
});

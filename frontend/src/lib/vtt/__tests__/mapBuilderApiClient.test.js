import { describe, expect, it, vi } from "vitest";
import { createMapBuilderApiClient } from "@/lib/vtt/mapBuilderApiClient";

describe("mapBuilderApiClient", () => {
  it("uses campaign-scoped project, lock, revision and publication routes", async () => {
    const request = vi.fn().mockResolvedValue({});
    const client = createMapBuilderApiClient({ request });

    await client.create(7, { name: "Karczma" });
    await client.save(7, 12, { baseRevision: 2 });
    await client.acquireLock(7, 12, "editor-test-0001");
    await client.restore(7, 12, 31, "editor-test-0001");
    await client.publish(7, 12, { revision: 3, sceneId: 4 });

    expect(request).toHaveBeenNthCalledWith(1, "/campaigns/7/maps", {
      method: "POST",
      body: { name: "Karczma" },
    });
    expect(request).toHaveBeenNthCalledWith(2, "/campaigns/7/maps/12", {
      method: "PUT",
      body: { baseRevision: 2 },
    });
    expect(request).toHaveBeenNthCalledWith(3, "/campaigns/7/maps/12/lock", {
      method: "POST",
      body: { editorId: "editor-test-0001" },
    });
    expect(request).toHaveBeenNthCalledWith(
      4,
      "/campaigns/7/maps/12/revisions/31/restore",
      { method: "POST", body: { editorId: "editor-test-0001" } },
    );
    expect(request).toHaveBeenNthCalledWith(5, "/campaigns/7/maps/12/publish", {
      method: "POST",
      body: { revision: 3, sceneId: 4 },
    });
  });

  it("queues, polls and cancels idempotent AI jobs", async () => {
    const request = vi.fn().mockResolvedValue({});
    const client = createMapBuilderApiClient({ request });
    const job = { prompt: "Wyposaż izbę", idempotencyKey: "request-0001" };

    await client.createAiJob(7, 12, job);
    await client.aiJob(7, 12, "11111111-1111-4111-8111-111111111111");
    await client.cancelAiJob(7, 12, "11111111-1111-4111-8111-111111111111");

    const route =
      "/campaigns/7/maps/12/ai-jobs/11111111-1111-4111-8111-111111111111";
    expect(request).toHaveBeenNthCalledWith(1, "/campaigns/7/maps/12/ai-jobs", {
      method: "POST",
      body: job,
    });
    expect(request).toHaveBeenNthCalledWith(2, route);
    expect(request).toHaveBeenNthCalledWith(3, route, {
      method: "DELETE",
      body: {},
    });
  });
});

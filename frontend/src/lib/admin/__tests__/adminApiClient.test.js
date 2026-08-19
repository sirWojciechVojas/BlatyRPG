import { describe, expect, it, vi } from "vitest";
import { createAdminApiClient } from "../adminApiClient";

describe("adminApiClient", () => {
  it("normalizes the overview without exposing unrelated fields", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({
        currentUserId: 2,
        users: [
          { id: "2", username: "admin", role: "ADMIN", campaignCount: "1" },
        ],
        campaigns: [{ id: "4", name: "World", isActive: 1, memberCount: "2" }],
        metrics: { users: "1", admins: "1", campaigns: "1" },
      }),
    };
    const result = await createAdminApiClient(client).overview();

    expect(client.request).toHaveBeenCalledWith("/admin/overview", {});
    expect(result.users[0]).toMatchObject({
      id: 2,
      role: "admin",
      campaignCount: 1,
    });
    expect(result.campaigns[0]).toMatchObject({ id: 4, memberCount: 2 });
  });

  it("uses dedicated write endpoints", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({ user: { id: 3, role: "gm" } }),
    };
    const api = createAdminApiClient(client);
    await api.createUser({ username: "gm" });
    await api.changeUserRole(3, "gm");

    expect(client.request).toHaveBeenNthCalledWith(1, "/admin/users", {
      method: "POST",
      body: { username: "gm" },
    });
    expect(client.request).toHaveBeenNthCalledWith(2, "/admin/users/3/role", {
      method: "PATCH",
      body: { role: "gm" },
    });
  });
});

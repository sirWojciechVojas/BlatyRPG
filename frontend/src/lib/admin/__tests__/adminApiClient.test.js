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
        characters: [
          {
            id: "9",
            name: "Bruder Witz",
          },
        ],
        characterCampaigns: [
          { characterId: "9", campaignId: "4", campaignName: "World" },
        ],
        characterOwners: [
          {
            characterId: "9",
            campaignId: "4",
            userId: "2",
            username: "admin",
          },
        ],
        characterGameMasters: [
          {
            campaignId: "4",
            userId: "2",
            username: "admin",
            isCampaignOwner: true,
          },
        ],
        metrics: {
          users: "1",
          admins: "1",
          campaigns: "1",
          activeCampaigns: "1",
          memberships: "2",
          activeSessions: "1",
        },
        analytics: {
          accountRoles: [{ key: "admin", value: "1" }],
          growth: [{ label: "18.08", users: "1", campaigns: "0" }],
        },
      }),
    };
    const result = await createAdminApiClient(client).overview();

    expect(client.request).toHaveBeenCalledWith("/admin/overview", {});
    expect(result.users[0]).toMatchObject({
      id: 2,
      role: "admin",
      campaignCount: 1,
    });
    expect(result.campaigns[0]).toMatchObject({
      id: 4,
      memberCount: 2,
      status: "active",
    });
    expect(result.metrics).toMatchObject({
      activeCampaigns: 1,
      memberships: 2,
      activeSessions: 1,
    });
    expect(result.characters[0]).toMatchObject({
      id: 9,
    });
    expect(result.characterCampaigns[0]).toMatchObject({
      characterId: 9,
      campaignId: 4,
    });
    expect(result.characterOwners[0]).toMatchObject({ userId: 2 });
    expect(result.characterGameMasters[0]).toEqual({
      campaignId: 4,
      userId: 2,
      username: "admin",
      isCampaignOwner: true,
    });
    expect(result.analytics.accountRoles).toEqual([{ key: "admin", value: 1 }]);
    expect(result.analytics.growth[0]).toEqual({
      label: "18.08",
      users: 1,
      campaigns: 0,
    });
  });

  it("uses dedicated write endpoints", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({ user: { id: 3, role: "user" } }),
    };
    const api = createAdminApiClient(client);
    await api.createUser({ username: "gm" });
    await api.changeUserRole(3, "user");
    await api.setCharacterCampaign(9, 4, true);
    await api.setCharacterOwner(9, 4, 2, false);

    expect(client.request).toHaveBeenNthCalledWith(1, "/admin/users", {
      method: "POST",
      body: { username: "gm" },
    });
    expect(client.request).toHaveBeenNthCalledWith(2, "/admin/users/3/role", {
      method: "PATCH",
      body: { role: "user" },
    });
    expect(client.request).toHaveBeenNthCalledWith(
      3,
      "/admin/characters/9/campaigns/4",
      { method: "PUT" },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      4,
      "/admin/characters/9/campaigns/4/owners/2",
      { method: "DELETE" },
    );
  });

  it("normalizes the Compendium administration overview", async () => {
    const client = {
      request: vi.fn().mockResolvedValue({
        metrics: { worlds: "2", entries: "2294", unresolvedLinks: "3" },
        worlds: [
          {
            universeId: "1",
            worldId: "4",
            name: "Old World",
            entries: "2294",
            owner: { userId: "2", username: "admin" },
            lastImport: { id: "9", processed: "2294" },
          },
        ],
        systems: [{ id: "1", code: "wfrp2ed", name: "Warhammer Fantasy 2e" }],
        imports: [{ id: "9", universeId: "1", processed: "2294" }],
        sources: [{ id: "3", key: "warhammerpl", documents: "2294" }],
      }),
    };

    const result = await createAdminApiClient(client).compendiumOverview();

    expect(client.request).toHaveBeenCalledWith("/admin/compendium", {});
    expect(result.metrics).toMatchObject({
      worlds: 2,
      entries: 2294,
      unresolvedLinks: 3,
    });
    expect(result.worlds[0]).toMatchObject({
      universeId: 1,
      worldId: 4,
      entries: 2294,
      owner: { userId: 2, username: "admin" },
    });
    expect(result.systems[0]).toEqual({
      id: 1,
      code: "wfrp2ed",
      name: "Warhammer Fantasy 2e",
    });
    expect(result.imports[0].processed).toBe(2294);
    expect(result.sources[0].documents).toBe(2294);
  });

  it("uses protected Compendium administration write endpoints", async () => {
    const client = { request: vi.fn().mockResolvedValue({}) };
    const api = createAdminApiClient(client);

    await api.createCompendiumWorld({ name: "New World", systemId: 1 });
    await api.updateCompendiumWorld(7, {
      name: "Renamed World",
      systemId: 2,
      isActive: true,
    });
    await api.updateCompendiumEntryPolicy(1, 12, { visibility: "gm" });
    await api.updateCompendiumProfile(1, 8, {
      status: "verified",
      usable: true,
    });
    await api.updateCompendiumSource(3, { licenseStatus: "reviewed" });
    await api.rollbackCompendiumImport(1, 9);
    await api.syncCompendiumWfrp2(1);

    expect(client.request).toHaveBeenNthCalledWith(
      1,
      "/admin/compendium/worlds",
      { method: "POST", body: { name: "New World", systemId: 1 } },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      2,
      "/admin/compendium/worlds/7",
      {
        method: "PATCH",
        body: { name: "Renamed World", systemId: 2, isActive: true },
      },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      3,
      "/admin/compendium/worlds/1/entries/12/policy",
      { method: "PATCH", body: { visibility: "gm" } },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      4,
      "/admin/compendium/worlds/1/profiles/8",
      { method: "PATCH", body: { status: "verified", usable: true } },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      5,
      "/admin/compendium/sources/3",
      { method: "PATCH", body: { licenseStatus: "reviewed" } },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      6,
      "/admin/compendium/worlds/1/imports/9/rollback",
      { method: "POST", body: {} },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      7,
      "/admin/compendium/worlds/1/sync-wfrp2",
      { method: "POST", body: {} },
    );
  });

  it("normalizes professions and uses protected profession CRUD endpoints", async () => {
    const client = {
      request: vi
        .fn()
        .mockResolvedValueOnce({
          items: [
            {
              id: "61",
              systemId: "1",
              systemCode: "wfrp2ed",
              name: "Arcykapłan",
              isAdvanced: 1,
              isMain: 0,
              skills: "46(6)",
              skillsDecoded: "Znajomość języka (kislevski)",
              skillItems: [
                {
                  raw: "46(6)",
                  display: "Znajomość języka (kislevski)",
                  decoded: true,
                },
              ],
              images: {
                male: {
                  id: "7",
                  slot: "male",
                  url: "/api/profession-assets/7/file",
                  width: "800",
                  height: "1600",
                },
              },
              usage: { characters: "2", pathReferences: "3" },
              canDelete: false,
            },
          ],
          systems: [{ id: "1", code: "wfrp2ed", name: "WFRP 2e" }],
          requirementOptions: {
            skills: [
              {
                raw: "46(6)",
                display: "Znajomość języka (kislevski)",
                systemId: "1",
                legacyId: "46",
                specializationId: "6",
              },
            ],
            talents: [],
          },
        })
        .mockResolvedValueOnce({
          profession: { id: "62", systemId: "1", name: "Nowa" },
        })
        .mockResolvedValueOnce({
          profession: {
            id: "62",
            systemId: "1",
            name: "Zmieniona",
            updatedAt: "2026-09-23 10:00:00",
          },
        })
        .mockResolvedValueOnce({ deleted: true, id: 62 }),
    };
    const api = createAdminApiClient(client);

    const overview = await api.professions();
    const created = await api.createProfession({ systemId: 1, name: "Nowa" });
    const updated = await api.updateProfession(62, {
      name: "Zmieniona",
      updatedAt: null,
    });
    await api.deleteProfession(62, "2026-09-23 10:00:00");

    expect(overview.items[0]).toMatchObject({
      id: 61,
      isAdvanced: true,
      isMain: false,
      usage: { characters: 2, pathReferences: 3 },
      canDelete: false,
      skills: "46(6)",
      skillsDecoded: "Znajomość języka (kislevski)",
      skillItems: [
        {
          raw: "46(6)",
          display: "Znajomość języka (kislevski)",
          decoded: true,
        },
      ],
      images: {
        male: {
          id: 7,
          slot: "male",
          url: "/api/profession-assets/7/file",
          width: 800,
          height: 1600,
        },
      },
    });
    expect(overview.systems[0]).toEqual({
      id: 1,
      code: "wfrp2ed",
      name: "WFRP 2e",
    });
    expect(overview.requirementOptions.skills[0]).toEqual({
      raw: "46(6)",
      display: "Znajomość języka (kislevski)",
      name: "Znajomość języka (kislevski)",
      systemId: 1,
      legacyId: 46,
      specializationId: 6,
    });
    expect(created.id).toBe(62);
    expect(updated.name).toBe("Zmieniona");
    expect(client.request).toHaveBeenNthCalledWith(1, "/admin/professions", {});
    expect(client.request).toHaveBeenNthCalledWith(2, "/admin/professions", {
      method: "POST",
      body: { systemId: 1, name: "Nowa" },
    });
    expect(client.request).toHaveBeenNthCalledWith(3, "/admin/professions/62", {
      method: "PATCH",
      body: { name: "Zmieniona", updatedAt: null },
    });
    expect(client.request).toHaveBeenNthCalledWith(4, "/admin/professions/62", {
      method: "DELETE",
      body: { updatedAt: "2026-09-23 10:00:00" },
    });
  });

  it("uses profession requirement preview and image removal endpoints", async () => {
    const client = {
      request: vi
        .fn()
        .mockResolvedValueOnce({
          skills: { display: "Znajomość języka (kislevski)" },
          talents: { display: "Bardzo silny" },
        })
        .mockResolvedValueOnce({
          profession: { id: "61", systemId: "1", name: "Arcykapłan" },
        }),
    };
    const api = createAdminApiClient(client);

    const decoded = await api.decodeProfessionRequirements({
      systemId: 1,
      skills: "46(6)",
      talents: "3",
    });
    const profession = await api.deleteProfessionImage(61, "female");

    expect(decoded.skills.display).toBe("Znajomość języka (kislevski)");
    expect(profession.id).toBe(61);
    expect(client.request).toHaveBeenNthCalledWith(
      1,
      "/admin/professions/decode",
      {
        method: "POST",
        body: { systemId: 1, skills: "46(6)", talents: "3" },
      },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      2,
      "/admin/professions/61/images/female",
      { method: "DELETE" },
    );
  });

  it("normalizes setting audio libraries and uses protected write endpoints", async () => {
    const client = {
      request: vi
        .fn()
        .mockResolvedValueOnce({
          libraries: [
            {
              id: "7",
              name: "Old World",
              systemId: "1",
              settingId: "2",
              isActive: 1,
              tracks: [
                {
                  id: "11",
                  libraryId: "7",
                  title: "Rain",
                  category: "ambient",
                  tags: ["weather"],
                },
              ],
            },
          ],
          systems: [{ id: "1", code: "wfrp2ed", name: "WFRP 2e" }],
          settings: [
            {
              id: "2",
              code: "old_world",
              name: "Old World",
              defaultSystemId: "1",
              systemIds: ["1", "2"],
            },
          ],
        })
        .mockResolvedValue({}),
    };
    const api = createAdminApiClient(client);

    const catalog = await api.audioOverview();
    await api.createAudioLibrary({
      name: "Old World",
      systemId: 1,
      settingId: 2,
      isActive: true,
    });
    await api.deleteAudioTrack(11);

    expect(catalog.libraries[0]).toMatchObject({
      id: 7,
      settingId: 2,
      tracks: [{ id: 11, category: "ambient", tags: ["weather"] }],
    });
    expect(catalog.settings[0].systemIds).toEqual([1, 2]);
    expect(client.request).toHaveBeenNthCalledWith(1, "/admin/audio", {});
    expect(client.request).toHaveBeenNthCalledWith(
      2,
      "/admin/audio/libraries",
      {
        method: "POST",
        body: {
          name: "Old World",
          systemId: 1,
          settingId: 2,
          isActive: true,
        },
      },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      3,
      "/admin/audio/tracks/11",
      { method: "DELETE" },
    );
  });

  it("uses optimistic revisions for token template CRUD", async () => {
    const client = {
      request: vi
        .fn()
        .mockResolvedValueOnce({
          items: [{ id: "7", name: "Orc", imageUrl: "https://img/orc.png" }],
        })
        .mockResolvedValueOnce({
          template: { id: "8", name: "Goblin", revision: "1" },
        })
        .mockResolvedValueOnce({
          template: { id: "8", name: "Goblin scout", revision: "2" },
        })
        .mockResolvedValueOnce({ deleted: true }),
    };
    const api = createAdminApiClient(client);

    const catalog = await api.tokenTemplates();
    const created = await api.createTokenTemplate({
      name: "Goblin",
      imageUrl: "https://img/goblin.png",
    });
    const updated = await api.updateTokenTemplate(8, {
      name: "Goblin scout",
      revision: 1,
    });
    await api.deleteTokenTemplate(8, updated.revision);

    expect(catalog[0]).toMatchObject({ id: 7, name: "Orc" });
    expect(created).toMatchObject({ id: 8, revision: 1 });
    expect(updated).toMatchObject({ id: 8, revision: 2 });
    expect(client.request).toHaveBeenNthCalledWith(
      3,
      "/admin/token-templates/8",
      { method: "PATCH", body: { name: "Goblin scout", revision: 1 } },
    );
    expect(client.request).toHaveBeenNthCalledWith(
      4,
      "/admin/token-templates/8",
      { method: "DELETE", body: { revision: 2 } },
    );
  });

  it("sends uploaded token images as multipart data", async () => {
    const originalFetch = window.fetch;
    window.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({
        template: { id: 12, name: "Ogre", imageAssetId: 4, revision: 2 },
      }),
    });
    try {
      const api = createAdminApiClient({ request: vi.fn() });
      const file = new File(["image"], "ogre.png", { type: "image/png" });
      const template = await api.updateTokenTemplate(
        12,
        { name: "Ogre", revision: 1 },
        file,
      );

      const [, request] = window.fetch.mock.calls[0];
      expect(request.method).toBe("POST");
      expect(request.body).toBeInstanceOf(FormData);
      expect(request.body.get("_method")).toBe("PATCH");
      expect(JSON.parse(request.body.get("payload"))).toEqual({
        name: "Ogre",
        revision: 1,
      });
      expect(request.body.get("file").name).toBe("ogre.png");
      expect(template).toMatchObject({ id: 12, imageAssetId: 4, revision: 2 });
    } finally {
      window.fetch = originalFetch;
    }
  });
});

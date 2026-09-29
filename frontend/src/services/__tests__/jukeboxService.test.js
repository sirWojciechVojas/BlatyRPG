import { describe, expect, it, vi } from "vitest";
import { JukeboxService } from "../jukeboxService";

const deferred = () => {
  let resolve;
  const promise = new Promise((done) => {
    resolve = done;
  });
  return { promise, resolve };
};

describe("JukeboxService campaign lifecycle", () => {
  it("ignores a catalog response from a campaign that is no longer active", async () => {
    const firstCatalog = deferred();
    const firstState = deferred();
    const secondCatalog = deferred();
    const secondState = deferred();
    const api = {
      tracks: vi.fn((campaignId) =>
        campaignId === 1 ? firstCatalog.promise : secondCatalog.promise,
      ),
      state: vi.fn((campaignId) =>
        campaignId === 1 ? firstState.promise : secondState.promise,
      ),
    };
    const sync = {
      setCatalog: vi.fn(),
      applyState: vi.fn(() => Promise.resolve()),
      handle: vi.fn(),
      destroy: vi.fn(),
    };
    const service = new JukeboxService({
      api,
      sync,
      mixer: { destroy: vi.fn(() => Promise.resolve()) },
    });

    const stale = service.enter(1);
    const current = service.enter(2);
    secondCatalog.resolve({ items: [{ id: 20 }], capabilities: {} });
    secondState.resolve({ capabilities: { canControl: true } });

    await expect(current).resolves.toMatchObject({
      tracks: [{ id: 20 }],
      capabilities: { canControl: true },
    });

    firstCatalog.resolve({ items: [{ id: 10 }], capabilities: {} });
    firstState.resolve({ capabilities: { canControl: false } });

    await expect(stale).resolves.toBeNull();
    expect(service.campaignId).toBe(2);
    expect(service.tracks).toEqual([{ id: 20 }]);
    expect(sync.setCatalog).toHaveBeenCalledTimes(1);
  });

  it("keeps the setting catalog separate from the reusable GM library", async () => {
    const setting = { id: 4, library: { scope: "system" } };
    const personal = {
      id: 9,
      attached: false,
      library: { scope: "personal" },
    };
    const api = {
      tracks: vi.fn().mockResolvedValue({
        items: [setting, personal],
        libraries: { setting: [setting], personal: [personal] },
        campaignCatalog: { setting: { id: 2, name: "Old World" } },
        capabilities: { canManage: true },
      }),
      state: vi.fn().mockResolvedValue({
        state: {},
        capabilities: { canControl: true },
      }),
      attach: vi.fn().mockResolvedValue({
        track: { ...personal, attached: true },
      }),
    };
    const sync = {
      setCatalog: vi.fn(),
      applyState: vi.fn(() => Promise.resolve()),
      destroy: vi.fn(),
    };
    const service = new JukeboxService({
      api,
      sync,
      mixer: { destroy: vi.fn(() => Promise.resolve()) },
    });

    const catalog = await service.enter(5);
    const attached = await service.attach(9);

    expect(catalog.settingTracks).toEqual([setting]);
    expect(catalog.personalTracks).toEqual([personal]);
    expect(catalog.campaignCatalog.setting.name).toBe("Old World");
    expect(attached.attached).toBe(true);
    expect(service.personalTracks[0].attached).toBe(true);
  });

  it("updates a personal track and refreshes the synchronization catalog", async () => {
    const original = {
      id: 9,
      title: "Old title",
      url: "https://www.youtube.com/watch?v=oldvideo123",
      library: { scope: "personal" },
    };
    const updated = {
      ...original,
      title: "New title",
      url: "https://www.youtube.com/watch?v=newvideo123",
    };
    const api = {
      tracks: vi.fn().mockResolvedValue({
        items: [original],
        libraries: { setting: [], personal: [original] },
        capabilities: { canManage: true },
      }),
      state: vi.fn().mockResolvedValue({ state: {}, capabilities: {} }),
      updatePersonal: vi.fn().mockResolvedValue({ track: updated }),
    };
    const sync = {
      setCatalog: vi.fn(),
      applyState: vi.fn().mockResolvedValue(),
      destroy: vi.fn(),
    };
    const service = new JukeboxService({
      api,
      sync,
      mixer: { destroy: vi.fn().mockResolvedValue() },
    });
    await service.enter(5);

    await expect(
      service.updatePersonal(9, {
        title: updated.title,
        url: updated.url,
      }),
    ).resolves.toEqual(updated);
    expect(api.updatePersonal).toHaveBeenCalledWith(5, 9, {
      title: updated.title,
      url: updated.url,
    });
    expect(service.personalTracks).toEqual([updated]);
    expect(sync.setCatalog).toHaveBeenLastCalledWith([updated]);
  });

  it("keeps channel queues isolated while several channels are active", async () => {
    const queues = {
      music: [{ id: 1, trackId: 10, title: "Theme" }],
      "ambient-1": [{ id: 2, trackId: 20, title: "Rain" }],
    };
    const api = {
      tracks: vi.fn().mockResolvedValue({
        items: [],
        queues,
        playlists: [],
        capabilities: { canManage: true },
      }),
      state: vi.fn().mockResolvedValue({
        state: {
          music: { trackId: 31, status: "playing" },
          "ambient-1": { trackId: 32, status: "playing" },
        },
        capabilities: { canControl: true },
      }),
      addQueueTrack: vi.fn().mockResolvedValue({
        queue: [...queues.music, { id: 3, trackId: 11, title: "Finale" }],
      }),
      moveQueueItem: vi.fn().mockResolvedValue({
        queue: [{ id: 3, trackId: 11, title: "Finale" }, queues.music[0]],
      }),
      removeQueueItem: vi.fn().mockResolvedValue({ queue: [] }),
    };
    const sync = {
      setCatalog: vi.fn(),
      applyState: vi.fn(() => Promise.resolve()),
      handle: vi.fn(),
      destroy: vi.fn(),
    };
    const service = new JukeboxService({
      api,
      sync,
      mixer: { destroy: vi.fn(() => Promise.resolve()) },
    });

    await service.enter(5);
    service.handleRealtime({
      type: "JUKEBOX_DEVICE",
      payload: { channelId: "music", deviceLabel: "Voicemeeter AUX" },
    });
    await service.addQueueTrack("music", 11);
    await service.moveQueueItem("music", 3, 0);

    expect(service.queues.music.map((item) => item.trackId)).toEqual([11, 10]);
    expect(service.queues["ambient-1"]).toEqual(queues["ambient-1"]);
    expect(sync.handle).toHaveBeenCalledWith(
      expect.objectContaining({ type: "JUKEBOX_DEVICE" }),
    );
    await service.removeQueueItem("music", 3);
    expect(service.queues.music).toEqual([]);
    expect(service.queues["ambient-1"]).toEqual(queues["ambient-1"]);
    expect(sync.applyState).toHaveBeenCalledWith(
      expect.objectContaining({
        state: expect.objectContaining({
          music: expect.objectContaining({ status: "playing" }),
          "ambient-1": expect.objectContaining({ status: "playing" }),
        }),
      }),
    );
  });

  it("starts and queues a playlist only on the selected channel", async () => {
    const api = {
      tracks: vi.fn().mockResolvedValue({
        items: [],
        queues: { music: [], "ambient-2": [{ id: 8, trackId: 88 }] },
        playlists: [{ id: 4, name: "Night", items: [{ trackId: 40 }] }],
        capabilities: {},
      }),
      state: vi.fn().mockResolvedValue({ capabilities: {} }),
      startPlaylist: vi.fn().mockResolvedValue({
        current: { trackId: 40 },
        playlist: { id: 4, name: "Night" },
        queue: [{ id: 9, trackId: 41, playlistId: 4 }],
      }),
      addQueuePlaylist: vi.fn().mockResolvedValue({
        queue: [{ id: 10, trackId: 40, playlistId: 4 }],
      }),
    };
    const service = new JukeboxService({
      api,
      sync: {
        setCatalog: vi.fn(),
        applyState: vi.fn(() => Promise.resolve()),
        destroy: vi.fn(),
      },
      mixer: { destroy: vi.fn(() => Promise.resolve()) },
    });
    await service.enter(5);

    const started = await service.startPlaylist("music", 4);
    await service.addQueuePlaylist("music", 4);

    expect(started.current.trackId).toBe(40);
    expect(service.queues.music).toEqual([
      { id: 10, trackId: 40, playlistId: 4 },
    ]);
    expect(service.queues["ambient-2"]).toEqual([{ id: 8, trackId: 88 }]);
  });
});

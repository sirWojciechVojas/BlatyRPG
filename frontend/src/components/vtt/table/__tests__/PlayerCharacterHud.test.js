import { existsSync, readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { createApp, h, nextTick, reactive } from "vue";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  cloneDataWithNumber,
  createPlayerCharacterHudModel,
  isHudCharacterAllowed,
  nextResourceValue,
  playerHudActions,
  selectHudCharacter,
  tokenResourcesWithValue,
} from "../playerCharacterHudModel";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/PlayerCharacterHud.vue",
);
const componentSource = readFileSync(componentPath, "utf8");
const runtimeDirectory = resolve(
  process.cwd(),
  "src/assets/app-ui/img/character-hud/v8",
);

let currentSession;
let app;
let host;

const authSession = {
  read: vi.fn(() => currentSession),
  subscribe: vi.fn((subscriber) => {
    subscriber(currentSession);
    return () => {};
  }),
};

const characterApiClient = {
  get: vi.fn(),
  update: vi.fn(),
};

const stripImports = (source) =>
  source
    .replace(/^import .*?;\n/gmu, "")
    .replace(/import \{[\s\S]*?\} from "\.\/playerCharacterHudModel";\n/u, "");

const loadComponent = () => {
  const { descriptor } = parse(componentSource, { filename: componentPath });
  const executable = stripImports(descriptor.script.content).replace(
    "export default {",
    "return {",
  );
  const dependencies = {
    authSession,
    characterApiClient,
    resolveCharacterAvatar: () => "/assets/avatar.svg",
    cloneDataWithNumber,
    createPlayerCharacterHudModel,
    isHudCharacterAllowed,
    nextResourceValue,
    playerHudActions,
    selectHudCharacter,
    tokenResourcesWithValue,
  };
  const names = Object.keys(dependencies);
  const component = new Function(...names, executable)(
    ...names.map((name) => dependencies[name]),
  );
  component.template = descriptor.template.content;
  return component;
};

const fullCharacter = (overrides = {}) => ({
  id: 9,
  campaignId: 4,
  ownerUserId: 7,
  name: "Alaric",
  avatarUrl: "/alaric.png",
  data: {
    health: { current: 5, max: 10 },
    experience: { current: 20, total: 100 },
  },
  revision: 2,
  updatedAt: "2026-08-30 10:00:00",
  capabilities: { canEdit: true },
  ...overrides,
});

const baseProps = () => ({
  campaignId: 4,
  characters: [
    {
      id: 9,
      campaignId: 4,
      ownerUserId: 7,
      name: "Alaric",
      capabilities: { canEdit: true },
    },
  ],
  focusedCharacterId: 9,
  selectedCharacterId: null,
  tokens: [],
  canManage: false,
  canOpenShop: true,
  contextPhase: "ready",
  contextGeneration: 1,
  contextUnauthorized: false,
  contextError: null,
});

const settle = async () => {
  await Promise.resolve();
  await nextTick();
  await Promise.resolve();
  await nextTick();
};

const mountHud = async (overrides = {}) => {
  const props = reactive({ ...baseProps(), ...overrides });
  const events = {
    visibility: vi.fn(),
    character: vi.fn(),
    map: vi.fn(),
    shop: vi.fn(),
    dice: vi.fn(),
    window: vi.fn(),
  };
  const store = { dispatch: vi.fn().mockResolvedValue(null) };
  const Hud = loadComponent();
  const Harness = {
    render() {
      return h(Hud, {
        ...props,
        ref: "hud",
        onVisibilityChange: events.visibility,
        onOpenCharacter: events.character,
        onFitMap: events.map,
        onOpenShop: events.shop,
        onOpenDice: events.dice,
        onOpenWindow: events.window,
      });
    },
  };
  host = document.createElement("div");
  document.body.append(host);
  app = createApp(Harness);
  app.config.globalProperties.$store = store;
  app.config.globalProperties.$t = (key, values = {}) =>
    `${key}${Object.keys(values).length ? ` ${JSON.stringify(values)}` : ""}`;
  app.config.globalProperties.$i18n = { locale: "pl" };
  const root = app.mount(host);
  await settle();
  return { events, props, root, store };
};

beforeEach(() => {
  currentSession = { user: { id: 7, username: "player" } };
  characterApiClient.get.mockReset().mockResolvedValue(fullCharacter());
  characterApiClient.update
    .mockReset()
    .mockImplementation((_campaignId, _characterId, draft) =>
      Promise.resolve(
        fullCharacter({ data: draft.data, revision: draft.revision + 1 }),
      ),
    );
  window.localStorage.clear();
});

afterEach(() => {
  app?.unmount();
  app = null;
  host?.remove();
  host = null;
});

describe("PlayerCharacterHud", () => {
  it("renders for the authenticated owner after the authorized detail request", async () => {
    const { events } = await mountHud();

    expect(characterApiClient.get).toHaveBeenCalledWith(4, 9);
    expect(
      host.querySelector(".hud-runtime.player-character-hud"),
    ).not.toBeNull();
    expect(host.textContent).toContain("Alaric");
    expect(events.visibility).toHaveBeenLastCalledWith(true);
  });

  it("does not select the first available character for a GM", async () => {
    await mountHud({ canManage: true, focusedCharacterId: null });

    expect(characterApiClient.get).not.toHaveBeenCalled();
    expect(host.querySelector(".hud-runtime")).toBeNull();
  });

  it("loads an authorized HUD while campaign context is reconciling", async () => {
    await mountHud({
      canManage: true,
      contextPhase: "loading",
      contextError: { code: "temporary_sync_error" },
    });

    expect(characterApiClient.get).toHaveBeenCalledWith(4, 9);
    expect(host.querySelector(".hud-runtime")).not.toBeNull();
  });

  it("keeps the current HUD visible while a context refresh reloads details", async () => {
    const { props } = await mountHud();
    let resolveRefresh;
    characterApiClient.get.mockReturnValueOnce(
      new Promise((resolveRequest) => {
        resolveRefresh = resolveRequest;
      }),
    );

    props.contextPhase = "loading";
    props.contextGeneration += 1;
    await nextTick();

    expect(host.querySelector(".hud-runtime")).not.toBeNull();
    expect(host.textContent).toContain("Alaric");

    resolveRefresh(fullCharacter({ name: "Alaric refreshed" }));
    await settle();
    expect(host.textContent).toContain("Alaric refreshed");
  });

  it("does not request or render a HUD for an unauthorized context", async () => {
    await mountHud({ contextUnauthorized: true });

    expect(characterApiClient.get).not.toHaveBeenCalled();
    expect(host.querySelector(".hud-runtime")).toBeNull();
  });

  it("renders the character explicitly selected by a GM", async () => {
    characterApiClient.get.mockResolvedValue(
      fullCharacter({ ownerUserId: 99, name: "GM selection" }),
    );
    await mountHud({
      canManage: true,
      focusedCharacterId: 9,
      characters: [
        { id: 9, campaignId: 4, ownerUserId: 99, name: "GM selection" },
      ],
    });

    expect(characterApiClient.get).toHaveBeenCalledWith(4, 9);
    expect(
      host.querySelector(".hud-runtime.player-character-hud"),
    ).not.toBeNull();
    expect(host.textContent).toContain("GM selection");
  });

  it("never renders a foreign character returned by a stale or invalid API response", async () => {
    characterApiClient.get.mockResolvedValue(
      fullCharacter({ ownerUserId: 99, name: "Foreign" }),
    );
    await mountHud();

    expect(host.querySelector(".hud-runtime")).toBeNull();
    expect(host.textContent).not.toContain("Foreign");
  });

  it("does not render without an assigned owned character", async () => {
    await mountHud({
      characters: [{ id: 11, ownerUserId: 99, name: "Observer target" }],
    });

    expect(characterApiClient.get).not.toHaveBeenCalled();
    expect(host.querySelector(".hud-runtime")).toBeNull();
  });

  it("disables unavailable actions and emits the real character, map, shop and dice actions", async () => {
    const { events } = await mountHud();
    const action = (id) => host.querySelector(`[data-hud-action="${id}"]`);

    expect(action("history").disabled).toBe(true);
    expect(action("advance").disabled).toBe(true);
    expect(action("traits").disabled).toBe(true);
    expect(action("notes").disabled).toBe(true);
    expect(action("character").disabled).toBe(false);
    expect(action("map").disabled).toBe(false);
    expect(action("shop").disabled).toBe(false);
    expect(action("dice").disabled).toBe(false);

    action("character").click();
    action("map").click();
    action("shop").click();
    action("dice").click();
    action("combat").click();

    expect(events.character).toHaveBeenCalledWith(9);
    expect(events.map).toHaveBeenCalledOnce();
    expect(events.shop).toHaveBeenCalledWith(9);
    expect(events.dice).toHaveBeenCalledOnce();
    expect(events.window).toHaveBeenCalledWith("combat");
  });

  it("disables the player shop action while its module is opening", async () => {
    await mountHud({ shopBusy: true });

    expect(host.querySelector('[data-hud-action="shop"]').disabled).toBe(true);
  });

  it("disables resource controls without edit rights and throughout a save", async () => {
    characterApiClient.get.mockResolvedValueOnce(
      fullCharacter({ capabilities: { canEdit: false } }),
    );
    await mountHud();

    expect(
      [...host.querySelectorAll(".hud-minus, .hud-plus")].every(
        (button) => button.disabled,
      ),
    ).toBe(true);

    app.unmount();
    app = null;
    host.remove();
    host = null;

    let resolveUpdate;
    characterApiClient.get.mockReset().mockResolvedValue(fullCharacter());
    characterApiClient.update.mockReturnValueOnce(
      new Promise((resolveUpdatePromise) => {
        resolveUpdate = resolveUpdatePromise;
      }),
    );
    await mountHud();
    const healthButtons = host.querySelectorAll(".hud-minus, .hud-plus");
    host.querySelector(".hud-plus").click();
    await nextTick();

    expect([...healthButtons].every((button) => button.disabled)).toBe(true);

    resolveUpdate(
      fullCharacter({
        data: { ...fullCharacter().data, health: { current: 6, max: 10 } },
      }),
    );
    await settle();
  });

  it("updates bound health through the existing token store action", async () => {
    const token = {
      id: 31,
      sceneId: 2,
      characterId: 9,
      revision: 4,
      locked: false,
      capabilities: { canControl: true },
      resources: {
        bars: [
          {
            label: "HP",
            value: 5,
            max: 10,
            attributePath: "health.current",
            maxAttributePath: "health.max",
          },
        ],
      },
    };
    const { store } = await mountHud({ tokens: [token] });

    host.querySelector(".hud-plus").click();
    await settle();

    expect(store.dispatch).toHaveBeenCalledWith("vtt/updateToken", {
      token,
      changes: {
        resources: {
          bars: [
            expect.objectContaining({
              attributePath: "health.current",
              value: 6,
            }),
          ],
        },
      },
    });
    expect(characterApiClient.update).not.toHaveBeenCalled();
  });

  it("ignores a late character response after switching campaigns", async () => {
    let resolveOld;
    characterApiClient.get.mockImplementation((_campaignId, characterId) => {
      if (Number(characterId) === 9) {
        return new Promise((resolveRequest) => {
          resolveOld = resolveRequest;
        });
      }
      return Promise.resolve(
        fullCharacter({
          id: 3,
          campaignId: 5,
          ownerUserId: 7,
          name: "Current campaign hero",
        }),
      );
    });
    const { props } = await mountHud();
    await nextTick();

    props.campaignId = 5;
    props.contextGeneration = 2;
    props.focusedCharacterId = 3;
    props.characters = [
      {
        id: 3,
        campaignId: 5,
        ownerUserId: 7,
        name: "Current campaign hero",
      },
    ];
    await settle();
    resolveOld(fullCharacter({ name: "Old campaign hero" }));
    await settle();

    expect(host.textContent).toContain("Current campaign hero");
    expect(host.textContent).not.toContain("Old campaign hero");
  });

  it("uses the delivered v8 runtime and keeps the semantic d100 button", async () => {
    await mountHud();
    const shell = host.querySelector(".hud-runtime");
    const avatar = shell.querySelector(".hud-avatar-image");
    const dieButton = shell.querySelector('[data-hud-action="dice"]');

    expect(shell.querySelector(".hud-runtime__stage")).not.toBeNull();
    expect(avatar.width).toBe(175);
    expect(avatar.height).toBe(175);
    expect(dieButton.tagName).toBe("BUTTON");
    expect(dieButton.getAttribute("type")).toBe("button");
    [
      "hud-shell.webp",
      "hud-shell-fhd.webp",
      "hud-atlas.webp",
      "hp-fill.webp",
      "xp-min-fill.webp",
      "xp-max-fill.webp",
    ].forEach((asset) =>
      expect(existsSync(resolve(runtimeDirectory, asset))).toBe(true),
    );
  });

  it("keeps experience fills within their 0–100% bounds", async () => {
    const { root } = await mountHud();
    const hud = root.$refs.hud;

    expect(hud.experienceRatio({ current: 0, required: 100 })).toBe(0);
    expect(hud.experienceRatio({ current: 100, required: 100 })).toBe(1);
    expect(hud.experienceRatio({ current: 160, required: 100 })).toBe(1);
    expect(hud.experienceRatio({ current: 1, required: 0 })).toBe(0);
  });
});

import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { createApp, h, nextTick, reactive } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";
import {
  createInventoryLayout,
  createInventoryPlacementPreview,
  createItemPrimaryAction,
  createMovePlan,
  EQUIPMENT_SLOT_ROWS,
  inventoryEncumbranceGroups,
  itemVisualTier,
  slotKind,
  slotLabelKey,
} from "../bountifyInventory";
import {
  BG_CARRY_LIMIT,
  BG_CARRY_UNIT_SHORT,
  calculateInventoryEncumbrance,
  resolveEncumbranceStatus,
} from "@/lib/trade/encumbrance";

const componentSource = (name) => {
  const path = resolve(
    process.cwd(),
    `src/components/characters/stats/${name}`,
  );
  return parse(readFileSync(path, "utf8"), { filename: path }).descriptor;
};

const withoutImports = (source) =>
  source.replace(/import[\s\S]*?from\s+["'][^"']+["'];\n/gu, "");

const ItemIcon = {
  props: ["item", "size"],
  template: '<span class="item-icon-stub"></span>',
};

const inventoryCellDescriptor = componentSource(
  "CharacterStatsInventoryCell.vue",
);
const inventoryCellScript = withoutImports(
  inventoryCellDescriptor.script.content,
).replace("export default {", "return {");
const InventoryCell = new Function(
  "ItemIcon",
  "itemVisualTier",
  "slotLabelKey",
  inventoryCellScript,
)(ItemIcon, itemVisualTier, slotLabelKey);
InventoryCell.template = inventoryCellDescriptor.template.content;

const panelDescriptor = componentSource("CharacterStatsEquipmentPanel.vue");
const modalStyles = readFileSync(
  resolve(
    process.cwd(),
    "src/components/characters/styles/PlayerCharacterStatsModal.css",
  ),
  "utf8",
);
const panelScript = withoutImports(panelDescriptor.script.content).replace(
  "export default {",
  "return {",
);
const Panel = new Function(
  "CharacterStatsInventoryCell",
  "CharacterStatsItemDialog",
  "ItemIcon",
  "createInventoryLayout",
  "createInventoryPlacementPreview",
  "createItemPrimaryAction",
  "createMovePlan",
  "EQUIPMENT_SLOT_ROWS",
  "inventoryEncumbranceGroups",
  "slotKind",
  "slotLabelKey",
  "BG_CARRY_LIMIT",
  "BG_CARRY_UNIT_SHORT",
  "calculateInventoryEncumbrance",
  "resolveEncumbranceStatus",
  "backpackIllustration",
  "pouchIllustration",
  panelScript,
)(
  InventoryCell,
  {
    props: [
      "item",
      "actionLabel",
      "actionDisabled",
      "actionHint",
      "actionBusy",
    ],
    emits: ["close", "primary-action"],
    template: "<span></span>",
  },
  ItemIcon,
  createInventoryLayout,
  createInventoryPlacementPreview,
  createItemPrimaryAction,
  createMovePlan,
  EQUIPMENT_SLOT_ROWS,
  inventoryEncumbranceGroups,
  slotKind,
  slotLabelKey,
  BG_CARRY_LIMIT,
  BG_CARRY_UNIT_SHORT,
  calculateInventoryEncumbrance,
  resolveEncumbranceStatus,
  "/backpack.png",
  "/pouch.png",
);
Panel.template = panelDescriptor.template.content;

let app;
let host;

afterEach(() => {
  vi.useRealTimers();
  app?.unmount();
  app = null;
  host?.remove();
  host = null;
  document
    .querySelectorAll(".character-stats-item-tooltip")
    .forEach((element) => element.remove());
});

const settle = async () => {
  await nextTick();
  await Promise.resolve();
  await nextTick();
};

describe("CharacterStatsEquipmentPanel runtime stability", () => {
  it("keeps component instances valid while tooltip, placement and items update", async () => {
    vi.useFakeTimers();
    const props = reactive({
      model: {
        armor: {
          head: 0,
          body: 0,
          rightArm: 0,
          leftArm: 0,
          rightLeg: 0,
          leftLeg: 0,
        },
        wallet: { system: "empire", crown: 0, shilling: 0, penny: 0 },
      },
      avatar: "/avatar.png",
      avatarAlt: "Hero",
      canEditInventory: true,
      moving: false,
      inventoryItems: [
        {
          ID: 1,
          NAME: "Hełm",
          SLOT: "0|0",
          ITEM_PLACE: "0|0",
          ITEM_GENRE: "head",
          QUANTITY: 1,
        },
        {
          ID: 2,
          NAME: "Czapka",
          SLOT: "head",
          ITEM_PLACE: "head",
          ITEM_GENRE: "head",
          QUANTITY: 1,
        },
      ],
    });
    const runtimeErrors = vi.fn();
    const Harness = {
      render() {
        return h(Panel, {
          ...props,
          onMoveItems: (moves) => {
            props.moving = true;
            props.inventoryItems = props.inventoryItems.map((item) => {
              const move = moves.find(
                ({ item: moved }) => moved.ID === item.ID,
              );
              return move
                ? { ...item, SLOT: move.slot, ITEM_PLACE: move.slot }
                : item;
            });
          },
        });
      },
    };

    host = document.createElement("div");
    document.body.append(host);
    app = createApp(Harness);
    app.config.errorHandler = runtimeErrors;
    app.config.globalProperties.$t = (key) => key;
    app.mount(host);
    await settle();

    const source = host.querySelector(".inventory-cell--0-0");
    source.dispatchEvent(new MouseEvent("mouseenter"));
    await settle();
    expect(
      document.querySelectorAll(".character-stats-item-tooltip"),
    ).toHaveLength(1);

    source.click();
    await vi.advanceTimersByTimeAsync(550);
    await settle();
    host.querySelector(".inventory-cell--head").click();
    await vi.advanceTimersByTimeAsync(550);
    await settle();
    props.moving = false;
    await settle();

    expect(
      host.querySelector(".inventory-cell--head .item-icon-stub"),
    ).not.toBeNull();
    expect(
      host.querySelector(".character-stats-equipment__status").textContent,
    ).toContain("inventory.success.equipExchange");
    expect(document.querySelector(".character-stats-item-tooltip")).toBeNull();
    expect(runtimeErrors).not.toHaveBeenCalled();
  });

  it("performs one primary action on double-click without selecting or opening details", async () => {
    vi.useFakeTimers();
    const activate = vi.fn();
    const primaryAction = vi.fn();
    const openDetails = vi.fn();
    const Harness = {
      render() {
        return h(InventoryCell, {
          cell: {
            slot: "0|0",
            frame: "single",
            item: itemForDoubleClick,
          },
          side: "personal",
          placement: {},
          onActivate: activate,
          onPrimaryAction: primaryAction,
          onOpenDetails: openDetails,
        });
      },
    };
    const itemForDoubleClick = {
      ID: 9,
      NAME: "Koszula",
      ITEM_CLASS: "CLOTH",
      ITEM_GENRE: "BODY",
    };

    host = document.createElement("div");
    document.body.append(host);
    app = createApp(Harness);
    app.config.globalProperties.$t = (key) => key;
    app.mount(host);
    await settle();

    const itemElement = host.querySelector(".inventory-cell__item");
    itemElement.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    itemElement.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    itemElement.dispatchEvent(new MouseEvent("dblclick", { bubbles: true }));
    await vi.advanceTimersByTimeAsync(550);
    await settle();

    expect(primaryAction).toHaveBeenCalledTimes(1);
    expect(primaryAction).toHaveBeenCalledWith("0|0");
    expect(activate).not.toHaveBeenCalled();
    expect(openDetails).not.toHaveBeenCalled();
  });

  it("renders a disabled red-overlay copy in the paired hand slot", async () => {
    const crossbow = {
      ID: 7,
      NAME: "Kusza",
      ITEM_CLASS: "WEAPON",
      ITEM_GENRE: "arms",
      WEAPON: { HANDED: "2H" },
    };
    const props = reactive({
      cell: { slot: "armL1", item: null, frame: "single" },
      side: "left",
      placement: {
        disabled: true,
        dimmed: true,
        blockedByTwoHanded: true,
        blockedItem: crossbow,
      },
    });
    const dropItem = vi.fn();
    const Harness = {
      render() {
        return h(InventoryCell, { ...props, onDropItem: dropItem });
      },
    };

    host = document.createElement("div");
    document.body.append(host);
    app = createApp(Harness);
    app.config.globalProperties.$t = (key) => key;
    app.mount(host);
    await settle();

    const pairedSlot = host.querySelector(".inventory-cell--armL1");
    expect(pairedSlot.disabled).toBe(false);
    expect(pairedSlot.getAttribute("aria-disabled")).toBe("true");
    expect(pairedSlot.classList).toContain(
      "inventory-cell--two-handed-blocked",
    );
    expect(pairedSlot.classList).toContain("inventory-cell--two-handed-ghost");
    expect(
      pairedSlot.querySelector(".inventory-cell__item--two-handed-ghost"),
    ).not.toBeNull();
    expect(pairedSlot.textContent).toBe("");
    expect(modalStyles).toMatch(
      /inventory-cell__item--two-handed-ghost \.item-icon[\s\S]*opacity:\s*0\.58[\s\S]*hue-rotate\(326deg\)/u,
    );
    expect(modalStyles).not.toContain(
      "button.inventory-cell--two-handed-blocked::after",
    );

    pairedSlot.dispatchEvent(
      new Event("drop", { bubbles: true, cancelable: true }),
    );
    expect(dropItem).toHaveBeenCalledWith("armL1");
  });

  it("explains an attempted move onto the inactive paired hand", async () => {
    vi.useFakeTimers();
    const moveItems = vi.fn();
    const props = {
      model: {
        armor: {
          head: 0,
          body: 0,
          rightArm: 0,
          leftArm: 0,
          rightLeg: 0,
          leftLeg: 0,
        },
        wallet: { system: "empire", crown: 0, shilling: 0, penny: 0 },
      },
      avatar: "/avatar.png",
      avatarAlt: "Hero",
      canEditInventory: true,
      moving: false,
      inventoryItems: [
        {
          ID: 7,
          NAME: "Kusza",
          SLOT: "armR1",
          ITEM_CLASS: "WEAPON",
          ITEM_GENRE: "arms",
          WEAPON: { HANDED: "2H" },
        },
        {
          ID: 8,
          NAME: "Miecz",
          SLOT: "0|0",
          ITEM_CLASS: "WEAPON",
          ITEM_GENRE: "MELEE",
        },
      ],
    };
    const Harness = {
      render() {
        return h(Panel, { ...props, onMoveItems: moveItems });
      },
    };

    host = document.createElement("div");
    document.body.append(host);
    app = createApp(Harness);
    app.config.globalProperties.$t = (key) => key;
    app.mount(host);
    await settle();

    host.querySelector(".inventory-cell--0-0").click();
    await vi.advanceTimersByTimeAsync(550);
    const blockedSlot = host.querySelector(".inventory-cell--armL1");
    blockedSlot.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    blockedSlot.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    blockedSlot.dispatchEvent(new MouseEvent("dblclick", { bubbles: true }));
    await vi.advanceTimersByTimeAsync(550);
    await settle();

    expect(moveItems).not.toHaveBeenCalled();
    expect(
      host.querySelector(".character-stats-equipment__status").textContent,
    ).toContain("inventory.errors.inactive_hand_slot");
  });

  it("marks the active hand pair and exposes a two-button set switcher", async () => {
    vi.useFakeTimers();
    const activateWeaponSet = vi.fn();
    const moveItems = vi.fn();
    const props = {
      model: {
        armor: {
          head: 0,
          body: 0,
          rightArm: 0,
          leftArm: 0,
          rightLeg: 0,
          leftLeg: 0,
        },
        wallet: { system: "empire", crown: 0, shilling: 0, penny: 0 },
      },
      avatar: "/avatar.png",
      avatarAlt: "Hero",
      canEditInventory: true,
      moving: false,
      activeWeaponSet: 1,
      inventoryItems: [
        {
          ID: 12,
          NAME: "Topór",
          SLOT: "0|0",
          ITEM_CLASS: "WEAPON",
          ITEM_GENRE: "MELEE",
        },
      ],
    };
    const Harness = {
      render() {
        return h(Panel, {
          ...props,
          onActivateWeaponSet: activateWeaponSet,
          onMoveItems: moveItems,
        });
      },
    };

    host = document.createElement("div");
    document.body.append(host);
    app = createApp(Harness);
    app.config.globalProperties.$t = (key) => key;
    app.mount(host);
    await settle();

    expect(
      host
        .querySelector(".inventory-cell--armR1")
        .classList.contains("inventory-cell--weapon-set-active"),
    ).toBe(true);
    expect(
      host
        .querySelector(".inventory-cell--armL1")
        .classList.contains("inventory-cell--weapon-set-active"),
    ).toBe(true);
    expect(
      host
        .querySelector(".inventory-cell--armR2")
        .classList.contains("inventory-cell--weapon-set-active"),
    ).toBe(false);

    const buttons = host.querySelectorAll(
      ".character-stats-weapon-set-switcher button",
    );
    expect(buttons).toHaveLength(2);

    host.querySelector(".inventory-cell--0-0").click();
    await vi.advanceTimersByTimeAsync(550);
    host.querySelector(".inventory-cell--armR2").click();
    await vi.advanceTimersByTimeAsync(550);
    expect(moveItems).toHaveBeenCalledWith(expect.any(Array), {
      activeWeaponSet: 2,
    });
    expect(activateWeaponSet).not.toHaveBeenCalled();

    buttons[1].click();
    expect(activateWeaponSet).toHaveBeenCalledWith(2);
  });

  it("renders every inventory cell as one stable root without a per-cell Teleport", () => {
    expect(inventoryCellDescriptor.template.content.trim()).toMatch(
      /^<button[\s\S]*<\/button>$/u,
    );
    expect(inventoryCellDescriptor.template.content).not.toContain("Teleport");
  });
});

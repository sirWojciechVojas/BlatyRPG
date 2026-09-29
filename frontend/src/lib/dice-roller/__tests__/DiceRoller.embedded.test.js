import { describe, expect, it, vi } from "vitest";
import { DiceRoller } from "../includes/DiceRoller";
import { DiceRoom } from "../includes/DiceRoom";

const createRoom = ({ rolling = false } = {}) => {
  const notationVectors = { notation: "1d100+1d10", vectors: [{}] };
  const DiceBox = {
    rolling,
    running: 10,
    startClickThrow: vi.fn(() => notationVectors),
    clearDice: vi.fn(),
  };
  return {
    DiceBox,
    set: { value: "" },
    info_div: document.createElement("div"),
    selector_div: document.createElement("div"),
    sendNetworkedRoll: vi.fn(),
    show_selector: vi.fn(),
  };
};

describe("DiceRoller embedded controls", () => {
  it("starts a programmatic notation only when the physics box is idle", () => {
    const roller = new DiceRoller({ embedded: true });
    const room = createRoom();
    roller.DiceRoom = room;

    expect(roller.roll("1d100+1d10")).toBe(true);
    expect(room.set.value).toBe("1d100+1d10");
    expect(room.DiceBox.startClickThrow).toHaveBeenCalledWith("1d100+1d10");
    expect(room.sendNetworkedRoll).toHaveBeenCalledOnce();

    room.DiceBox.rolling = true;
    expect(roller.roll("1d20")).toBe(false);
    expect(room.sendNetworkedRoll).toHaveBeenCalledOnce();
  });

  it("clears the renderer state and can show the configured selector", () => {
    const roller = new DiceRoller({ embedded: true });
    const room = createRoom();
    roller.DiceRoom = room;
    roller.diceDisplayList = ["d4", "d100"];

    expect(roller.showSelector()).toBe(true);
    expect(room.show_selector).toHaveBeenCalledWith({
      diceList: ["d4", "d100"],
    });

    expect(roller.clear()).toBe(true);
    expect(room.DiceBox.running).toBe(false);
    expect(room.DiceBox.rolling).toBe(false);
    expect(room.DiceBox.clearDice).toHaveBeenCalledOnce();
    expect(room.info_div.style.display).toBe("none");
    expect(room.selector_div.style.display).toBe("none");
  });

  it("reports the result after the physics roll completes", () => {
    const previousDiceRoller = window.DiceRoller;
    const previousDiceFactory = window.DiceFactory;
    const waitform = document.createElement("div");
    waitform.id = "waitform";
    const labelhelp = document.createElement("div");
    labelhelp.id = "labelhelp";
    document.body.append(waitform, labelhelp);
    window.DiceRoller = { Teal: { offline: true } };
    window.DiceFactory = { get: vi.fn(() => ({})) };

    const onRollComplete = vi.fn();
    const context = {
      TealChat: {
        roll_uuid: null,
        add_unconfirmed_message: vi.fn(),
        confirm_message: vi.fn(),
      },
      label: document.createElement("div"),
      info_div: document.createElement("div"),
      selector_div: document.createElement("div"),
      DiceBox: {
        tally: true,
        rolling: true,
        diceList: [
          {
            notation: { type: "d100" },
            getLastValue: () => ({ value: 0, label: "00" }),
          },
          {
            notation: { type: "d10" },
            getLastValue: () => ({ value: 0, label: "0" }),
          },
        ],
        rollDice: (notation, complete) => complete(notation),
        getDiceTotals: () => ({
          rolls: "[00]+[0]",
          labels: "",
          values: 0,
        }),
        clearDice: vi.fn(),
      },
      diceDisplayEnabled: true,
      chatEnabled: false,
      onRollComplete,
      make_notation_for_log: vi.fn(() => "1d100+1d10"),
    };

    try {
      DiceRoom.prototype.action_roll.call(context, {
        user: "Yourself",
        notation: "1d100+1d10",
        vectors: [],
        colorset: "",
        texture: "",
        material: "",
        time: "",
      });

      expect(onRollComplete).toHaveBeenCalledWith({
        notation: "1d100+1d10",
        rolls: "[00]+[0]",
        labels: "",
        total: 100,
        dice: [
          {
            type: "d100",
            value: 0,
            label: "00",
            display: "00",
          },
          {
            type: "d10",
            value: 0,
            label: "0",
            display: "0",
          },
        ],
      });
    } finally {
      waitform.remove();
      labelhelp.remove();
      window.DiceRoller = previousDiceRoller;
      window.DiceFactory = previousDiceFactory;
    }
  });
});

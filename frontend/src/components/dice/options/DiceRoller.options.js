import { onBeforeUnmount, onMounted, nextTick, ref } from "vue";
import { createDiceRoller } from "../../../lib/dice-roller/index.js";
import DiceRollerSettingsPanel from "../DiceRollerSettingsPanel.vue";

export default {
  name: "DiceRoller",
  components: {
    DiceRollerSettingsPanel,
  },
  props: {
    assetBaseUrl: {
      type: String,
      default: "/dice_roller",
    },
    themeId: {
      type: String,
      default: "blue-felt",
    },
    embedded: {
      type: Boolean,
      default: false,
    },
    showAdvancedControls: {
      type: Boolean,
      default: true,
    },
    autoStart: {
      type: Boolean,
      default: true,
    },
    showDiceDisplayToggle: {
      type: Boolean,
      default: true,
    },
    showDragThrowToggle: {
      type: Boolean,
      default: true,
    },
    diceDisplayEnabled: {
      type: Boolean,
      default: false,
    },
    dragThrowEnabled: {
      type: Boolean,
      default: true,
    },
    chatEnabled: {
      type: Boolean,
      default: true,
    },
    rngSeed: {
      type: [Number, String],
      default: null,
    },
    canvasHeightOffset: {
      type: Number,
      default: 0,
    },
    diceScale: {
      type: Number,
      default: 1,
    },
    diceScaleThrow: {
      type: Number,
    },
    diceScaleSelector: {
      type: Number,
    },
    diceBoxDimensions: {
      type: Object,
      default: null,
    },
    diceSelectorDimensions: {
      type: Object,
      default: null,
    },
    diceDisplayList: {
      type: Array,
      default: () => [
        "df",
        "d4",
        "d6",
        "d8",
        "d10",
        "d100",
        "d12",
        "d20",
        "dc",
      ],
    },
  },
  emits: ["ready", "error", "roll-complete"],
  setup(props, { emit, expose }) {
    let diceRoller = null;
    let pendingAction = null;
    let resizeObserver = null;
    const root = ref(null);
    const originalBodyStyles = {
      backgroundColor: document.body.style.backgroundColor,
      color: document.body.style.color,
      backgroundImage: document.body.style.backgroundImage,
      backgroundSize: document.body.style.backgroundSize,
      backgroundRepeat: document.body.style.backgroundRepeat,
      overflow: document.body.style.overflow,
      margin: document.body.style.margin,
      width: document.body.style.width,
      height: document.body.style.height,
    };
    const originalHtmlStyles = {
      overflow: document.documentElement.style.overflow,
      margin: document.documentElement.style.margin,
      width: document.documentElement.style.width,
      height: document.documentElement.style.height,
    };

    const removeDiceRollerStyles = () => {
      const styleIds = [
        "dice-roller-core-style",
        "dice-roller-main-style",
        "dice-roller-default-style",
        "dice-roller-theme-style",
      ];
      styleIds.forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
          el.remove();
        }
      });
      document.body.style.backgroundColor = originalBodyStyles.backgroundColor;
      document.body.style.color = originalBodyStyles.color;
      document.body.style.backgroundImage = originalBodyStyles.backgroundImage;
      document.body.style.backgroundSize = originalBodyStyles.backgroundSize;
      document.body.style.backgroundRepeat =
        originalBodyStyles.backgroundRepeat;
      document.body.style.overflow = originalBodyStyles.overflow;
      document.body.style.margin = originalBodyStyles.margin;
      document.body.style.width = originalBodyStyles.width;
      document.body.style.height = originalBodyStyles.height;
      document.documentElement.style.overflow = originalHtmlStyles.overflow;
      document.documentElement.style.margin = originalHtmlStyles.margin;
      document.documentElement.style.width = originalHtmlStyles.width;
      document.documentElement.style.height = originalHtmlStyles.height;
    };

    const startDiceRoller = async () => {
      try {
        await nextTick();
        if (!props.embedded) {
          document.body.style.overflow = "hidden";
          document.body.style.margin = "0";
          document.body.style.width = "100%";
          document.body.style.height = "100%";
          document.documentElement.style.overflow = "hidden";
          document.documentElement.style.margin = "0";
          document.documentElement.style.width = "100%";
          document.documentElement.style.height = "100%";
        }
        const fallbackScale =
          typeof props.diceScale === "number" &&
          Number.isFinite(props.diceScale)
            ? props.diceScale
            : 1;
        const diceScaleThrow =
          typeof props.diceScaleThrow === "number" &&
          Number.isFinite(props.diceScaleThrow)
            ? props.diceScaleThrow
            : fallbackScale;
        const diceScaleSelector =
          typeof props.diceScaleSelector === "number" &&
          Number.isFinite(props.diceScaleSelector)
            ? props.diceScaleSelector
            : fallbackScale;
        diceRoller = await createDiceRoller({
          assetBaseUrl: props.assetBaseUrl,
          themeId: props.themeId,
          embedded: props.embedded,
          loadStyles: !props.embedded,
          autoStart: props.autoStart,
          chatEnabled: props.chatEnabled,
          rngSeed: props.rngSeed,
          canvasHeightOffset: props.canvasHeightOffset,
          diceScaleThrow,
          diceScaleSelector,
          diceDisplayEnabled: props.diceDisplayEnabled,
          dragThrowEnabled: props.dragThrowEnabled,
          diceBoxDimensions: props.diceBoxDimensions,
          diceSelectorDimensions: props.diceSelectorDimensions,
          diceDisplayList: props.diceDisplayList,
          onRollComplete: (result) => emit("roll-complete", result),
          onError: (error) => emit("error", error),
        });
        if (props.embedded && root.value && "ResizeObserver" in window) {
          resizeObserver = new ResizeObserver(() => diceRoller?.resize?.());
          resizeObserver.observe(root.value);
        }
        emit("ready", diceRoller);
        if (pendingAction?.type === "roll") {
          diceRoller.roll(pendingAction.notation);
        } else if (pendingAction?.type === "selector") {
          diceRoller.showSelector();
        }
        pendingAction = null;
      } catch (error) {
        emit("error", error);
      }
    };

    const roll = (notation) => {
      if (!diceRoller) {
        pendingAction = { type: "roll", notation };
        return true;
      }
      return diceRoller.roll(notation);
    };

    const showSelector = () => {
      if (!diceRoller) {
        pendingAction = { type: "selector" };
        return true;
      }
      return diceRoller.showSelector();
    };

    const clear = () => {
      pendingAction = null;
      return diceRoller?.clear?.() ?? false;
    };

    const resize = () => diceRoller?.resize?.();

    expose({ roll, showSelector, clear, resize });

    onMounted(startDiceRoller);

    onBeforeUnmount(() => {
      resizeObserver?.disconnect();
      if (diceRoller?.destroy) {
        diceRoller.destroy();
      } else if (diceRoller?.close_socket) {
        diceRoller.close_socket();
      }
      if (diceRoller?.DiceRoom?.DiceBox) {
        diceRoller.DiceRoom.DiceBox.running = false;
      }
      if (!props.embedded) removeDiceRollerStyles();
    });

    return { root };
  },
};

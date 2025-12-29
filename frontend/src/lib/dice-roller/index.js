import $ from "jquery";
import "jquery-ui-dist/jquery-ui.css";
import "spectrum-colorpicker/spectrum.css";

import { DiceRoller } from "./includes/DiceRoller.js";
import { DiceColors, TEXTURELIST } from "./includes/DiceColors.js";
import {
  DEFAULT_ASSET_BASE_URL,
  normalizeBaseUrl,
  resolveTextureList,
} from "./assetPaths.js";

const CORE_STYLE_ID = "dice-roller-core-style";
const MAIN_STYLE_ID = "dice-roller-main-style";
const BASE_STYLE_ID = "dice-roller-default-style";

let pluginsLoadedPromise = null;

const bindGlobals = () => {
  window.$ = $;
  window.jQuery = $;
};

const loadJqueryPlugins = () => {
  if (!pluginsLoadedPromise) {
    bindGlobals();
    pluginsLoadedPromise = Promise.all([
      import("jquery-ui-dist/jquery-ui"),
      import("jquery-ui-touch-punch"),
      import("spectrum-colorpicker"),
    ]).then(() => undefined);
  }
  return pluginsLoadedPromise;
};

const ensureStyle = (id, href) => {
  if (!href) return;
  const existing = document.getElementById(id);
  if (existing) {
    existing.href = href;
    return;
  }
  const link = document.createElement("link");
  link.id = id;
  link.rel = "stylesheet";
  link.type = "text/css";
  link.href = href;
  document.head.appendChild(link);
};

const ensureBaseStyles = (assetBaseUrl) => {
  const baseUrl = normalizeBaseUrl(assetBaseUrl);
  ensureStyle(MAIN_STYLE_ID, `${baseUrl}/main.css`);
  ensureStyle(CORE_STYLE_ID, `${baseUrl}/dice.css`);
  ensureStyle(BASE_STYLE_ID, `${baseUrl}/themes/default/style.css`);
};

const createDiceRoller = (options = {}) => {
  const assetBaseUrl = normalizeBaseUrl(
    options.assetBaseUrl || DEFAULT_ASSET_BASE_URL,
  );
  const themeId = options.themeId || "blue-felt";
  const diceDisplayEnabled =
    typeof options.diceDisplayEnabled === "boolean"
      ? options.diceDisplayEnabled
      : true;
  const fallbackScale =
    typeof options.diceScale === "number" && Number.isFinite(options.diceScale)
      ? options.diceScale
      : 1;
  const diceScaleThrow =
    typeof options.diceScaleThrow === "number" &&
    Number.isFinite(options.diceScaleThrow)
      ? options.diceScaleThrow
      : fallbackScale;
  const diceScaleSelector =
    typeof options.diceScaleSelector === "number" &&
    Number.isFinite(options.diceScaleSelector)
      ? options.diceScaleSelector
      : fallbackScale;
  const chatEnabled =
    typeof options.chatEnabled === "boolean" ? options.chatEnabled : true;
  const rngSeed =
    options.rngSeed !== undefined && options.rngSeed !== null
      ? options.rngSeed
      : null;
  const dragThrowEnabled =
    typeof options.dragThrowEnabled === "boolean"
      ? options.dragThrowEnabled
      : true;
  const canvasHeightOffset =
    typeof options.canvasHeightOffset === "number" &&
    Number.isFinite(options.canvasHeightOffset)
      ? options.canvasHeightOffset
      : 0;
  const diceBoxDimensions = options.diceBoxDimensions || null;
  const diceSelectorDimensions = options.diceSelectorDimensions || null;
  const diceDisplayList = Array.isArray(options.diceDisplayList)
    ? options.diceDisplayList
    : null;

  ensureBaseStyles(assetBaseUrl);

  return new Promise((resolve, reject) => {
    const diceColors = new DiceColors();
    const textures = resolveTextureList(TEXTURELIST, assetBaseUrl);

    loadJqueryPlugins()
      .then(() => {
        diceColors.ImageLoader(textures, (images) => {
          try {
            const diceRoller = new DiceRoller({
              assetBaseUrl,
              themeId,
              chatEnabled,
              rngSeed,
              dragThrowEnabled,
              canvasHeightOffset,
              diceScaleThrow,
              diceScaleSelector,
              diceDisplayEnabled,
              diceBoxDimensions,
              diceSelectorDimensions,
              diceDisplayList,
            });
            diceRoller.initialize(images, {
              assetBaseUrl,
              themeId,
              chatEnabled,
              rngSeed,
              dragThrowEnabled,
              canvasHeightOffset,
              diceScaleThrow,
              diceScaleSelector,
              diceDisplayEnabled,
              diceBoxDimensions,
              diceSelectorDimensions,
              diceDisplayList,
            });
            if (options.autoStart !== false && diceRoller.button_single_press) {
              diceRoller.button_single_press();
            }
            resolve(diceRoller);
          } catch (error) {
            if (typeof options.onError === "function") {
              options.onError(error);
            }
            reject(error);
          }
        });
      })
      .catch((error) => {
        if (typeof options.onError === "function") {
          options.onError(error);
        }
        reject(error);
      });
  });
};

export { createDiceRoller };
export { DiceRoller };

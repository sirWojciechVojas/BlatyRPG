import $ from "jquery";
import "jquery-ui-dist/jquery-ui";
import "jquery-ui-dist/jquery-ui.css";
import "jquery-ui-touch-punch";
import "spectrum-colorpicker";
import "spectrum-colorpicker/spectrum.css";

import { DiceRoller } from "./includes/DiceRoller.js";
import { DiceColors, TEXTURELIST } from "./includes/DiceColors.js";
import {
  DEFAULT_ASSET_BASE_URL,
  normalizeBaseUrl,
  resolveTextureList,
} from "./assetPaths.js";

const BASE_STYLE_ID = "dice-roller-default-style";

const bindGlobals = () => {
  window.$ = $;
  window.jQuery = $;
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
  ensureStyle(BASE_STYLE_ID, `${baseUrl}/themes/default/style.css`);
};

const createDiceRoller = (options = {}) => {
  const assetBaseUrl = normalizeBaseUrl(
    options.assetBaseUrl || DEFAULT_ASSET_BASE_URL
  );
  const themeId = options.themeId || "blue-felt";

  bindGlobals();
  ensureBaseStyles(assetBaseUrl);

  return new Promise((resolve, reject) => {
    const diceColors = new DiceColors();
    const textures = resolveTextureList(TEXTURELIST, assetBaseUrl);

    diceColors.ImageLoader(textures, (images) => {
      try {
        const diceRoller = new DiceRoller({ assetBaseUrl, themeId });
        diceRoller.initialize(images, { assetBaseUrl, themeId });
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
  });
};

export { createDiceRoller };
export { DiceRoller };

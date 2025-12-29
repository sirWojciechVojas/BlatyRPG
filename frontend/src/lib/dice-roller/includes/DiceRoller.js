"use strict";
import $ from "jquery";
import { Teal } from "./Teal.js";
import { DiceRoom } from "./DiceRoom.js";
import { DiceFactory } from "./DiceFactory.js";
import { DiceFavorites } from "./DiceFavorites.js";
import {
  DiceColors,
  THEMES,
  COLORSETS,
  TEXTURELIST,
  COLORCATEGORIES,
} from "./DiceColors.js";

const normalizeDiceBoxDimensions = (dimensions) => {
  if (!dimensions) return null;
  const width = Number(
    dimensions.w !== undefined ? dimensions.w : dimensions.width,
  );
  const height = Number(
    dimensions.h !== undefined ? dimensions.h : dimensions.height,
  );
  if (!Number.isFinite(width) || !Number.isFinite(height)) return null;
  return { w: width, h: height };
};

export class DiceRoller {
  constructor(options = {}) {
    this.assetBaseUrl = options.assetBaseUrl || "/dice_roller";
    this.themeId = options.themeId || "default";
    const fallbackScale =
      typeof options.diceScale === "number" && Number.isFinite(options.diceScale)
        ? options.diceScale
        : 1;
    this.diceScaleThrow =
      typeof options.diceScaleThrow === "number" &&
      Number.isFinite(options.diceScaleThrow)
        ? options.diceScaleThrow
        : fallbackScale;
    this.diceScaleSelector =
      typeof options.diceScaleSelector === "number" &&
      Number.isFinite(options.diceScaleSelector)
        ? options.diceScaleSelector
        : fallbackScale;
    this.chatEnabled =
      typeof options.chatEnabled === "boolean" ? options.chatEnabled : true;
    this.rngSeed =
      options.rngSeed !== undefined && options.rngSeed !== null
        ? options.rngSeed
        : null;
    this.dragThrowEnabled =
      typeof options.dragThrowEnabled === "boolean"
        ? options.dragThrowEnabled
        : true;
    this.canvasHeightOffset =
      typeof options.canvasHeightOffset === "number" &&
      Number.isFinite(options.canvasHeightOffset)
        ? options.canvasHeightOffset
        : 0;
    this.diceScale = this.diceScaleThrow;
    this.diceDisplayEnabled =
      typeof options.diceDisplayEnabled === "boolean"
        ? options.diceDisplayEnabled
        : true;
    this.diceBoxDimensions = normalizeDiceBoxDimensions(
      options.diceBoxDimensions,
    );
    this.diceSelectorDimensions = normalizeDiceBoxDimensions(
      options.diceSelectorDimensions,
    );
    this.diceDisplayList =
      Array.isArray(options.diceDisplayList) && options.diceDisplayList.length
        ? options.diceDisplayList
        : null;
    this._diceRoomStarted = false;
    this._boundOnResize = this.on_window_resize.bind(this);
    this._boundBeforeUnload = this.close_socket.bind(this);
  }

  initialize(imagesList, options = {}) {
    this.assetBaseUrl =
      options.assetBaseUrl || this.assetBaseUrl || "/dice_roller";
    this.themeId = options.themeId || this.themeId || "default";
    if (typeof options.chatEnabled === "boolean") {
      this.chatEnabled = options.chatEnabled;
    }
    if (options.rngSeed !== undefined && options.rngSeed !== null) {
      this.rngSeed = options.rngSeed;
    }
    if (typeof options.dragThrowEnabled === "boolean") {
      this.dragThrowEnabled = options.dragThrowEnabled;
    }
    if (
      typeof options.canvasHeightOffset === "number" &&
      Number.isFinite(options.canvasHeightOffset)
    ) {
      this.canvasHeightOffset = options.canvasHeightOffset;
    }
    const hasScaleThrow =
      typeof options.diceScaleThrow === "number" &&
      Number.isFinite(options.diceScaleThrow);
    const hasScaleSelector =
      typeof options.diceScaleSelector === "number" &&
      Number.isFinite(options.diceScaleSelector);
    if (hasScaleThrow) {
      this.diceScaleThrow = options.diceScaleThrow;
    }
    if (hasScaleSelector) {
      this.diceScaleSelector = options.diceScaleSelector;
    }
    if (
      (!hasScaleThrow || !hasScaleSelector) &&
      typeof options.diceScale === "number" &&
      Number.isFinite(options.diceScale)
    ) {
      if (!hasScaleThrow) this.diceScaleThrow = options.diceScale;
      if (!hasScaleSelector) this.diceScaleSelector = options.diceScale;
    }
    this.diceScale = this.diceScaleThrow;
    if (typeof options.diceDisplayEnabled === "boolean") {
      this.diceDisplayEnabled = options.diceDisplayEnabled;
    }
    if (options.diceBoxDimensions) {
      this.diceBoxDimensions = normalizeDiceBoxDimensions(
        options.diceBoxDimensions,
      );
    }
    if (options.diceSelectorDimensions) {
      this.diceSelectorDimensions = normalizeDiceBoxDimensions(
        options.diceSelectorDimensions,
      );
    }
    if (
      Array.isArray(options.diceDisplayList) &&
      options.diceDisplayList.length
    ) {
      this.diceDisplayList = options.diceDisplayList;
    }
    this.Teal = new Teal();

    // Inicjalizacja obiektu DiceRoller na window
    window.DiceRoller = this;
    // Logowanie procesu inicjalizacji DiceFavorites
    // console.log('Inicjalizacja DiceFavorites...');
    this.DiceFavorites = new DiceFavorites();
    if (!this.DiceFavorites) {
      // console.error("DiceFavorites initialization failed!");
    } else {
      // console.log("DiceFavorites initialized correctly.");
      this.DiceFavorites.favtemplate = $(".fav_draggable");
      window.DiceFavorites = this.DiceFavorites;
    }
    if (this.DiceFavorites && this.themeId) {
      this.DiceFavorites.settings.theme.value = this.themeId;
      this.DiceFavorites.storeSettings();
    }

    this.DiceFactory = new DiceFactory({
      assetBaseUrl: this.assetBaseUrl,
      diceScaleThrow: this.diceScaleThrow,
      diceScale: this.diceScaleThrow,
    });
    this.DiceFactory.setBumpMapping(
      this.DiceFavorites.settings.bumpmaps.value == "1",
    );
    window.DiceFactory = this.DiceFactory;

    this.DiceColors = new DiceColors(this);
    this.DiceColors.textures = imagesList || TEXTURELIST;
    this.textureList = TEXTURELIST;
    this.colorSets = COLORSETS;
    this.colorCategories = COLORCATEGORIES;
    this.DiceColors.initColorSets();
    window.DiceColors = this.DiceColors;

    this.DiceRoom = null;

    this.setupInitialSettings();
    this.populateThemeOptions();
    this.populateSystemOptions();
    this.applySettings();
    this.setupConnectionSettings();
    this.initializeFormElements();
    this.initializeEvents();

    this.on_theme_select_change(
      null,
      this.DiceFavorites.settings.fgcolor.value,
      this.DiceFavorites.settings.bgcolor.value,
    );
  }

  initializeDiceFavorites() {
    try {
      this.DiceFavorites = new DiceFavorites();
      this.DiceFavorites.favtemplate = $(".fav_draggable");
      window.DiceFavorites = this.DiceFavorites;

      if (!this.DiceFavorites) {
        // throw new Error("DiceFavorites initialization failed.");
      }
    } catch (error) {
      // console.error("Failed to initialize DiceFavorites:", error);
      this.DiceFavorites = null;
    }
  }

  setupInitialSettings() {
    this.control_panel_show = Teal.id("cp_showsettings");
    this.control_panel_hide = Teal.id("cp_hidesettings");
    this.selector_div = Teal.id("selector_div");
    this.theme_select = Teal.id("theme");
    this.surface_select = Teal.id("surface");
    this.system_select = Teal.id("system");
    this.color_select = Teal.id("color");
    this.texture_select = Teal.id("texture");
    this.material_select = Teal.id("material");
    this.socket_button = Teal.id("reconnect");
    this.desk = Teal.id("desk");
    this.toggle_selector = Teal.id("toggle_selector");
    this.parent_notation = Teal.id("parent_notation");
    this.parent_roll = Teal.id("parent_roll");
    this.set = Teal.id("set");
    this.diceset = [];
  }
  initializeEvents() {
    // Obsługa zmiany rozmiaru okna i zamykania strony
    window.addEventListener("resize", this._boundOnResize);
    window.addEventListener("beforeunload", this._boundBeforeUnload);

    // Sprawdzamy, czy poszczególne elementy są zainicjalizowane
    if (this.socket_button) {
      Teal.bind(
        this.socket_button,
        "click",
        this.socket_button_press.bind(this),
      );
      Teal.bind(
        this.socket_button,
        ["mousedown", "mouseup", "touchstart", "touchend"],
        (ev) => ev.stopPropagation(),
      );
      Teal.bind(this.socket_button, "focus", () =>
        Teal.set(this.desk, { class: "" }),
      );
      Teal.bind(this.socket_button, "blur", () =>
        Teal.set(this.desk, { class: "noselect" }),
      );
    } else {
      console.error("socket_button is not initialized!");
    }

    if (this.theme_select) {
      Teal.bind(
        this.theme_select,
        "change",
        this.on_theme_select_change.bind(this),
      );
      Teal.bind(
        this.theme_select,
        ["mousedown", "mouseup", "touchstart", "touchend"],
        (ev) => ev.stopPropagation(),
      );
      Teal.bind(this.theme_select, "focus", () =>
        Teal.set(this.desk, { class: "" }),
      );
      Teal.bind(this.theme_select, "blur", () =>
        Teal.set(this.desk, { class: "noselect" }),
      );
    } else {
      console.error("theme_select is not initialized!");
    }

    if (this.surface_select) {
      Teal.bind(
        this.surface_select,
        "change",
        this.on_surface_select_change.bind(this),
      );
      Teal.bind(
        this.surface_select,
        ["mousedown", "mouseup", "touchstart", "touchend"],
        (ev) => ev.stopPropagation(),
      );
      Teal.bind(this.surface_select, "focus", () =>
        Teal.set(this.desk, { class: "" }),
      );
      Teal.bind(this.surface_select, "blur", () =>
        Teal.set(this.desk, { class: "noselect" }),
      );
    } else {
      console.error("surface_select is not initialized!");
    }

    if (this.system_select) {
      Teal.bind(
        this.system_select,
        "change",
        this.on_system_select_change.bind(this),
      );
      Teal.bind(
        this.system_select,
        ["mousedown", "mouseup", "touchstart", "touchend"],
        (ev) => ev.stopPropagation(),
      );
      Teal.bind(this.system_select, "focus", () =>
        Teal.set(this.desk, { class: "" }),
      );
      Teal.bind(this.system_select, "blur", () =>
        Teal.set(this.desk, { class: "noselect" }),
      );
    } else {
      console.error("system_select is not initialized!");
    }

    if (this.color_select) {
      Teal.bind(
        this.color_select,
        "change",
        this.on_color_select_change.bind(this),
      );
      Teal.bind(
        this.color_select,
        ["mousedown", "mouseup", "touchstart", "touchend"],
        (ev) => ev.stopPropagation(),
      );
      Teal.bind(this.color_select, "focus", () =>
        Teal.set(this.desk, { class: "" }),
      );
      Teal.bind(this.color_select, "blur", () =>
        Teal.set(this.desk, { class: "noselect" }),
      );
    } else {
      console.error("color_select is not initialized!");
    }

    if (this.texture_select) {
      Teal.bind(
        this.texture_select,
        "change",
        this.on_texture_select_change.bind(this),
      );
      Teal.bind(
        this.texture_select,
        ["mousedown", "mouseup", "touchstart", "touchend"],
        (ev) => ev.stopPropagation(),
      );
      Teal.bind(this.texture_select, "focus", () =>
        Teal.set(this.desk, { class: "" }),
      );
      Teal.bind(this.texture_select, "blur", () =>
        Teal.set(this.desk, { class: "noselect" }),
      );
    } else {
      console.error("texture_select is not initialized!");
    }

    if (this.material_select) {
      Teal.bind(
        this.material_select,
        "change",
        this.on_material_select_change.bind(this),
      );
      Teal.bind(this.material_select, "focus", () =>
        Teal.set(this.desk, { class: "" }),
      );
      Teal.bind(this.material_select, "blur", () =>
        Teal.set(this.desk, { class: "noselect" }),
      );
    } else {
      console.error("material_select is not initialized!");
    }

    if (this.control_panel_show && this.control_panel_hide) {
      Teal.bind(
        this.control_panel_show,
        "click",
        this.on_control_panel_show.bind(this),
      );
      Teal.bind(
        this.control_panel_hide,
        "click",
        this.on_control_panel_show.bind(this),
      );
      Teal.bind(
        this.control_panel_show,
        ["mousedown", "mouseup", "touchstart", "touchend"],
        (ev) => ev.stopPropagation(),
      );
      Teal.bind(
        this.control_panel_hide,
        ["mousedown", "mouseup", "touchstart", "touchend"],
        (ev) => ev.stopPropagation(),
      );
    } else {
      console.error(
        "control_panel_show or control_panel_hide is not initialized!",
      );
    }

    if (this.toggle_selector) {
      Teal.bind(
        this.toggle_selector,
        "click",
        this.on_toggle_selector.bind(this),
      );
      Teal.bind(
        this.toggle_selector,
        ["mousedown", "mouseup", "touchstart", "touchend"],
        (ev) => ev.stopPropagation(),
      );
      Teal.bind(this.toggle_selector, "focus", () =>
        Teal.set(this.desk, { class: "" }),
      );
      Teal.bind(this.toggle_selector, "blur", () =>
        Teal.set(this.desk, { class: "noselect" }),
      );
    } else {
      console.error("toggle_selector is not initialized!");
    }

    if (this.parent_roll) {
      Teal.bind(
        this.parent_roll,
        "change",
        this.on_parent_roll_change.bind(this),
      );
    } else {
      console.error("parent_roll is not initialized!");
    }

    // if (Teal.id('input_user')) {
    // 	Teal.bind(Teal.id('input_user'), 'keyup', this.submit_login.bind(this));
    // }

    // if (Teal.id('input_room')) {
    // 	Teal.bind(Teal.id('input_room'), 'keyup', this.submit_login.bind(this));
    // }

    // if (Teal.id('input_pass')) {
    // 	Teal.bind(Teal.id('input_pass'), 'keyup', this.submit_login.bind(this));
    // }

    // if (Teal.id('button_join')) {
    // 	Teal.bind(Teal.id('button_join'), 'click', this.button_join_press.bind(this));
    // }

    if (Teal.id("button_single")) {
      Teal.bind(Teal.id("button_single"), "click", () => {
        if (!this.DiceFavorites) {
          console.error(
            "DiceFavorites is not initialized, delaying DiceRoom initialization.",
          );
          // Spróbujemy ponownie po 500ms, kiedy DiceFavorites się zainicjalizuje.
          setTimeout(() => {
            if (this.DiceFavorites) {
              console.log("DiceFavorites initialized, creating DiceRoom.");
              this.button_single_press();
            } else {
              console.error("DiceFavorites still not available.");
            }
          }, 500);
        } else {
          this.button_single_press();
        }
      });
      $("#button_single").trigger("click");
    } else {
      console.error("button_single is not initialized!");
    }

    if (Teal.id("turnOnRoom")) {
      Teal.bind(
        Teal.id("turnOnRoom"),
        "click",
        this.button_single_press.bind(this),
      );
    }
  }

  destroy() {
    window.removeEventListener("resize", this._boundOnResize);
    window.removeEventListener("beforeunload", this._boundBeforeUnload);
    if (this.Teal?.socket && this.Teal.socket.readyState <= WebSocket.OPEN) {
      this.Teal.socket.close();
    }
    if (window.DiceRoller === this) {
      window.DiceRoller = null;
    }
  }

  populateThemeOptions() {
    if (this.theme_select) {
      Teal.empty(this.theme_select);
    }
    const themeprops = Object.entries(THEMES);
    themeprops.forEach(([key, value]) => {
      let attributes = { value: key };
      if (key == this.DiceFavorites.settings.theme.value)
        attributes["selected"] = "selected";
      Teal.element("option", attributes, Teal.id("theme"), value.name);
    });
  }

  populateSystemOptions() {
    if (this.system_select) {
      Teal.empty(this.system_select);
    }
    const systemprops = Object.entries(this.DiceFactory.systems);
    systemprops.forEach(([key, value]) => {
      let attributes = { value: key };
      if (key == this.DiceFavorites.settings.system.value)
        attributes["selected"] = "selected";
      Teal.element("option", attributes, Teal.id("system"), value.name);
    });
  }
  applySettings() {
    this.params = Teal.get_url_params();
    this.params.colorset =
      this.DiceFavorites.settings.colorset.value || this.params.colorset;
    this.params.texture =
      this.DiceFavorites.settings.texture.value || this.params.texture;
    this.params.material =
      this.DiceFavorites.settings.material.value || this.params.material;

    if (this.params.colorset || this.params.texture || this.params.material) {
      this.DiceColors.applyColorSet(
        this.params.colorset || "random",
        this.params.texture || "",
        this.params.material || "",
      );
    } else {
      this.DiceColors.applyColorSet("random");
    }
  }
  setupConnectionSettings() {
    if (this.params.server) {
      this.Teal.socketAddress = this.params.server;
      this.Teal.socketSecure = this.params.secure;
    } else {
      this.Teal.socketAddress = "dnd.majorsplace.com:32400";
      this.Teal.socketSecure = false;
    }

    this.set_connection_message("Ready", "green");
    Teal.hidden(this.desk, true);
    this.show_waitform(false);
  }
  initializeFormElements() {
    // Inicjalizacja elementów formularza i obsługa zdarzeń z jQuery
    $("#checkbox_allowdiceoverride").prop(
      "checked",
      this.DiceFavorites.settings.allowDiceOverride.value == "1",
    );
    $("#checkbox_allowdiceoverride").change(() => {
      this.DiceFavorites.settings.allowDiceOverride.value = $(
        "#checkbox_allowdiceoverride",
      ).prop("checked")
        ? "1"
        : "0";
      this.DiceFavorites.storeSettings();
      if (this.DiceRoom) this.DiceRoom.show_selector();
    });

    $("#checkbox_bumpmap").prop(
      "checked",
      this.DiceFavorites.settings.bumpmaps.value == "1",
    );
    $("#checkbox_bumpmap").change(() => {
      this.DiceFavorites.settings.bumpmaps.value = $("#checkbox_bumpmap").prop(
        "checked",
      )
        ? "1"
        : "0";
      this.DiceFavorites.storeSettings();
      if (this.DiceRoom) {
        // this.DiceFactory.setBumpMapping(this.DiceFavorites.settings.bumpmaps.value == '1');
        this.DiceRoom.show_selector();
      }
    });

    $("#checkbox_shadows").prop(
      "checked",
      this.DiceFavorites.settings.shadows.value == "1",
    );
    $("#checkbox_shadows").change(() => {
      this.DiceFavorites.settings.shadows.value = $("#checkbox_shadows").prop(
        "checked",
      )
        ? "1"
        : "0";
      this.DiceFavorites.storeSettings();
      if (this.DiceRoom) {
        if (this.DiceFavorites.settings.shadows.value == "1") {
          this.DiceRoom.DiceBox.enableShadows();
        } else {
          this.DiceRoom.DiceBox.disableShadows();
        }
        this.DiceRoom.show_selector();
      }
    });

    $("#checkbox_sounds").prop(
      "checked",
      this.DiceFavorites.settings.sounds.value == "1",
    );
    $("#checkbox_sounds").change(() => {
      this.DiceFavorites.settings.sounds.value = $("#checkbox_sounds").prop(
        "checked",
      )
        ? "1"
        : "0";
      this.DiceFavorites.storeSettings();
      if (this.DiceRoom)
        this.DiceRoom.DiceBox.sounds =
          this.DiceFavorites.settings.sounds.value == "1";
    });

    $("#checkbox_tally").prop(
      "checked",
      this.DiceFavorites.settings.tally.value == "1",
    );
    $("#checkbox_tally").change(() => {
      this.DiceFavorites.settings.tally.value = $("#checkbox_tally").prop(
        "checked",
      )
        ? "1"
        : "0";
      this.DiceFavorites.storeSettings();
      if (this.DiceRoom)
        this.DiceRoom.DiceBox.tally =
          this.DiceFavorites.settings.tally.value == "1";
    });

    $("#checkbox_users").prop(
      "checked",
      this.DiceFavorites.settings.users.value == "1",
    );
    $("#checkbox_users").change(() => {
      this.DiceFavorites.settings.users.value = $("#checkbox_users").prop(
        "checked",
      )
        ? "1"
        : "0";
      this.DiceFavorites.storeSettings();
      Teal.id("label_players").style.display =
        this.DiceFavorites.settings.users.value == "1"
          ? "inline-block"
          : "none";
    });

    let volume_handle = $("#volume_handle");
    $("#volume_slider").slider({
      range: "min",
      min: 0,
      max: 100,
      value: this.DiceFavorites.settings.volume.value,
      create: function () {
        let DiceRoller = window.DiceRoller;
        const currentValue = $(this).slider("value");
        volume_handle.text(currentValue);
        if (DiceRoller.DiceRoom)
          DiceRoller.DiceRoom.DiceBox.volume = parseInt(currentValue);
      },
      slide: function (event, ui) {
        let DiceRoller = window.DiceRoller;
        void event;
        volume_handle.text(ui.value);
        DiceRoller.DiceFavorites.settings.volume.value = ui.value;
        if (DiceRoller.DiceRoom)
          DiceRoller.DiceRoom.DiceBox.volume = parseInt(ui.value);
      },
      stop: function (event, ui) {
        let DiceRoller = window.DiceRoller;
        void event;
        DiceRoller.DiceFavorites.storeSettings();
        if (DiceRoller.DiceRoom)
          DiceRoller.DiceRoom.DiceBox.volume = parseInt(ui.value);
      },
    });

    $(".control_bgcolor").spectrum({
      showPalette: true,
      palette: [
        ["#ff0000", "#00ff00", "#0000ff"],
        ["#000000", "#ffffff"],
        ["#9794ff", "#0b1a3e"],
      ],
      color: this.DiceFavorites.settings.bgcolor.value,
      showInput: "true",
      showAlpha: "false",
      replacerClassName: "control_bgcolor",
      change: function (color) {
        let DiceRoller = window.DiceRoller;
        $(document.body).css("background-color", color.toHexString());
        DiceRoller.DiceFavorites.settings.bgcolor.value = color.toHexString();
        DiceRoller.DiceFavorites.storeSettings();
      },
    });

    $(".control_fgcolor").spectrum({
      showPalette: true,
      palette: [
        ["#ff0000", "#00ff00", "#0000ff"],
        ["#000000", "#ffffff"],
        ["#9794ff", "#0b1a3e"],
      ],
      color: this.DiceFavorites.settings.fgcolor.value,
      showInput: "true",
      showAlpha: "false",
      replacerClassName: "control_fgcolor",
      change: function (color) {
        let DiceRoller = window.DiceRoller;
        $(document.body).css("color", color.toHexString());
        DiceRoller.DiceFavorites.settings.fgcolor.value = color.toHexString();
        DiceRoller.DiceFavorites.storeSettings();
      },
    });

    let pageThemeInfo = THEMES[this.DiceFavorites.settings.theme.value];
    if (pageThemeInfo) {
      if (pageThemeInfo.showColorPicker) {
        $(".sp-replacer").show();
        $(document.body).css(
          "background-color",
          this.DiceFavorites.settings.bgcolor.value,
        );
        $(document.body).css(
          "color",
          this.DiceFavorites.settings.fgcolor.value,
        );
      } else {
        $(".sp-replacer").hide();
      }
    }

    $("#control_panel")
      .accordion({
        header: "fieldset > legend",
        heightStyle: "content",
        collapsible: true,
        active: false,
      })
      .draggable({
        scroll: false,
        snap: ".fav_draggable, #selector_div, #log, #control_panel",
        stack: ".fav_draggable, #control_panel",
        containment: "window",
        snapTolerance: 10,
        stop: function () {
          let pos = $(this).offset();
          if (!pos) return;

          if (pos.left + $(this).width() > window.innerWidth) {
            pos.left = window.innerWidth - $(this).width();
          }

          if (pos.top + $(this).height() > window.innerHeight) {
            pos.top = window.innerHeight - $(this).height();
          }

          $(this).offset(pos);
        },
      })
      .tabs();
  }
  on_window_resize() {
    let DiceRoller = window.DiceRoller;

    if (!DiceRoller) {
      console.warn("DiceRoller is not defined.");
      return;
    }

    const deskRoot =
      DiceRoller.desk?.closest(".dice-roller-root") ||
      DiceRoller.desk?.parentElement;
    const rootRect = deskRoot?.getBoundingClientRect();
    const chatEnabled = DiceRoller.chatEnabled !== false;
    const availableWidth = chatEnabled
      ? rootRect?.width || window.innerWidth
      : window.innerWidth;
    const availableHeight = chatEnabled
      ? Math.max(0, window.innerHeight - (rootRect?.top || 0))
      : window.innerHeight;
    let w = availableWidth + "px";
    const chatVisible =
      chatEnabled &&
      DiceRoller.DiceRoom &&
      DiceRoller.DiceRoom.TealChat &&
      DiceRoller.DiceRoom.TealChat.place &&
      DiceRoller.DiceRoom.TealChat.place.style.display !== "none";
    let hh = chatVisible ? Math.floor(availableHeight * 0.24) : 0;
    let hValue = Math.max(0, availableHeight - hh);
    let h = hValue + "px";
    const offset =
      typeof DiceRoller.canvasHeightOffset === "number" &&
      Number.isFinite(DiceRoller.canvasHeightOffset)
        ? DiceRoller.canvasHeightOffset
        : 0;
    const canvasHeight = Math.max(0, hValue - offset) + "px";

    if (deskRoot) {
      deskRoot.style.position = "relative";
      deskRoot.style.height = availableHeight + "px";
      deskRoot.style.width = w;
    }

    // Sprawdzenie, czy DiceRoller.desk jest zdefiniowane
    if (DiceRoller.desk) {
      DiceRoller.desk.style.width = w;
      DiceRoller.desk.style.height = h;
    } else {
      console.warn("DiceRoller.desk is undefined.");
    }

    // Sprawdzenie, czy DiceRoller.DiceRoom jest zdefiniowane
    if (DiceRoller.DiceRoom) {
      // Sprawdzenie, czy DiceRoller.DiceRoom.DiceBox jest zdefiniowane
      if (DiceRoller.DiceRoom.DiceBox) {
        DiceRoller.DiceRoom.DiceBox.setDimensions(
          DiceRoller.diceBoxDimensions,
        );
      } else {
        console.warn("DiceRoller.DiceRoom.DiceBox is undefined.");
      }

      // Sprawdzenie, czy DiceRoller.DiceRoom.TealChat jest zdefiniowane
      if (DiceRoller.DiceRoom.TealChat) {
        const logEl = DiceRoller.DiceRoom.TealChat.place;
        if (logEl) {
          logEl.style.position = chatEnabled ? "absolute" : "fixed";
          logEl.style.left = "0";
          logEl.style.bottom = "0";
          if (!chatEnabled) {
            logEl.style.right = "0";
          } else {
            logEl.style.right = "";
          }
        }
        if (chatVisible) {
          DiceRoller.DiceRoom.TealChat.resize(
            Math.max(0, availableWidth - 30),
            Math.max(0, hh - 10),
          );
        } else {
          DiceRoller.DiceRoom.TealChat.resize(0, 0);
        }
      } else {
        console.warn("DiceRoller.DiceRoom.TealChat is undefined.");
      }

      // Sprawdzenie, czy DiceRoller.DiceRoom.canvas jest zdefiniowane
      if (DiceRoller.DiceRoom.canvas) {
        DiceRoller.DiceRoom.canvas.style.width = w;
        DiceRoller.DiceRoom.canvas.style.height = canvasHeight;
      } else {
        console.warn("DiceRoller.DiceRoom.canvas is undefined.");
      }
    } else {
      console.warn("DiceRoller.DiceRoom is undefined.");
    }

    // Sprawdzenie, czy DiceRoller.DiceFavorites jest zdefiniowane
    if (DiceRoller.DiceFavorites) {
      DiceRoller.DiceFavorites.ensureOnScreen();
    } else {
      console.warn("DiceRoller.DiceFavorites is undefined.");
    }
  }

  socket_button_press(ev) {
    void ev;
    window.DiceRoller.connect_socket(true);
  }

  on_theme_select_change(ev, fgcolor, bgcolor) {
    void ev;
    let DiceRoller = window.DiceRoller;
    if (!DiceRoller.theme_select) {
      console.error("Theme select element not found!");
      return;
    }
    let themeinfo = THEMES[DiceRoller.theme_select.value];
    if (!themeinfo) Teal.selectByValue(DiceRoller.theme_select, "default");
    Teal.selectByValue(DiceRoller.surface_select, themeinfo.surface);
    DiceRoller.DiceFavorites.settings.theme.value =
      DiceRoller.theme_select.value;
    DiceRoller.DiceFavorites.settings.surface.value =
      DiceRoller.surface_select.value;

    const themeStyleId = "dice-roller-theme-style";
    const existingThemeLink = document.getElementById(themeStyleId);
    if (existingThemeLink) {
      existingThemeLink.remove();
    }

    let themeid = DiceRoller.DiceFavorites.settings.theme.value;
    if (themeid !== "default") {
      let headelement = document.getElementsByTagName("head")[0];
      const themeBase = (DiceRoller.assetBaseUrl || "/dice_roller").replace(
        /\/+$/,
        "",
      );
      Teal.element(
        "link",
        {
          id: themeStyleId,
          rel: "stylesheet",
          type: "text/css",
          href: `${themeBase}/themes/${themeid}/style.css`,
        },
        headelement,
      );
    }

    let pageThemeInfo = THEMES[DiceRoller.DiceFavorites.settings.theme.value];

    DiceRoller.DiceFavorites.settings.fgcolor.value =
      fgcolor || pageThemeInfo.colors.fg;
    DiceRoller.DiceFavorites.settings.bgcolor.value =
      bgcolor || pageThemeInfo.colors.bg;
    DiceRoller.DiceFavorites.storeSettings();

    if (pageThemeInfo) {
      if (pageThemeInfo.showColorPicker) {
        $(".sp-replacer, #fgbglabel").show();
        $(document.body).css("color", fgcolor || pageThemeInfo.colors.fg);
        $(document.body).css(
          "background-color",
          bgcolor || pageThemeInfo.colors.bg,
        );
        $(".control_fgcolor").spectrum(
          "set",
          fgcolor || pageThemeInfo.colors.fg,
        );
        $(".control_bgcolor").spectrum(
          "set",
          bgcolor || pageThemeInfo.colors.bg,
        );
      } else {
        $(".sp-replacer, #fgbglabel").hide();
      }
    }

    if (pageThemeInfo.cubeMap) {
      const themeBase = (DiceRoller.assetBaseUrl || "/dice_roller").replace(
        /\/+$/,
        "",
      );
      window.DiceFactory.setCubeMap(
        `${themeBase}/themes/${themeid}/`,
        pageThemeInfo.cubeMap,
      );
    } else {
      window.DiceFactory.setCubeMap(false);
    }
  }

  on_surface_select_change(ev) {
    void ev;
    let DiceRoller = window.DiceRoller;
    DiceRoller.DiceFavorites.settings.surface.value =
      DiceRoller.surface_select.value;
    DiceRoller.DiceFavorites.storeSettings();
  }

  on_system_select_change(ev) {
    void ev;
    let DiceRoller = window.DiceRoller;
    DiceRoller.DiceFavorites.settings.system.value =
      DiceRoller.system_select.value;
    DiceRoller.DiceFavorites.storeSettings();

    if (DiceRoller.DiceRoom) DiceRoller.DiceRoom.show_selector();
  }

  on_color_select_change(ev) {
    void ev;
    let DiceRoller = window.DiceRoller;
    Teal.selectByValue(DiceRoller.texture_select, "");
    Teal.selectByValue(DiceRoller.material_select, "");

    DiceRoller.DiceColors.applyColorSet(DiceRoller.color_select.value);

    DiceRoller.Teal.rpc({
      method: "colorset",
      colorset: DiceRoller.color_select.value,
    });
    DiceRoller.Teal.rpc({
      method: "texture",
      texture: DiceRoller.texture_select.value,
    });
    DiceRoller.Teal.rpc({
      method: "material",
      material: DiceRoller.material_select.value,
    });

    DiceRoller.DiceFavorites.settings.colorset.value =
      DiceRoller.color_select.value;
    DiceRoller.DiceFavorites.settings.texture.value =
      DiceRoller.texture_select.value;
    DiceRoller.DiceFavorites.settings.material.value =
      DiceRoller.material_select.value;
    DiceRoller.DiceFavorites.storeSettings();

    if (DiceRoller.DiceRoom) DiceRoller.DiceRoom.show_selector();
  }

  on_texture_select_change(ev) {
    void ev;
    let DiceRoller = window.DiceRoller;
    DiceRoller.DiceColors.applyColorSet(
      DiceRoller.color_select.value,
      DiceRoller.texture_select.value,
    );
    DiceRoller.Teal.rpc({
      method: "texture",
      texture: DiceRoller.texture_select.value,
    });

    DiceRoller.DiceFavorites.settings.texture.value =
      DiceRoller.texture_select.value;
    DiceRoller.DiceFavorites.storeSettings();

    if (DiceRoller.DiceRoom) DiceRoller.DiceRoom.show_selector();
  }

  on_material_select_change(ev) {
    void ev;
    let DiceRoller = window.DiceRoller;
    DiceRoller.DiceColors.applyColorSet(
      DiceRoller.color_select.value,
      DiceRoller.texture_select.value,
      DiceRoller.material_select.value,
    );
    DiceRoller.Teal.rpc({
      method: "material",
      material: DiceRoller.material_select.value,
    });

    DiceRoller.DiceFavorites.settings.material.value =
      DiceRoller.material_select.value;
    DiceRoller.DiceFavorites.storeSettings();

    if (DiceRoller.DiceRoom) DiceRoller.DiceRoom.show_selector();
  }

  on_control_panel_show(ev) {
    if (ev) ev.stopPropagation();

    if ($("#control_panel").css("display") == "none") {
      $("#control_panel").show();
    } else {
      $("#control_panel").hide();
    }
    if (ev) ev.preventDefault();
  }

  on_toggle_selector(ev) {
    let DiceRoller = window.DiceRoller;
    if (ev) ev.stopPropagation();
    if (DiceRoller.selector_div)
      Teal.hidden(
        DiceRoller.selector_div,
        DiceRoller.selector_div.style.display != "none",
      );
    if (ev) ev.preventDefault();
  }

  submit_login(ev) {
    if (ev && ev.keyCode && ev.keyCode == 13) {
      Teal.raise_event(Teal.id("button_join"), "click");
    }
  }

  on_parent_roll_change() {
    let DiceRoller = window.DiceRoller;
    if (DiceRoller.parent_roll.value == "1") {
      DiceRoller.set.value = DiceRoller.parent_notation.value;
      Teal.raise_event(Teal.id("throw"), "mouseup");
    }
  }

  button_join_press(ev) {
    void ev;
    let DiceRoller = window.DiceRoller;
    DiceRoller.show_waitform(true);
    window.DiceRoller.connect_socket(false, DiceRoller.on_socket_connect);
  }

  on_socket_connect(socketevent) {
    void socketevent;
    let user = Teal.id("input_user").value;
    let room = Teal.id("input_room").value;
    let pass = Teal.id("input_pass").value;

    window.DiceRoller.Teal.rpc({
      method: "join",
      user: user,
      room: room,
      pass: pass,
    });
  }

  async button_single_press(ev) {
    void ev;
    if (this._diceRoomStarted) {
      return;
    }
    this._diceRoomStarted = true;
    // Dodajemy oczekiwanie na pełną inicjalizację
    if (!this.DiceFavorites) {
      console.error("DiceFavorites is not initialized, waiting for it...");
      await new Promise((resolve) => {
        const interval = setInterval(() => {
          if (this.DiceFavorites) {
            console.log("DiceFavorites initialized.");
            clearInterval(interval);
            resolve();
          }
        }, 100); // Sprawdzamy co 100ms
      });
    }

    if (this.Teal.socket && this.Teal.socket.readyState <= WebSocket.OPEN) {
      this.Teal.socket.close();
    }

    this.Teal.offline = true;

    try {
      // Upewniamy się, że DiceFavorites jest w pełni gotowe
      this.DiceRoom = new DiceRoom("Yourself", -1, this.DiceFavorites, {
        assetBaseUrl: this.assetBaseUrl,
        chatEnabled: this.chatEnabled,
        rngSeed: this.rngSeed,
        dragThrowEnabled: this.dragThrowEnabled,
        diceScaleThrow: this.diceScaleThrow,
        diceScaleSelector: this.diceScaleSelector,
        diceDisplayEnabled: this.diceDisplayEnabled,
        diceBoxDimensions: this.diceBoxDimensions,
        diceSelectorDimensions: this.diceSelectorDimensions,
        diceDisplayList: this.diceDisplayList,
      });

      this.show_waitform(false);
      requestAnimationFrame(function () {});
      this.DiceRoom.actions["login"].call(this.DiceRoom, { user: "Yourself" });
    } catch (error) {
      console.error("Error during DiceRoom initialization:", error);
      this._diceRoomStarted = false;
    }
  }

  show_waitform(show) {
    let waitform = Teal.id("waitform");
    if (!waitform) {
      console.error("Waitform element not found!");
      return;
    }
    waitform.style.display = show ? "block" : "none";
    waitform.style.cursor = show ? "default" : "wait";
    waitform.style.visibility = show ? "visible" : "hidden";
  }

  set_connection_message(text, color = "orange") {
    $(".connection_message").each(function () {
      $(this).text(text).css({ color: color });
    });
  }

  connect_socket(reopen, callback) {
    if (
      reopen &&
      this.Teal.socket &&
      this.Teal.socket.readyState <= WebSocket.OPEN
    ) {
      this.Teal.socket.close();
      this.set_connection_message("Reconnecting...");
    } else {
      this.set_connection_message("Connecting...");
    }
    this.Teal.openSocket();

    this.Teal.socket.onerror = function (event) {
      let DiceRoller = window.DiceRoller;
      DiceRoller.set_connection_message("Connection Error", "red", true);
      DiceRoller.show_waitform(false);
      console.log(event);
      DiceRoller.Teal.offline = true;
    };

    this.Teal.socket.onopen = function (event) {
      let DiceRoller = window.DiceRoller;
      DiceRoller.set_connection_message("Connected", "green");
      DiceRoller.show_waitform(false);
      console.log(event);
      DiceRoller.Teal.offline = false;
      callback.call(DiceRoller, event);
    };

    this.Teal.socket.onclose = function (event) {
      let DiceRoller = window.DiceRoller;
      if (event.wasClean) {
        DiceRoller.set_connection_message("Connection Ended");
      } else {
        DiceRoller.set_connection_message("Connection Failed", "red", true);
      }
      console.log(event);
      DiceRoller.show_waitform(false);
      DiceRoller.Teal.offline = true;
    };

    this.Teal.socket.onmessage = function (message) {
      if (message && message.data) {
        var data = JSON.parse(message.data);
        let DiceRoller = window.DiceRoller;
        if (data && data.cid) {
          DiceRoller.cid = data.cid;
          console.log("Client id: " + DiceRoller.cid);
        }

        if (data.error) {
          DiceRoller.set_connection_message(data.error, "red");
          DiceRoller.show_waitform(false);
          DiceRoller.set_login_message(data.error, "red");
        }

        if (data.warning) {
          DiceRoller.set_connection_message(data.warning, "orange");
          DiceRoller.show_waitform(false);
          DiceRoller.set_login_message(data.warning, "orange");
        }

        if (data.message) {
          DiceRoller.set_connection_message(data.message, "green");
          DiceRoller.show_waitform(false);
          DiceRoller.set_login_message(data.message, "green");
        }

        if (data.method == "join" && data.action == "login") {
            DiceRoller.DiceRoom = new DiceRoom(
              data.user,
              DiceRoller.cid,
              DiceRoller.DiceFavorites,
              {
                assetBaseUrl: DiceRoller.assetBaseUrl,
                chatEnabled: DiceRoller.chatEnabled,
                rngSeed: DiceRoller.rngSeed,
                dragThrowEnabled: DiceRoller.dragThrowEnabled,
                diceScaleThrow: DiceRoller.diceScaleThrow,
                diceScaleSelector: DiceRoller.diceScaleSelector,
                diceDisplayEnabled: DiceRoller.diceDisplayEnabled,
                diceBoxDimensions: DiceRoller.diceBoxDimensions,
                diceSelectorDimensions: DiceRoller.diceSelectorDimensions,
                diceDisplayList: DiceRoller.diceDisplayList,
              },
          );

          DiceRoller.show_waitform(false);
          requestAnimationFrame(function () {});
        }

        if (!data.action || data.action.length < 1) return;

        if (
          DiceRoller.DiceRoom &&
          Object.prototype.hasOwnProperty.call(
            DiceRoller.DiceRoom.actions,
            data.action,
          )
        ) {
          DiceRoller.DiceRoom.actions[data.action].call(
            DiceRoller.DiceRoom,
            data,
          );
        }
        DiceRoller.show_waitform(false);
      }
    };
  }

  close_socket() {
    if (this.DiceRoom) {
      this.Teal.rpc({ method: "logout", cid: this.DiceRoom.cid });
    }
    if (this.Teal.socket) {
      this.Teal.socket.close();
    }
  }
}

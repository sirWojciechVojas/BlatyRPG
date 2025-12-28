"use strict";
import $ from "jquery";
import { Teal } from "./Teal.js";
import { DiceBox } from "./DiceBox.js";
import { TealChat } from "./TealChat.js";
import { DiceNotation } from "./DiceNotation.js";
import { DiceFunctions } from "./DiceFunctions.js";

export class DiceRoom {
  constructor(username, userid, diceFavorites, options = {}) {
    if (!window.DiceFavorites) {
      // console.error("DiceFavorites is not initialized in DiceRoom!");
      return;
    }
    this.assetBaseUrl = options.assetBaseUrl || "/dice_roller";
    this.chatEnabled =
      typeof options.chatEnabled === "boolean" ? options.chatEnabled : true;

    this.TealChat = new TealChat(Teal.id("log"));
    this.TealChat.own_user = username;
    this.TealChat.clientid = userid;
    this.cid = userid;
    if (!this.chatEnabled && this.TealChat.place) {
      this.TealChat.place.style.display = "none";
    }

    this.DiceBox = null;

    this.canvas = Teal.id("canvas");
    this.label = Teal.id("label");
    this.set = Teal.id("set");
    this.desk = Teal.id("desk");
    this.info_div = Teal.id("info_div");
    this.selector_div = Teal.id("selector_div");
    this.params = Teal.get_url_params();
    this.users = true;
    const fallbackScale =
      typeof options.diceScale === "number" && Number.isFinite(options.diceScale)
        ? options.diceScale
        : 1;
    this.diceThrowScale =
      typeof options.diceScaleThrow === "number" &&
      Number.isFinite(options.diceScaleThrow)
        ? options.diceScaleThrow
        : fallbackScale;
    this.diceSelectorScale =
      typeof options.diceScaleSelector === "number" &&
      Number.isFinite(options.diceScaleSelector)
        ? options.diceScaleSelector
        : fallbackScale;
    this.diceDisplayEnabled =
      typeof options.diceDisplayEnabled === "boolean"
        ? options.diceDisplayEnabled
        : true;
    this.rngSeed =
      options.rngSeed !== undefined && options.rngSeed !== null
        ? options.rngSeed
        : null;
    this.dragThrowEnabled =
      typeof options.dragThrowEnabled === "boolean"
        ? options.dragThrowEnabled
        : true;
    this.diceDisplayList =
      Array.isArray(options.diceDisplayList) && options.diceDisplayList.length
        ? options.diceDisplayList
        : null;
    this.diceSelectorDimensions = options.diceSelectorDimensions || null;

    // actions performed when the server sends a command
    this.actions = {
      login: this.action_login,
      userlist: this.action_userlist,
      roll: this.action_roll,
      chat: this.action_chat,
      colorset: this.action_colorset,
      texture: this.action_texture,
      material: this.action_material,
      roomlist: this.action_roomlist,
    };

    Teal.hidden(this.desk, false);

    // Sprawdzenie dostępności DiceFavorites
    if (window.DiceRoller && window.DiceFavorites) {
      window.DiceRoller.DiceFavorites.retrieve();
      window.DiceRoller.DiceFavorites.ensureOnScreen();
      // console.log("DiceFavorites is available in DiceRoom context! :)");
    } else {
      // console.error("DiceFavorites not available in DiceRoom context!");
    }

    window.addEventListener("message", this.on_receivePostMessage);

    Teal.bind(
      Teal.id("clear"),
      ["mouseup", "touchend"],
      this.on_button_clear_notation,
    );
    Teal.bind(
      Teal.id("save"),
      ["mouseup", "touchend"],
      this.on_button_create_favorite,
    );

    Teal.bind(this.set, ["mouseup", "keyup", "touchend"], this.on_set_change);
    Teal.bind(this.set, ["mousedown", "touchstart"], function (ev) {
      ev.stopPropagation();
    });
    Teal.bind(this.set, "focus", function () {
      Teal.set(this.desk, { class: "" });
    });
    Teal.bind(this.set, "blur", function () {
      Teal.set(this.desk, { class: "noselect" });
    });

    Teal.bind(
      Teal.id("rage"),
      ["mouseup", "touchend"],
      this.button_increment_rage,
    );
    this.toggle_dice_display = document.getElementById("toggle_dice_display");
    if (this.toggle_dice_display) {
      Teal.bind(
        this.toggle_dice_display,
        ["mouseup", "touchend"],
        this.on_toggle_dice_display.bind(this),
      );
      Teal.bind(
        this.toggle_dice_display,
        ["mousedown", "touchstart"],
        (ev) => ev.stopPropagation(),
      );
      this.updateDiceDisplayToggle();
    }
    this.toggle_drag_throw = document.getElementById("toggle_drag_throw");
    if (this.toggle_drag_throw) {
      Teal.bind(
        this.toggle_drag_throw,
        ["mouseup", "touchend"],
        this.on_toggle_drag_throw.bind(this),
      );
      Teal.bind(
        this.toggle_drag_throw,
        ["mousedown", "touchstart"],
        (ev) => ev.stopPropagation(),
      );
      this.updateDragThrowToggle();
    }

    this.DiceBox = new DiceBox(
      this.canvas,
      options.diceBoxDimensions,
      null,
      null,
      {
        assetBaseUrl: this.assetBaseUrl,
        rngSeed: this.rngSeed,
      },
    );
    this.DiceBox.selector.dice = [
      "df",
      "d4",
      "d6",
      "d8",
      "d10",
      "d100",
      "d12",
      "d20",
    ];
    this.DiceBox.initialize();

    this.DiceBox.volume = parseInt(
      window.DiceRoller.DiceFavorites.settings.volume.value,
    );
    this.DiceBox.sounds =
      window.DiceRoller.DiceFavorites.settings.sounds.value == "1";

    this.DiceFunctions = new DiceFunctions(this.DiceBox);

    Teal.bind(
      this.desk,
      ["mousedown", "touchstart"],
      function (ev) {
        if (!this.canDragThrow()) return;
        this.DiceBox.startDragThrow(ev);
      }.bind(this),
    );

    if (this.params.notation) {
      this.set.value = this.params.notation;
    }
    if (this.params.roll) {
      Teal.raise_event(Teal.id("throw"), "mouseup");
    }

    Teal.bind(
      this.desk,
      ["mouseup", "touchend"],
      function (ev) {
        let notationVectors = null;
        if (this.canDragThrow()) {
          notationVectors = this.DiceBox.endDragThrow(
            ev,
            Teal.id("set").value,
          );
        }

        if (!notationVectors || notationVectors.error) {
          // if total display is up and dice aren't rolling, reset the selector
          if (this.DiceBox.animstate == "afterthrow") {
            if (!this.DiceBox.rolling) this.show_selector();
            return;
          }

          // otherwise, select dice
          let name = this.DiceBox.getDiceAtMouse(ev);
          if (name) {
            let notation = new DiceNotation(this.set.value);

            let shift = ev && ev.shiftKey;
            let ctrl = ev && ev.ctrlKey;
            let leftclick = ev && ev.button == 0;

            let op = "+";
            if (ctrl && leftclick) op = "*";
            if (shift && leftclick) op = "/";
            if (ctrl && shift && leftclick) op = "-";

            notation.addSet(1, name, 0, 0, "", "", op);

            this.set.value = notation.stringify();
            this.on_set_change();
          }
        } else {
          this.sendNetworkedRoll(notationVectors);
        }
      }.bind(this),
    );

    Teal.bind(
      Teal.id("throw"),
      ["mouseup", "touchend"],
      function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        let notationVectors = this.DiceBox.startClickThrow(
          Teal.id("set").value,
        );
        if (!notationVectors || notationVectors.error) return;

        this.sendNetworkedRoll(notationVectors);
      }.bind(this),
    );

    this.TealChat.bind_send(
      function (text) {
        var notation = new DiceNotation(text);
        var uuid = Teal.uuid();
        if (notation.set.length && !notation.error) {
          this.set.value = text;
          let notationVectors = this.DiceBox.startClickThrow(notation);

          if (!notationVectors || notationVectors.error) return;

          this.sendNetworkedRoll(notationVectors);
        } else {
          if (text.length > 1) {
            for (var i = 0, l = text.length; i < l; ++i)
              if (text[i] != "-") break;
            if (i == l) text = Teal.element("hr");
          }
          var time = new Date().getTime();
          this.TealChat.add_unconfirmed_message(
            this.TealChat.own_user,
            text,
            time,
            uuid,
          );
          window.DiceRoller.Teal.rpc({
            method: "chat",
            cid: this.cid,
            text: text,
            time: time,
            uuid: uuid,
          });
        }
      }.bind(this),
    );

    if (!this.diceDisplayEnabled && this.canvas) {
      this.canvas.style.display = "none";
    }
    this.show_selector({ diceList: this.diceDisplayList });
  }
  button_increment_rage(ev) {
    ev.stopPropagation();
    ev.preventDefault();
    let rage = 0;
    // count '!'
    for (let i = 0, l = $("#set").val().length; i < l; i++) {
      rage += $("#set").val().charAt(i) == "!" ? 1 : 0;
    }

    rage += 1;
    if (rage > 3) rage = 0;

    let newval = $("#set").val().replace(/!/g, "");
    $("#set").val(newval + "!".repeat(rage));
    Teal.raise_event(Teal.id("set"), "change");
  }

  make_notation_for_log(notation, result) {
    notation = new DiceNotation(notation); //reinit notation class, if sent from server it will have no methods attached.
    var res = Teal.element("span");
    res.innerHTML +=
      '<span class="chat-notation">' +
      notation.stringify(false) +
      (notation.result.length ? " (preset result)" : "") +
      "</span>";
    res.innerHTML +=
      '<span class="chat-notation-result">' +
      (result ? " → " + result : " ...") +
      "</span>";
    return res;
  }

  close_socket() {
    if (this.cid && window.DiceRoller.Teal.socket) {
      window.DiceRoller.Teal.rpc({ method: "logout", cid: this.cid });
      window.DiceRoller.Teal.socket.close();
    }
  }

  on_receivePostMessage(ev) {
    console.log(ev);
    //if (ev.origin !== "https://www.improved-initiative.com" &&
    //	ev.origin !== "https://files.majorsplace.com" &&
    //	ev.origin !== "https://rand.majorsplace.com" &&
    //	ev.origin !== "https://dnd.majorsplace.com") return;

    $("#set").val(ev.data);
    Teal.raise_event(Teal.id("throw"), "mouseup");
  }

  on_set_change(ev) {
    if (ev) ev.stopPropagation();
    $("#set").css("width", $("#set").val().length + 3 + "ex");

    if (ev && ev.keyCode && ev.keyCode == 13) {
      Teal.raise_event(Teal.id("throw"), "mouseup");
    }
  }

  on_button_clear_notation(ev) {
    let DiceRoller = window.DiceRoller;
    ev.stopPropagation();
    DiceRoller.DiceRoom.set.value = "0";
    DiceRoller.DiceRoom.show_selector();
  }

  on_button_create_favorite(ev) {
    ev.stopPropagation();

    let names = [
      "👊 Melee",
      "🏹 Piercing",
      "🧱 Bludgeoning",
      "🗡️ Slash",
      "🚶 Walk",
      "🧡 Fire",
      "💙 Cold",
      "💛 Lightning",
      "🤎 Thunder",
      "💚 Acid",
      "🤍 Radiant",
      "🖤 Necrotic",
      "💜 Force",
      "🤏 Grapple",
      "🤺 Dodge",
      "🛡️ Parry",
      "🦶 Jump",
      "💥 Explode",
      "💦 Splash",
      "🍂 Fall",
    ];

    let name = prompt(
      "Set Name for Favorite",
      names[Math.floor(Math.random() * names.length)],
    );
    let icons = [
      "❤️",
      "🧡",
      "💛",
      "💚",
      "💙",
      "💜",
      "🖤",
      "🤍",
      "💖",
      "💗",
      "🤎",
    ];
    Teal.id("save").innerHTML = icons[Math.floor(Math.random() * icons.length)];

    let DiceRoller = window.DiceRoller;
    DiceRoller.DiceFavorites.create(
      name,
      DiceRoller.DiceRoom.set.value,
      DiceRoller.color_select.value,
      DiceRoller.texture_select.value,
      ev.pageX,
      ev.pageY,
    );
    DiceRoller.DiceFavorites.store();
  }

  updateDiceDisplayToggle() {
    if (!this.toggle_dice_display) return;
    const enabled = this.diceDisplayEnabled;
    this.toggle_dice_display.setAttribute(
      "aria-pressed",
      enabled ? "true" : "false",
    );
    this.toggle_dice_display.classList.toggle("is-active", enabled);
    const label = enabled ? "Hide Dice" : "Show Dice";
    this.toggle_dice_display.setAttribute("aria-label", label);
    this.toggle_dice_display.setAttribute("title", label);
    const labelSpan = this.toggle_dice_display.querySelector(".toggle-label");
    if (labelSpan) {
      labelSpan.textContent = label;
    }
  }

  canDragThrow() {
    return this.dragThrowEnabled && this.diceDisplayEnabled;
  }

  updateDragThrowToggle() {
    if (!this.toggle_drag_throw) return;
    const enabled = this.dragThrowEnabled;
    this.toggle_drag_throw.setAttribute(
      "aria-pressed",
      enabled ? "true" : "false",
    );
    this.toggle_drag_throw.classList.toggle("is-active", enabled);
    const label = enabled ? "Disable Drag Roll" : "Enable Drag Roll";
    this.toggle_drag_throw.setAttribute("aria-label", label);
    this.toggle_drag_throw.setAttribute("title", label);
    const labelSpan = this.toggle_drag_throw.querySelector(".toggle-label");
    if (labelSpan) {
      labelSpan.textContent = label;
    }
  }

  setDragThrowEnabled(enabled) {
    if (this.dragThrowEnabled === enabled) return;
    this.dragThrowEnabled = enabled;
    this.updateDragThrowToggle();
  }

  setDiceDisplayEnabled(enabled) {
    if (this.diceDisplayEnabled === enabled) return;
    this.diceDisplayEnabled = enabled;
    this.updateDiceDisplayToggle();
    if (this.canvas) {
      this.canvas.style.display = enabled ? "block" : "none";
    }
    if (!enabled) {
      if (this.DiceBox && !this.DiceBox.rolling) this.DiceBox.clearDice();
      return;
    }
    this.show_selector({ diceList: this.diceDisplayList });
  }

  on_toggle_drag_throw(ev) {
    if (ev) {
      ev.stopPropagation();
      ev.preventDefault();
    }
    this.setDragThrowEnabled(!this.dragThrowEnabled);
  }

  on_toggle_dice_display(ev) {
    if (ev) {
      ev.stopPropagation();
      ev.preventDefault();
    }
    this.setDiceDisplayEnabled(!this.diceDisplayEnabled);
  }

  show_selector(options = {}) {
    let systemid = window.DiceFavorites.settings.system.value;
    let alldice = systemid == "all";

    if (!alldice) {
      this.DiceBox.selector.dice = window.DiceFactory.systems[systemid].dice;
    } else {
      this.DiceBox.selector.dice = Object.keys(window.DiceFactory.dice);
    }

    this.info_div.style.display = "none";
    Teal.id("labelhelp").style.display = "none";

    this.selector_div.style.display = "block";
    Teal.id("sethelp").style.display = "block";
    this.deskrolling = false;

    window.DiceColors.applyColorSet(
      window.DiceRoller.color_select.value,
      window.DiceRoller.texture_select.value,
      window.DiceRoller.material_select.value,
    );

    const diceList =
      Array.isArray(options.diceList) && options.diceList.length
        ? options.diceList
        : this.diceDisplayEnabled &&
            Array.isArray(this.diceDisplayList) &&
            this.diceDisplayList.length
          ? this.diceDisplayList
          : null;
    const selectorDimensions =
      options.selectorDimensions || this.diceSelectorDimensions;
    const selectorScale =
      typeof options.selectorScale === "number" &&
      Number.isFinite(options.selectorScale)
        ? options.selectorScale
        : this.diceSelectorScale;
    const throwScale = this.diceThrowScale || 1;
    const selectorScaleFactor =
      typeof selectorScale === "number" && Number.isFinite(selectorScale)
        ? selectorScale / throwScale
        : 1;

    if (!this.diceDisplayEnabled) {
      if (this.DiceBox && !this.DiceBox.rolling) this.DiceBox.clearDice();
      return;
    }

    window.setTimeout(() => {
      window.DiceRoller.on_window_resize();
      this.DiceBox.showSelector(
        !diceList && this.params.alldice && this.params.alldice == "1",
        diceList,
        selectorDimensions,
        selectorScaleFactor,
      );
    }, 100);
  }

  sendNetworkedRoll(notationVectors) {
    this.label.innerHTML = this.TealChat.own_user + " is Rolling...";
    this.info_div.style.display = this.DiceBox.tally ? "block" : "none";
    Teal.id("sethelp").style.display = "none";
    if (this.selector_div) this.selector_div.style.display = "none";
    this.deskrolling = true;
    window.DiceRoller.set_connection_message("");
    window.DiceRoller.show_waitform(true);

    let time = new Date().getTime();
    this.TealChat.add_unconfirmed_message(
      this.TealChat.own_user,
      this.make_notation_for_log(notationVectors.notation),
      time,
      (this.TealChat.roll_uuid = Teal.uuid()),
    );

    if (window.DiceRoller.Teal.offline) {
      this.actions["roll"].call(window.DiceRoller.DiceRoom, {
        method: "roll",
        user: this.TealChat.own_user,
        cid: this.cid,
        time: time,
        notation: notationVectors.notation,
        vectors: notationVectors.vectors,
        colorset: window.DiceRoller.color_select.value,
        texture: window.DiceRoller.texture_select.value,
        material: window.DiceRoller.material_select.value,
      });
    } else {
      try {
        Teal.pack_vectors(notationVectors.vectors);

        window.DiceRoller.Teal.rpc({
          method: "roll",
          user: this.TealChat.own_user,
          cid: this.cid,
          time: time,
          notation: notationVectors.notation,
          vectors: notationVectors.vectors,
        });
      } catch (e) {
        window.DiceRoller.set_connection_message(
          "Failed to send roll!",
          "red",
          true,
        );
        console.log(e);
      }
    }
  }

  set_login_message(text, color = "red") {
    let mbox = Teal.id("login_message");
    if (!mbox) return;
    Teal.empty(mbox);
    Teal.inner(text, mbox);
    mbox.style.color = color;
  }

  action_login(res) {
    var loginform = document.getElementById("loginform");
    if (loginform) {
      Teal.remove(loginform);
      loginform.style.display = "none";
      Teal.id("label_players").style.display =
        window.DiceFavorites.settings.users.value == "1"
          ? "inline-block"
          : "none";
      const chatEnabled =
        window.DiceRoller && window.DiceRoller.chatEnabled !== false;
      this.TealChat.place.style.display = chatEnabled ? "inline-block" : "none";
      this.TealChat.own_user = res.user;

      window.DiceRoller.Teal.rpc({
        method: "colorset",
        colorset: window.DiceRoller.color_select.value,
      });
      window.DiceRoller.Teal.rpc({
        method: "texture",
        texture: window.DiceRoller.texture_select.value,
      });
      window.DiceRoller.Teal.rpc({
        method: "material",
        texture: window.DiceRoller.material_select.value,
      });
      window.DiceRoller.on_window_resize();
    }
    window.DiceRoller.set_connection_message(" ");
  }
  action_userlist(res) {
    Teal.id("label_players").innerHTML = res.room + ": " + res.list.join(", ");
    this.TealChat.add_info(
      res.user +
        " has " +
        { add: "joined", del: "left" }[res.act] +
        " the room",
    );
  }
  action_roll(res) {
    Teal.id("waitform").style.display = "none";
    if (this.TealChat.roll_uuid)
      this.TealChat.confirm_message(this.TealChat.roll_uuid, undefined, true);
    else
      this.TealChat.add_unconfirmed_message(
        res.user,
        this.make_notation_for_log(res.notation),
        res.time,
        (this.TealChat.roll_uuid = Teal.uuid()),
        true,
      );

    this.label.innerHTML = res.user + " is Rolling...";
    this.info_div.style.display = this.DiceBox.tally ? "block" : "none";
    this.deskrolling = true;

    if (
      res.colorset.length > 0 ||
      res.texture.length > 0 ||
      res.material.length > 0
    ) {
      window.DiceColors.applyColorSet(
        res.colorset,
        res.texture,
        res.material,
        false,
      );
    }

    if (!window.DiceRoller.Teal.offline) Teal.unpack_vectors(res.vectors);

    let notationVectors = new DiceNotation(res.notation);
    notationVectors.vectors = res.vectors;

    this.DiceBox.rollDice(notationVectors, (notationVectors) => {
      let resultDice = this.DiceBox.diceList;

      let results = this.DiceBox.getDiceTotals(notationVectors, resultDice);
      results.values =
        results.values == 0 &&
        (notationVectors.notation == "1d10+1d100" ||
          notationVectors.notation == "1d100+1d10")
          ? 100
          : results.values;

      this.label.innerHTML =
        results.rolls +
        "<h2>" +
        results.labels +
        " " +
        results.values +
        "</h2>";

      this.info_div.style.display = this.DiceBox.tally ? "block" : "none";
      Teal.id("labelhelp").style.display = "block";
      if (this.selector_div) this.selector_div.style.display = "block";
      this.deskrolling = false;
      this.DiceBox.rolling = false;
      if (!this.diceDisplayEnabled) {
        this.DiceBox.skipAfterThrow = true;
        this.DiceBox.clearDice();
      }

      if (this.TealChat.roll_uuid) {
        this.TealChat.confirm_message(
          this.TealChat.roll_uuid,
          this.make_notation_for_log(
            res.notation,
            results.rolls + " = " + results.labels + " " + results.values,
          ),
        );
        delete this.TealChat.roll_uuid;
      }

      $(".ui-helper-hidden-accessible").remove();

      $(".diceresult")
        .mouseenter(function () {
          let diceid = $(this).data("uuid");
          for (
            let i = 0, len = window.DiceRoller.DiceRoom.DiceBox.diceList.length;
            i < len;
            ++i
          ) {
            let dicemesh = window.DiceRoller.DiceRoom.DiceBox.diceList[i];

            if (dicemesh.uuid == diceid) {
              window.DiceRoller.DiceRoom.DiceBox.setSelected(dicemesh);
              break;
            }
          }
        })
        .mouseleave(function () {
          window.DiceRoller.DiceRoom.DiceBox.setSelected();
        });

      $(document).tooltip({
        items: ".diceresult",
        track: true,
        content: function () {
          let diceid = $(this).data("uuid");

          if (!diceid) return "";

          let rollhistory = "Roll History:<br>";

          for (
            let i = 0, len = window.DiceRoller.DiceRoom.DiceBox.diceList.length;
            i < len;
            ++i
          ) {
            let dicemesh = window.DiceRoller.DiceRoom.DiceBox.diceList[i];
            if (!dicemesh || !dicemesh.notation) continue;
            let diceobj = window.DiceRoller.DiceFactory.get(
              dicemesh.notation.type,
            );

            if (dicemesh.uuid == diceid) {
              for (let j = 0, len = dicemesh.result.length; j < len; ++j) {
                let historyresult = dicemesh.result[j];

                let showvalue =
                  diceobj.display == "values"
                    ? historyresult.value
                    : historyresult.label;

                if (historyresult.ignore)
                  showvalue = '<span class="ignored">' + showvalue + "</span>";

                rollhistory +=
                  "Roll " +
                  (j + 1) +
                  ": " +
                  showvalue +
                  " (" +
                  historyresult.reason +
                  ")<br>";
              }
            }
          }
          return rollhistory;
        },
      });
    });
  }
  action_chat(res) {
    alert("Oh my god!");
    if (res.uuid) this.TealChat.confirm_message(res.uuid);
    else this.TealChat.add_message(res.user, res.text, res.time);
  }
  action_colorset(res) {
    alert("colorset: " + res.colorset);
  }
  action_texture(res) {
    alert("texture: " + res.texture);
  }
  action_material(res) {
    alert("material: " + res.material);
  }
  action_roomlist(res) {
    console.log(res);
  }
}

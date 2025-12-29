<template>
  <div class="dice-roller-root" :class="{ 'chat-disabled': !chatEnabled }">
    <input type="hidden" id="parent_notation" value="" />
    <input type="hidden" id="parent_roll" value="0" />
    <button id="turnOnRoom" title="Roll for Myself" style="display: none">
      Roll for Myself
    </button>

    <div id="waitform"></div>

    <div id="loginform" style="display: none">
      <div style="display: table-cell; vertical-align: middle">
        <div style="margin-left: auto; margin-right: auto; width: 100%">
          <div class="loginform">
            <fieldset>
              <h1>Major's 3D Dice Roller</h1>
            </fieldset>

            <fieldset>
              <legend>Offline Dice</legend>
              <div class="lform">
                <button id="button_single" title="Roll for Myself">
                  Roll for Myself
                </button>
              </div>
            </fieldset>
          </div>
        </div>
      </div>
    </div>

    <div id="desk" class="noselect">
      <div id="selector_div" style="display: none">
        <div class="center_field">
          <div class="selector-row selector-actions">
            <button id="clear" class="selector-button" title="Reset Dice" aria-label="Reset Dice">
              <svg class="selector-icon bi bi-arrow-clockwise" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 1 1 .908-.417A6 6 0 1 1 8 2v1z" />
                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966a.25.25 0 0 1 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z" />
              </svg>
              <span class="button-label">Reset</span>
            </button>
            <button id="save" class="selector-button" title="Save Favorite" aria-label="Save Favorite">
              <svg class="selector-icon bi bi-bookmark-fill" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M2 2v12.5a.5.5 0 0 0 .757.429L8 11.5l5.243 3.429A.5.5 0 0 0 14 14.5V2a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z" />
              </svg>
              <span class="button-label">Save</span>
            </button>
            <button id="rage" class="selector-button" title="Add Rage" aria-label="Add Rage">
              <svg class="selector-icon bi bi-lightning-fill" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.844l-8 8.5a.5.5 0 0 1-.843-.451L6.323 9.5H3a.5.5 0 0 1-.364-.844l8-8.5a.5.5 0 0 1 .615-.088z" />
              </svg>
              <span class="button-label">Rage</span>
            </button>
            <button id="throw" class="selector-button" title="Throw Dice" aria-label="Throw Dice">
              <svg class="selector-icon bi bi-dice-5-fill" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M13 1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h10zM6 5a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm4 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm1-5a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" />
              </svg>
              <span class="button-label">Throw</span>
            </button>
            <button
              id="cp_showsettings"
              class="selector-button"
              title="Dice settings"
              aria-label="Dice settings"
            >
              <svg class="selector-icon bi bi-gear-fill" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M9.405 1.05a1 1 0 0 0-1.81 0l-.34.68a5.5 5.5 0 0 0-1.357.785l-.733-.305a1 1 0 0 0-1.279.578l-.357.857a1 1 0 0 0 .305 1.145l.64.52a5.5 5.5 0 0 0 0 1.57l-.64.52a1 1 0 0 0-.305 1.145l.357.857a1 1 0 0 0 1.279.578l.733-.305a5.5 5.5 0 0 0 1.357.785l.34.68a1 1 0 0 0 1.81 0l.34-.68a5.5 5.5 0 0 0 1.357-.785l.733.305a1 1 0 0 0 1.279-.578l.357-.857a1 1 0 0 0-.305-1.145l-.64-.52a5.5 5.5 0 0 0 0-1.57l.64-.52a1 1 0 0 0 .305-1.145l-.357-.857a1 1 0 0 0-1.279-.578l-.733.305a5.5 5.5 0 0 0-1.357-.785l-.34-.68z" />
                <path d="M8 5.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z" />
              </svg>
              <span class="button-label">Settings</span>
            </button>
          </div>
          <div class="selector-row selector-notation">
            <input type="text" id="set" name="set" value="1d100+1d10" />
            <button
              v-if="showDiceDisplayToggle"
              id="toggle_dice_display"
              class="selector-button dice-toggle-button"
              title="Toggle Dice Display"
              aria-pressed="false"
              aria-label="Show Dice"
            >
              <svg class="dice-toggle-icon bi bi-eye-fill" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z" />
                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z" />
              </svg>
              <span class="toggle-label">Show Dice</span>
            </button>
            <button
              v-if="showDragThrowToggle"
              id="toggle_drag_throw"
              class="selector-button dice-toggle-button"
              title="Toggle Drag Roll"
              aria-pressed="true"
              aria-label="Disable Drag Roll"
            >
              <svg
                class="dice-toggle-icon bi bi-mouse"
                viewBox="0 0 16 16"
                aria-hidden="true"
              >
                <path d="M8 0a5 5 0 0 0-5 5v3a5 5 0 0 0 10 0V5a5 5 0 0 0-5-5zm4 8a4 4 0 0 1-8 0V5a4 4 0 0 1 8 0v3z" />
                <path d="M8 1.5a.5.5 0 0 1 .5.5V4h-1V2a.5.5 0 0 1 .5-.5z" />
              </svg>
              <span class="toggle-label">Disable Drag Roll</span>
            </button>
          </div>
          <div id="sethelp">Set notation, e.g. 2d6+1</div>
          <div id="labelhelp">Click dice or drag to throw</div>
        </div>
      </div>

      <div id="canvas"></div>

      <div id="info_div" style="display: none">
        <div class="center_field">
          <div id="label"></div>
        </div>
      </div>

      <div class="info-field">
        <div class="center_field">
          <span id="label_players" style="display: none"></span>
        </div>
      </div>
    </div>

    <div id="log" class="teal-chat" style="display: none"></div>

    <div id="fav_container">
      <fieldset class="fav_draggable">
        <legend class="fav_name">Attack</legend>
        <button class="fav_edit" title="Edit Favorite Name">Edit</button>
        <button class="fav_delete" title="Delete Favorite">Delete</button>
        <input type="text" class="fav_notation" value="2d20" />
        <button class="fav_throw" title="Throw Dice">Throw</button>
        <input type="hidden" class="fav_colorset" value="" />
        <input type="hidden" class="fav_texture" value="" />
      </fieldset>
    </div>

    <fieldset id="control_panel" class="noselect" style="display: none">
      <legend>Settings</legend>
      <fieldset>
        <legend>Dice</legend>
        <div>
          <label id="colorname" class="control_label" for="color">
            Theme: Black - <a href="?colorset=black&amp;texture=fire">Link</a>
          </label>
          <select id="color" name="color">
            <optgroup label="Custom Sets">
              <option value="breebaby">Pastel Sunset</option>
              <option value="pinkdreams">Pink Dreams</option>
              <option value="inspired">Inspired</option>
              <option value="bloodmoon">Blood Moon</option>
              <option value="starynight">Stary Night</option>
              <option value="glitterparty">Glitter Party</option>
              <option value="astralsea">Astral Sea</option>
              <option value="bronze">Thylean Bronze</option>
              <option value="dragons">Here be Dragons</option>
              <option value="birdup">Bird Up</option>
            </optgroup>
            <optgroup label="Damage Types">
              <option value="radiant">Radiant</option>
              <option value="fire">Fire</option>
              <option value="ice">Ice</option>
              <option value="poison">Poison</option>
              <option value="acid">Acid</option>
              <option value="thunder">Thunder</option>
              <option value="lightning">Lightning</option>
              <option value="air">Air</option>
              <option value="water">Water</option>
              <option value="earth">Earth</option>
              <option value="force">Force</option>
              <option value="psychic">Psychic</option>
              <option value="necrotic">Necrotic</option>
            </optgroup>
            <optgroup label="Colors">
              <option value="test">Test</option>
              <option value="rainbow">Rainbow</option>
              <option value="random">Random</option>
              <option value="black" selected="selected">Black</option>
              <option value="white">White</option>
            </optgroup>
            <optgroup label="Other">
              <option value="coin_default">Gold Coin</option>
              <option value="coin_silver">Silver Coin</option>
              <option value="tigerking">Tiger King</option>
              <option value="covid">COVID</option>
              <option value="acleaf">Animal Crossing</option>
              <option value="isabelle">Isabelle</option>
              <option value="thecage">Nicholas Cage</option>
            </optgroup>
            <optgroup label="Star Wars RPG">
              <option value="swrpg_abi">Star Wars RPG - Ability</option>
              <option value="swrpg_pro">Star Wars RPG - Proficiency</option>
              <option value="swrpg_dif">Star Wars RPG - Difficulty</option>
              <option value="swrpg_cha">Star Wars RPG - Challenge</option>
              <option value="swrpg_boo">Star Wars RPG - Boost</option>
              <option value="swrpg_set">Star Wars RPG - Setback</option>
              <option value="swrpg_for">Star Wars RPG - Force</option>
            </optgroup>
            <optgroup label="Star Wars Armada">
              <option value="swa_red">Armada Attack - Red</option>
              <option value="swa_blue">Armada Attack - Blue</option>
              <option value="swa_black">Armada Attack - Black</option>
            </optgroup>
            <optgroup label="Star Wars X-Wing">
              <option value="xwing_red">X-Wing Attack - Red</option>
              <option value="xwing_green">X-Wing Attack - Green</option>
            </optgroup>
            <optgroup label="Star Wars Legion">
              <option value="swl_atkred">Legion Attack - Red</option>
              <option value="swl_atkblack">Legion Attack - Black</option>
              <option value="swl_atkwhite">Legion Attack - White</option>
              <option value="swl_defred">Legion Defense - Red</option>
              <option value="swl_defwhite">Legion Defense - White</option>
            </optgroup>
          </select>

          <select id="texture" name="texture">
            <option value="cloudy">Clouds (Transparent)</option>
            <option value="cloudy_2">Clouds</option>
            <option value="fire" selected="selected">Fire</option>
            <option value="marble">Marble</option>
            <option value="water">Water</option>
            <option value="ice">Ice</option>
            <option value="paper">Paper</option>
            <option value="speckles">Speckles</option>
            <option value="glitter">Glitter</option>
            <option value="glitter_2">Glitter (Transparent)</option>
            <option value="stars">Stars</option>
            <option value="stainedglass">Stained Glass</option>
            <option value="wood">Wood</option>
            <option value="metal">Stainless Steel</option>
            <option value="skulls">Skulls</option>
            <option value="leopard">Leopard</option>
            <option value="tiger">Tiger</option>
            <option value="cheetah">Cheetah</option>
            <option value="dragon">Dragon</option>
            <option value="lizard">Lizard</option>
            <option value="bird">Bird</option>
            <option value="astral">Astral Sea</option>
            <option value="acleaf">AC Leaf</option>
            <option value="thecage">Nicholas Cage</option>
            <option value="isabelle">Isabelle</option>
            <option value="bronze01">bronze01</option>
            <option value="bronze02">bronze02</option>
            <option value="bronze03">bronze03</option>
            <option value="bronze03a">bronze03a</option>
            <option value="bronze03b">bronze03b</option>
            <option value="bronze04">bronze04</option>
            <option value="none">None</option>
            <option value="">~ Preset ~</option>
          </select>

          <select id="material" name="material">
            <option value="">~ Preset ~</option>
            <option value="none">Plastic</option>
            <option value="perfectmetal">Perfect Metal</option>
            <option value="metal">Metal</option>
            <option value="wood">Wood</option>
            <option value="glass">Glass</option>
          </select>

          <label
            for="checkbox_allowdiceoverride"
            title="Allow some dice to override your color settings"
          >
            <input type="checkbox" value="" id="checkbox_allowdiceoverride" />
            Allow Some Dice to Override Colors
          </label>
          <p>Star Wars dice have themes they can use individually.</p>
          <label for="system" title="Select which type of dice to display"
            >Set:</label
          >
          <select id="system" name="system">
            <option value="d20" selected="selected">D20</option>
            <option value="dweird">D-Weird</option>
            <option value="swrpg">Star Wars RPG</option>
            <option value="swarmada">Star Wars Armada</option>
            <option value="xwing">Star Wars X-Wing</option>
            <option value="legion">Star Wars Legion</option>
            <option value="all">ALL THE DICE</option>
          </select>
        </div>
      </fieldset>
      <fieldset>
        <legend>Site Theme</legend>
        <div>
          <label for="theme" title="Set an overall color theme for the page"
            >Theme:</label
          >
          <select id="theme" name="theme">
            <option value="default">Solid Color</option>
            <option value="blue-felt" selected="selected">Blue Felt</option>
            <option value="red-felt">Red Felt</option>
            <option value="green-felt">Green Felt</option>
            <option value="taverntable">Old Tavern Table</option>
            <option value="mahogany">Mahogany</option>
            <option value="stainless">Stainless Steel</option>
            <option value="cyberpunk">Neo Future City</option>
            <option value="cagetown">Cage Town</option>
          </select>
          <label for="fgcolor">Foreground:</label>
          <input id="fgcolor" class="control_fgcolor" value="rgb(0,255,0)" />
          <label for="bgcolor">Background:</label>
          <input id="bgcolor" class="control_bgcolor" value="rgb(0,255,0)" />
        </div>
      </fieldset>
      <fieldset>
        <legend>Graphics</legend>
        <div>
          <label for="checkbox_bumpmap" title="Enable/Disable Bumpmapping">
            <input type="checkbox" value="" id="checkbox_bumpmap" /> Bumpmapping
          </label>
          <label for="checkbox_shadows" title="Enable/Disable Shadows">
            <input type="checkbox" value="" id="checkbox_shadows" /> Shadows
          </label>
          <p>May speed up rendering. Recommend off for chroma keying.</p>
          <label for="checkbox_sounds" title="Enable/Disable Sound Effects">
            <input type="checkbox" value="" id="checkbox_sounds" /> Sound
            Effects
          </label>
          <select id="surface" name="surface">
            <option value="felt">Felt</option>
            <option value="wood_tray">Wood Tray</option>
            <option value="wood_table">Wood Table</option>
            <option value="metal">Metal</option>
          </select>
          <p>Can cause lag on older browsers or systems.</p>
          <label for="volume_slider">Sounds Effects Volume: </label>
          <div id="volume_slider">
            <div id="volume_handle" class="ui-slider-handle"></div>
          </div>
        </div>
      </fieldset>
      <fieldset>
        <legend>Elements</legend>
        <div>
          <button id="toggle_selector" title="Show/Hide Throw Buttons">
            Toggle Dice Buttons
          </button>
          <label for="checkbox_tally" title="Show/Hide Dice Tally">
            <input type="checkbox" value="" id="checkbox_tally" /> Show Dice
            Tally
          </label>
          <label for="checkbox_users" title="Show/Hide Userlist">
            <input type="checkbox" value="" id="checkbox_users" /> Show Userlist
          </label>
        </div>
      </fieldset>
      <br /><br />
      <div class="connection_message control_label" style="color: orange">
        Loading Textures...
      </div>
      <button id="cp_hidesettings" title="Close Settings Panel">Close</button>
      <button id="reconnect" title="Reconnect">Reconnect</button>
    </fieldset>
  </div>
</template>

<script>
import { onBeforeUnmount, onMounted, nextTick } from "vue";
import { createDiceRoller } from "../../lib/dice-roller/index.js";

export default {
  name: "DiceRoller",
  props: {
    assetBaseUrl: {
      type: String,
      default: "/dice_roller",
    },
    themeId: {
      type: String,
      default: "blue-felt",
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
  emits: ["ready", "error"],
  setup(props, { emit }) {
    let diceRoller = null;
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
        if (el) el.remove();
      });
      document.body.style.backgroundColor = originalBodyStyles.backgroundColor;
      document.body.style.color = originalBodyStyles.color;
      document.body.style.backgroundImage = originalBodyStyles.backgroundImage;
      document.body.style.backgroundSize = originalBodyStyles.backgroundSize;
      document.body.style.backgroundRepeat = originalBodyStyles.backgroundRepeat;
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
        document.body.style.overflow = "hidden";
        document.body.style.margin = "0";
        document.body.style.width = "100%";
        document.body.style.height = "100%";
        document.documentElement.style.overflow = "hidden";
        document.documentElement.style.margin = "0";
        document.documentElement.style.width = "100%";
        document.documentElement.style.height = "100%";
        const fallbackScale =
          typeof props.diceScale === "number" && Number.isFinite(props.diceScale)
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
          onError: (error) => emit("error", error),
        });
        emit("ready", diceRoller);
      } catch (error) {
        emit("error", error);
      }
    };

    onMounted(startDiceRoller);

    onBeforeUnmount(() => {
      if (diceRoller?.destroy) {
        diceRoller.destroy();
      } else if (diceRoller?.close_socket) {
        diceRoller.close_socket();
      }
      if (diceRoller?.DiceRoom?.DiceBox) {
        diceRoller.DiceRoom.DiceBox.running = false;
      }
      removeDiceRollerStyles();
    });

    return {};
  },
};
</script>

<style scoped>
.dice-roller-root {
  position: relative;
  width: 100%;
  height: 100%;
}

#desk {
  margin-left: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
}

#canvas {
  width: 100%;
  height: 100%;
}

#label,
#label h2 {
  color: #fff;
}

#info_div {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 0.5rem 1rem 2.5rem;
  box-sizing: border-box;
  z-index: 12;
  pointer-events: none;
}

#info_div .center_field {
  display: flex;
  justify-content: center;
}

#label {
  margin: 0;
  line-height: 1.2;
  max-width: 100%;
  overflow-wrap: anywhere;
  text-align: center;
}

#label h2 {
  margin: 0.2em 0 0;
  line-height: 1.1;
}

.chat-disabled #selector_div {
  position: fixed;
  left: 0;
  right: 0;
  top: 0;
  z-index: 10;
}

.chat-disabled #log {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 10;
}

.chat-disabled #desk,
.chat-disabled #canvas {
  height: 100vh;
}
.dice-settings-icon {
  width: 1em;
  height: 1em;
  fill: currentColor;
  vertical-align: middle;
}

#selector_div .selector-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.4rem;
}

#selector_div .selector-actions {
  margin-bottom: 0.35rem;
}

.selector-button {
  width: 2.6em;
  height: 2.6em;
  padding: 0;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

#selector_div #set {
  height: 2.6em;
  font-size: 1.1rem;
  line-height: 1.1;
  padding: 0 0.75rem;
}

.selector-button .selector-icon,
.dice-toggle-button .dice-toggle-icon {
  width: 1.4em;
  height: 1.4em;
  stroke: #ffffff;
  stroke-width: 1.6;
  fill: rgba(255, 255, 255, 0.08);
}

.dice-toggle-button.is-active .dice-toggle-icon {
  fill: rgba(255, 255, 255, 0.35);
  stroke: #ffd166;
}

.dice-toggle-button .toggle-label,
.selector-button .button-label {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>

<template>
  <div class="dice-roller-root">
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
          <div>
            <button id="clear" title="Reset Dice">Reset</button>
            <button id="save" title="Save Favorite">Save</button>
            <input type="text" id="set" name="set" value="1d100+1d10" />
            <button id="rage" title="Add Rage">Rage</button>
            <button id="throw" title="Throw Dice">Throw</button>
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

    <fieldset id="control_panel_buttons" class="noselect" style="display: none">
      <button id="cp_showsettings" title="Show Settings Panel">Settings</button>
    </fieldset>

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
  },
  emits: ["ready", "error"],
  setup(props, { emit }) {
    let diceRoller = null;

    const startDiceRoller = async () => {
      try {
        await nextTick();
        diceRoller = await createDiceRoller({
          assetBaseUrl: props.assetBaseUrl,
          themeId: props.themeId,
          autoStart: props.autoStart,
          onError: (error) => emit("error", error),
        });
        emit("ready", diceRoller);
      } catch (error) {
        emit("error", error);
      }
    };

    onMounted(startDiceRoller);

    onBeforeUnmount(() => {
      if (diceRoller?.close_socket) {
        diceRoller.close_socket();
      }
      if (diceRoller?.DiceRoom?.DiceBox) {
        diceRoller.DiceRoom.DiceBox.running = false;
      }
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
}

#canvas {
  width: 100%;
  height: 100%;
}
</style>

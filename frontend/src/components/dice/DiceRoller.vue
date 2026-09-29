<template>
  <div
    ref="root"
    class="dice-roller-root"
    :class="{
      'chat-disabled': !chatEnabled,
      'dice-roller-root--embedded': embedded,
    }"
  >
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
            <button
              id="clear"
              class="selector-button"
              title="Reset Dice"
              aria-label="Reset Dice"
            >
              <svg
                class="selector-icon bi bi-arrow-clockwise"
                viewBox="0 0 16 16"
                aria-hidden="true"
              >
                <path
                  d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 1 1 .908-.417A6 6 0 1 1 8 2v1z"
                />
                <path
                  d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966a.25.25 0 0 1 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"
                />
              </svg>
              <span class="button-label">Reset</span>
            </button>
            <button
              v-if="showAdvancedControls"
              id="save"
              class="selector-button"
              title="Save Favorite"
              aria-label="Save Favorite"
            >
              <svg
                class="selector-icon bi bi-bookmark-fill"
                viewBox="0 0 16 16"
                aria-hidden="true"
              >
                <path
                  d="M2 2v12.5a.5.5 0 0 0 .757.429L8 11.5l5.243 3.429A.5.5 0 0 0 14 14.5V2a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"
                />
              </svg>
              <span class="button-label">Save</span>
            </button>
            <button
              v-if="showAdvancedControls"
              id="rage"
              class="selector-button"
              title="Add Rage"
              aria-label="Add Rage"
            >
              <svg
                class="selector-icon bi bi-lightning-fill"
                viewBox="0 0 16 16"
                aria-hidden="true"
              >
                <path
                  d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.844l-8 8.5a.5.5 0 0 1-.843-.451L6.323 9.5H3a.5.5 0 0 1-.364-.844l8-8.5a.5.5 0 0 1 .615-.088z"
                />
              </svg>
              <span class="button-label">Rage</span>
            </button>
            <button
              id="throw"
              class="selector-button"
              title="Throw Dice"
              aria-label="Throw Dice"
            >
              <svg
                class="selector-icon bi bi-dice-5-fill"
                viewBox="0 0 16 16"
                aria-hidden="true"
              >
                <path
                  d="M13 1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h10zM6 5a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm4 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm1-5a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"
                />
              </svg>
              <span class="button-label">Throw</span>
            </button>
            <button
              v-if="showAdvancedControls"
              id="cp_showsettings"
              class="selector-button"
              title="Dice settings"
              aria-label="Dice settings"
            >
              <svg
                class="selector-icon bi bi-gear-fill"
                viewBox="0 0 16 16"
                aria-hidden="true"
              >
                <path
                  d="M9.405 1.05a1 1 0 0 0-1.81 0l-.34.68a5.5 5.5 0 0 0-1.357.785l-.733-.305a1 1 0 0 0-1.279.578l-.357.857a1 1 0 0 0 .305 1.145l.64.52a5.5 5.5 0 0 0 0 1.57l-.64.52a1 1 0 0 0-.305 1.145l.357.857a1 1 0 0 0 1.279.578l.733-.305a5.5 5.5 0 0 0 1.357.785l.34.68a1 1 0 0 0 1.81 0l.34-.68a5.5 5.5 0 0 0 1.357-.785l.733.305a1 1 0 0 0 1.279-.578l.357-.857a1 1 0 0 0-.305-1.145l-.64-.52a5.5 5.5 0 0 0 0-1.57l.64-.52a1 1 0 0 0 .305-1.145l-.357-.857a1 1 0 0 0-1.279-.578l-.733.305a5.5 5.5 0 0 0-1.357-.785l-.34-.68z"
                />
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
              <svg
                class="dice-toggle-icon bi bi-eye-fill"
                viewBox="0 0 16 16"
                aria-hidden="true"
              >
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
                <path
                  d="M8 0a5 5 0 0 0-5 5v3a5 5 0 0 0 10 0V5a5 5 0 0 0-5-5zm4 8a4 4 0 0 1-8 0V5a4 4 0 0 1 8 0v3z"
                />
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

    <DiceRollerSettingsPanel />
  </div>
</template>

<script src="./options/DiceRoller.options.js"></script>

<style scoped src="./styles/DiceRoller.css"></style>

import UiButton from "./UiButton.vue";
import UiField from "./UiField.vue";
import UiPanel from "./UiPanel.vue";
import UiTooltip from "./UiTooltip.vue";

const UI_COMPONENTS = [UiButton, UiField, UiPanel, UiTooltip];

export const UiKit = Object.freeze({
  install(app) {
    UI_COMPONENTS.forEach((component) => {
      app.component(component.name, component);
    });
  },
});

export { UiButton, UiField, UiPanel, UiTooltip };

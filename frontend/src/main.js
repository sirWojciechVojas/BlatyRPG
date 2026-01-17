import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store";
import i18n, { initI18n } from "./i18n";


async function initializeApp() {
    const app = createApp(App);
    app.use(store)
    app.use(router)
    app.use(i18n)
    await initI18n();
    app.mount("#app");
}

initializeApp();

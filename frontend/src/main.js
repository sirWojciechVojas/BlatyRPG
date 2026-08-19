import { createApp } from "vue";
import App from "./App.vue";
import router from "./router";
import store from "./store";
import i18n, { initI18n } from "./i18n";
import { installAuthLifecycle } from "./lib/auth/authLifecycle";
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap/dist/js/bootstrap.bundle.min.js";

async function initializeApp() {
  const app = createApp(App);
  app.use(store);
  app.use(router);
  app.use(i18n);
  installAuthLifecycle({ store, router });
  await initI18n();
  app.mount("#app");
}

initializeApp();

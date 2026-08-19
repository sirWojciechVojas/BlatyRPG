import { createRouter, createWebHashHistory } from "vue-router";
import HomeView from "../views/HomeView.vue";
import store from "../store";
import { ensureShopStoreModuleForRoute } from "../store/modules/loadShopModule";

const routes = [
  {
    path: "/",
    name: "home",
    component: HomeView,
  },
  {
    path: "/about",
    name: "about",
    // route level code-splitting
    // this generates a separate chunk (about.[hash].js) for this route
    // which is lazy-loaded when the route is visited.
    component: () =>
      import(/* webpackChunkName: "about" */ "../views/AboutView.vue"),
  },
  {
    path: "/dice",
    name: "dice",
    meta: { title: "Dice Roller 3D" },
    component: () =>
      import(/* webpackChunkName: "dice" */ "../views/DiceRollerView.vue"),
  },
  {
    path: "/campaigns/:campaignId/shop",
    name: "shop-gm",
    meta: { title: "Shop — GM", requiresGm: true },
    beforeEnter: () => ensureShopStoreModuleForRoute(store),
    component: () =>
      import(/* webpackChunkName: "shop-gm" */ "../views/ShopGmWorkspace.vue"),
  },
  {
    path: "/403",
    name: "forbidden",
    meta: { title: "403" },
    component: () =>
      import(/* webpackChunkName: "forbidden" */ "../views/ForbiddenView.vue"),
  },
  {
    path: "/:pathMatch(.*)*",
    name: "not-found",
    meta: { title: "404" },
    component: () =>
      import(/* webpackChunkName: "not-found" */ "../views/NotFoundView.vue"),
  },
];

const router = createRouter({
  history: createWebHashHistory(),
  routes,
});

export default router;

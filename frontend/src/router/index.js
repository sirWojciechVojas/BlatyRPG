import { createRouter, createWebHashHistory } from "vue-router";
import DashboardHomeView from "../views/DashboardHomeView.vue";
import store from "../store";
import { ensureShopStoreModuleForRoute } from "../store/modules/loadShopModule";
import { ensureVttStoreModuleForRoute } from "../store/modules/loadVttModule";
import { createAuthGuard } from "./authGuard";

const routes = [
  {
    path: "/",
    name: "home",
    component: DashboardHomeView,
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
    meta: { title: "Shop — GM", requiresAuth: true, requiresGm: true },
    beforeEnter: () => ensureShopStoreModuleForRoute(store),
    component: () =>
      import(/* webpackChunkName: "shop-gm" */ "../views/ShopGmWorkspace.vue"),
  },
  {
    path: "/campaigns/:campaignId/scenes",
    name: "scene-workspace",
    meta: { title: "Scenes", requiresAuth: true },
    beforeEnter: () => ensureVttStoreModuleForRoute(store),
    component: () =>
      import(
        /* webpackChunkName: "scene-workspace" */ "../views/SceneWorkspaceView.vue"
      ),
  },
  {
    path: "/403",
    name: "forbidden",
    meta: { title: "403" },
    component: () =>
      import(/* webpackChunkName: "forbidden" */ "../views/ForbiddenView.vue"),
  },
  {
    path: "/admin",
    name: "admin",
    meta: { title: "Administration", requiresAuth: true, requiresAdmin: true },
    component: () =>
      import(/* webpackChunkName: "administration" */ "../views/AdminView.vue"),
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

router.beforeEach(createAuthGuard());

export default router;

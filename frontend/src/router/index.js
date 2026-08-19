import { createRouter, createWebHashHistory } from "vue-router";
import HomeView from "../views/HomeView.vue";
import store from "../store";
import { ensureShopStoreModuleForRoute } from "../store/modules/loadShopModule";
import { ensureVttStoreModuleForRoute } from "../store/modules/loadVttModule";
import { createAuthGuard } from "./authGuard";
import {
  createCampaignAuthorization,
  createCampaignSessionGuard,
} from "./campaignSessionGuard";

const routes = [
  {
    path: "/",
    name: "landing",
    meta: { redirectAuthenticated: true },
    component: HomeView,
  },
  {
    path: "/home",
    name: "home",
    redirect: { name: "tables" },
  },
  {
    path: "/login",
    name: "login",
    meta: { title: "Sign in", redirectAuthenticated: true },
    component: () =>
      import(/* webpackChunkName: "authentication" */ "../views/LoginView.vue"),
  },
  {
    path: "/tables",
    name: "tables",
    meta: { title: "Tables", requiresAuth: true },
    component: () =>
      import(
        /* webpackChunkName: "campaign-dashboard" */ "../views/DashboardHomeView.vue"
      ),
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
    path: "/register",
    name: "register",
    meta: { title: "Register" },
    component: () =>
      import(
        /* webpackChunkName: "authentication" */ "../views/RegisterView.vue"
      ),
  },
  {
    path: "/password-reset",
    name: "password-reset-request",
    meta: { title: "Reset password" },
    component: () =>
      import(
        /* webpackChunkName: "authentication" */ "../views/PasswordResetRequestView.vue"
      ),
  },
  {
    path: "/password-reset/confirm",
    name: "password-reset-confirm",
    meta: { title: "Set new password" },
    component: () =>
      import(
        /* webpackChunkName: "authentication" */ "../views/PasswordResetConfirmView.vue"
      ),
  },
  {
    path: "/profile",
    name: "profile",
    meta: { title: "Profile", requiresAuth: true },
    component: () =>
      import(
        /* webpackChunkName: "authentication" */ "../views/ProfileView.vue"
      ),
  },
  {
    path: "/invitations",
    name: "my-invitations",
    meta: { title: "Invitations", requiresAuth: true },
    component: () =>
      import(
        /* webpackChunkName: "campaign-lobby" */ "../views/MyInvitationsView.vue"
      ),
  },
  {
    path: "/dice",
    name: "dice",
    meta: { title: "Dice Roller 3D" },
    component: () =>
      import(/* webpackChunkName: "dice" */ "../views/DiceRollerView.vue"),
  },
  {
    path: "/campaigns/:campaignId",
    name: "campaign-lobby",
    meta: { title: "Campaign lobby", requiresAuth: true },
    component: () =>
      import(
        /* webpackChunkName: "campaign-lobby" */ "../views/CampaignLobbyView.vue"
      ),
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
    path: "/campaigns/:campaignId/characters",
    name: "character-workspace",
    meta: { title: "Characters", requiresAuth: true },
    component: () =>
      import(
        /* webpackChunkName: "characters" */ "../views/CharacterWorkspaceView.vue"
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

router.beforeEach(
  createAuthGuard(undefined, createCampaignAuthorization(store)),
);
router.beforeEach(createCampaignSessionGuard(store));

export default router;

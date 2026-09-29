const loaders = {
  admin: () =>
    import(/* webpackChunkName: "administration" */ "@/views/AdminView.vue"),
  tables: () =>
    import(
      /* webpackChunkName: "campaign-dashboard" */ "@/views/DashboardHomeView.vue"
    ),
};

export const loadAdminView = loaders.admin;
export const loadDashboardView = loaders.tables;

export const preloadAuthenticatedView = (session) => {
  const role = String(session?.user?.role || "user").toLowerCase();
  return (role === "admin" ? loaders.admin : loaders.tables)();
};

export const preloadDefaultAuthenticatedView = () => loaders.tables();

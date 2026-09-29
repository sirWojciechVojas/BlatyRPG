const { defineConfig } = require("@vue/cli-service");

const pollInterval = Number.parseInt(
  process.env.WATCHPACK_POLLING_INTERVAL ||
    process.env.CHOKIDAR_INTERVAL ||
    "2000",
  10,
);
const validPoll =
  Number.isFinite(pollInterval) && pollInterval > 0 ? pollInterval : 2000;

const usePolling =
  process.env.CHOKIDAR_USEPOLLING === "true" ||
  process.env.WATCHPACK_POLLING === "true" ||
  process.env.VUE_CLI_USE_POLLING === "true";

module.exports = defineConfig({
  transpileDependencies: true,
  lintOnSave: false,

  devServer: {
    host: "0.0.0.0",
    port: 8080,
    allowedHosts: "all",
    client: {
      webSocketURL:
        process.env.WDS_SOCKET_URL ||
        `auto://0.0.0.0:0${process.env.WDS_SOCKET_PATH || "/ws"}`,
      overlay: {
        errors: true,
        warnings: true,
        runtimeErrors: (error) =>
          ![
            "ResizeObserver loop completed with undelivered notifications.",
            "ResizeObserver loop limit exceeded",
          ].includes(error?.message),
      },
    },
    webSocketServer: "ws",
    hot: true,
    liveReload: true,
  },

  configureWebpack: usePolling
    ? {
        watchOptions: {
          poll: validPoll,
          aggregateTimeout: 400,
          ignored: /(node_modules|\.git|dist|coverage|\.cache|tmp|logs?)/,
        },
      }
    : {},

  chainWebpack: (config) => {
    const runtimeAssets = /character-hud[\\/](?:runtime|v8)[\\/].*\.webp$/iu;

    // Assets copied from the GUI pack live in public/ and intentionally keep
    // their manifest paths instead of being fingerprinted a second time.
    config.module.rule("css").oneOfs.store.forEach((oneOf) => {
      oneOf.use("css-loader").tap((options = {}) => ({
        ...options,
        url: {
          filter: (url) => !url.startsWith("/blaty-rpg-gui/"),
        },
      }));
    });

    // Keep the versioned HUD WebP textures independently cacheable.
    // The default `asset` rule inlines small files, which would otherwise place
    // the shared button and frame textures inside the JavaScript chunk.
    config.module.rule("images").exclude.add(runtimeAssets);
    config.module
      .rule("character-hud-runtime")
      .test(runtimeAssets)
      .type("asset/resource")
      .set("generator", {
        filename: "img/[name].[contenthash:8][ext]",
      });
  },
});

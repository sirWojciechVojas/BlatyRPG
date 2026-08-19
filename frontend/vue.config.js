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
});

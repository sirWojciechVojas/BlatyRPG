const { defineConfig } = require("@vue/cli-service");

module.exports = defineConfig({
  transpileDependencies: true,

  devServer: {
    port: 8080,
    allowedHosts: "all",
    client: {
      webSocketURL: (() => {
        const host = process.env.WDS_SOCKET_HOST || "localhost";
        const port = process.env.WDS_SOCKET_PORT || "443";
        const protocol = process.env.WDS_SOCKET_PROTOCOL || "wss";
        const path = process.env.WDS_SOCKET_PATH || "/sockjs-node";
        return `${protocol}://${host}:${port}${path}`;
      })(),
    },
    hot: true,
    liveReload: true,
    watchFiles: {
      paths: ["src/**/*", "public/**/*"],
      options: {
        usePolling: true,
        interval: 300,
      },
    },
  },

  configureWebpack: {
    watchOptions: {
      poll: 300,
      aggregateTimeout: 200,
      ignored: /node_modules/,
    },
  },
});

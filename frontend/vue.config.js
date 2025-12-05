const { defineConfig } = require("@vue/cli-service");

const fs = require('fs');
const path = require('path');

module.exports = defineConfig({
  transpileDependencies: true,

  devServer: {
    port: 8080,
    allowedHosts: 'all',
    client: {
      webSocketURL: 'auto://localhost/ws', // Vue połączy się przez Nginx
    }
  }
});

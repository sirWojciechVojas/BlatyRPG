import { createConfig } from "./config.js";
import { createRealtimeServer } from "./realtime-server.js";

const config = createConfig();
const server = createRealtimeServer(config);

const address = await server.start();
const host = typeof address === "object" && address ? address.address : config.host;
const port = typeof address === "object" && address ? address.port : config.port;
console.log(`BlatyRPG WebSocket listening on ${host}:${port}${config.path}`);

const shutdown = async (signal) => {
  console.log(`Received ${signal}; stopping WebSocket server`);
  await server.stop();
  process.exit(0);
};

process.once("SIGINT", () => void shutdown("SIGINT"));
process.once("SIGTERM", () => void shutdown("SIGTERM"));

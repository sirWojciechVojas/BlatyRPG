import http from "node:http";
import { WebSocketServer } from "ws";
import { isOriginAllowed } from "./origin-policy.js";

const requestPath = (request) => {
  try {
    return new URL(request.url || "/", "http://websocket.internal").pathname;
  } catch {
    return null;
  }
};

const rejectUpgrade = (socket, status, message) => {
  const body = `${message}\n`;
  socket.write(
    `HTTP/1.1 ${status}\r\nConnection: close\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Length: ${Buffer.byteLength(body)}\r\n\r\n${body}`,
  );
  socket.destroy();
};

export const createHttpTransport = (config, healthResponse) => {
  const httpServer = http.createServer((request, response) => {
    if (["GET", "HEAD"].includes(request.method) && requestPath(request) === config.healthPath) {
      const body = JSON.stringify(healthResponse());
      response.writeHead(200, {
        "Content-Type": "application/json; charset=utf-8",
        "Content-Length": Buffer.byteLength(body),
        "Cache-Control": "no-store",
      });
      response.end(request.method === "HEAD" ? undefined : body);
      return;
    }
    response.writeHead(404, { "Content-Type": "text/plain; charset=utf-8" });
    response.end("Not found\n");
  });

  const wss = new WebSocketServer({
    noServer: true,
    clientTracking: false,
    maxPayload: config.maxPayloadBytes,
    perMessageDeflate: false,
  });

  httpServer.on("upgrade", (request, socket, head) => {
    if (String(request.url || "").includes("?")) {
      rejectUpgrade(socket, "400 Bad Request", "WebSocket query strings are forbidden");
      return;
    }
    if (requestPath(request) !== config.path) {
      rejectUpgrade(socket, "404 Not Found", "WebSocket endpoint not found");
      return;
    }
    if (
      !isOriginAllowed(
        request.headers.origin,
        config.allowedOrigins,
        config.allowMissingOrigin,
      )
    ) {
      rejectUpgrade(socket, "403 Forbidden", "WebSocket origin rejected");
      return;
    }
    wss.handleUpgrade(request, socket, head, (ws) => wss.emit("connection", ws, request));
  });

  return { httpServer, wss };
};

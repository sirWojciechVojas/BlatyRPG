# BlatyRPG realtime server

Standalone Node service for authenticated campaign presence and the protocol
foundation used by later VTT synchronization. It deliberately does not mutate
campaign, character, scene, chat, combat, or other domain state.

## Trust boundary

The HTTP backend remains responsible for login, campaign membership, and
permissions. Before connecting, the browser obtains a short-lived HS256 ticket
from the authenticated backend. The ticket must contain:

- `iss`: configured issuer (`BlatyRPG` by default),
- `aud`: configured audience (`blatyrpg-realtime` by default),
- `iat` and `exp`: integer NumericDate values, no more than 60 seconds apart by
  default,
- `jti`: unique identifier consumed once by this server process,
- `sub`: authenticated user ID,
- `campaign_id`: campaign already authorized by the backend.

The required `client_instance_id` claim must match the first auth message and
use 16–128 characters from `A-Z`, `a-z`, `0-9`, `_`, or `-`. Optional
signed claims are `display_name`, `campaign_role`, and `capabilities`.
Client-provided user IDs, campaign IDs, timestamps, sequence numbers, and actor
metadata are never accepted.

The in-memory `jti` replay cache is correct for the current single server. A
shared replay cache is required before horizontally scaling this service.

## Protocol v1

The first and only permitted unauthenticated message is:

```json
{
  "v": 1,
  "type": "auth",
  "ticket": "signed.jwt",
  "clientInstanceId": "stable-per-browser-tab",
  "lastSequence": 12,
  "requestId": "optional-correlation-id"
}
```

Authentication in URL query parameters is rejected before the WebSocket
upgrade, preventing tickets from leaking into proxy access logs.

After authentication the client may send only:

```json
{ "v": 1, "type": "sync.request", "lastSequence": 12, "requestId": "sync-1" }
{ "v": 1, "type": "campaign.leave", "requestId": "leave-1" }
```

All server events use an authoritative envelope:

```json
{
  "v": 1,
  "eventId": "server-uuid",
  "type": "presence.changed",
  "campaignId": 7,
  "sequence": 13,
  "actorUserId": 42,
  "occurredAt": "2026-08-19T12:00:00.000Z",
  "payload": {}
}
```

Current event types are:

- `session.ready`, `session.replaced`, `session.left`,
- `presence.snapshot`, `presence.changed`,
- `sync.snapshot`,
- `protocol.error`.

`presence.snapshot` is sent after every successful auth. `sync.snapshot`
returns the current presence stream and reports whether the requested sequence
was stale. Later domain streams can extend this response only after their data
has been committed and authorized by the backend.

Presence is counted per user across connections. Reusing the same
`clientInstanceId` atomically replaces the stale socket; separate browser tabs
remain separate connections but one online user. The last disconnect enters a
short reconnect grace period before an offline event is broadcast.

Close codes:

- `4001`: auth missing, invalid, timed out, or expired,
- `4008`: message rate exceeded,
- `4010`: stale socket replaced by the same client instance,
- `1000`: explicit campaign leave or normal close.

## Environment

Required:

- `WS_TICKET_SECRET`: at least 32 bytes; never commit it,
- `WS_ALLOWED_ORIGINS`: comma-separated exact HTTP(S) origins; wildcards are
  rejected.

Optional configuration:

- `WS_HOST=0.0.0.0`, `WS_PORT=8081`, `WS_PATH=/realtime`,
- `WS_HEALTH_PATH=/health`,
- `WS_TICKET_ISSUER=BlatyRPG`,
- `WS_TICKET_AUDIENCE=blatyrpg-realtime`,
- `WS_TICKET_MAX_LIFETIME_SEC=60`, `WS_CLOCK_TOLERANCE_SEC=2`,
- `WS_MAX_PAYLOAD_BYTES=65536`,
- `WS_AUTH_TIMEOUT_MS=5000`,
- `WS_RATE_LIMIT_MESSAGES=40`, `WS_RATE_LIMIT_WINDOW_MS=10000`,
- `WS_OFFLINE_GRACE_MS=5000`, `WS_HEARTBEAT_INTERVAL_MS=25000`.

Missing origins and wildcard origins are rejected by default. Set
`WS_ALLOW_MISSING_ORIGIN=true` only for a controlled non-browser client.

## Commands

```sh
npm ci
npm test
npm start
```

The health endpoint exposes only service status, protocol version, and total
socket count; it never returns identities or campaign membership.

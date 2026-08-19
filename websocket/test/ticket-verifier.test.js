import assert from "node:assert/strict";
import test from "node:test";
import { ReplayCache } from "../src/replay-cache.js";
import { TicketError, TicketVerifier } from "../src/ticket-verifier.js";
import { TEST_SECRET, signTicket, testConfig } from "./helpers.js";

const NOW = 2_000_000_000;

const verifier = () =>
  new TicketVerifier(testConfig(), {
    now: () => NOW,
    replayCache: new ReplayCache({ now: () => NOW * 1000 }),
  });

const rejects = (operation, code) =>
  assert.throws(operation, (error) => error instanceof TicketError && error.code === code);

test("accepts a valid short-lived HS256 ticket and normalizes authoritative identity", () => {
  const identity = verifier().verify(
    signTicket(
      {
        iat: NOW,
        exp: NOW + 30,
        sub: "42",
        campaign_id: 9,
        client_instance_id: "browser-instance-42",
      },
      { now: NOW },
    ),
    { clientInstanceId: "browser-instance-42" },
  );

  assert.equal(identity.userId, 42);
  assert.equal(identity.campaignId, 9);
  assert.equal(identity.clientInstanceId, "browser-instance-42");
  assert.equal(identity.expiresAt, (NOW + 30) * 1000);
});

test("rejects a replayed jti", () => {
  const subject = verifier();
  const ticket = signTicket(
    {
      iat: NOW,
      exp: NOW + 30,
      client_instance_id: "browser-instance-01",
    },
    { now: NOW },
  );
  subject.verify(ticket, { clientInstanceId: "browser-instance-01" });
  rejects(
    () => subject.verify(ticket, { clientInstanceId: "browser-instance-01" }),
    "ticket_replayed",
  );
});

test("rejects an invalid signature and algorithm", () => {
  rejects(
    () =>
      verifier().verify(signTicket({ iat: NOW, exp: NOW + 30 }, { now: NOW, secret: `${TEST_SECRET}x` }), {
        clientInstanceId: "browser-instance-01",
      }),
    "ticket_signature_invalid",
  );
  rejects(
    () =>
      verifier().verify(
        signTicket({ iat: NOW, exp: NOW + 30 }, { now: NOW, header: { alg: "none" } }),
        { clientInstanceId: "browser-instance-01" },
      ),
    "ticket_algorithm_invalid",
  );
});

test("rejects wrong issuer, audience and client binding", () => {
  rejects(
    () =>
      verifier().verify(signTicket({ iss: "Other", iat: NOW, exp: NOW + 30 }, { now: NOW }), {
        clientInstanceId: "browser-instance-01",
      }),
    "ticket_issuer_invalid",
  );
  rejects(
    () =>
      verifier().verify(signTicket({ aud: "other", iat: NOW, exp: NOW + 30 }, { now: NOW }), {
        clientInstanceId: "browser-instance-01",
      }),
    "ticket_audience_invalid",
  );
  rejects(
    () =>
      verifier().verify(
        signTicket(
          {
            client_instance_id: undefined,
            iat: NOW,
            exp: NOW + 30,
          },
          { now: NOW },
        ),
        { clientInstanceId: "client-instance-0001" },
      ),
    "ticket_client_instance_invalid",
  );
  rejects(
    () =>
      verifier().verify(
        signTicket(
          { client_instance_id: "browser-instance-02", iat: NOW, exp: NOW + 30 },
          { now: NOW },
        ),
        { clientInstanceId: "browser-instance-01" },
      ),
    "ticket_client_instance_mismatch",
  );
});

test("rejects expired, future and overlong tickets", () => {
  rejects(
    () =>
      verifier().verify(signTicket({ iat: NOW - 30, exp: NOW }, { now: NOW }), {
        clientInstanceId: "browser-instance-01",
      }),
    "ticket_expired",
  );
  rejects(
    () =>
      verifier().verify(signTicket({ iat: NOW + 10, exp: NOW + 20 }, { now: NOW }), {
        clientInstanceId: "browser-instance-01",
      }),
    "ticket_not_yet_valid",
  );
  rejects(
    () =>
      verifier().verify(signTicket({ iat: NOW, exp: NOW + 61 }, { now: NOW }), {
        clientInstanceId: "browser-instance-01",
      }),
    "ticket_lifetime_invalid",
  );
});

import { jsonApiClient } from "@/lib/api/jsonApiClient";

const plansFrom = (payload) => {
  if (Array.isArray(payload?.plans)) return payload.plans;
  if (Array.isArray(payload?.data?.plans)) return payload.data.plans;
  return [];
};

const code = (value) => {
  const normalized = String(value || "").trim();
  return /^[A-Za-z][A-Za-z0-9_-]{0,49}$/.test(normalized) ? normalized : "";
};

const limits = (source) =>
  Object.fromEntries(
    Object.entries(source && typeof source === "object" ? source : {})
      .map(([key, value]) => [code(key), value === null ? null : Number(value)])
      .filter(
        ([key, value]) =>
          key && (value === null || (Number.isFinite(value) && value >= 0)),
      ),
  );

export const normalizeSubscriptionPlan = (source = {}) => ({
  code: code(source.code),
  price: {
    amountMinor: Math.max(0, Number(source.price?.amountMinor) || 0),
    currency: code(source.price?.currency) || "PLN",
    interval: code(source.price?.interval) || "month",
  },
  limits: limits(source.limits),
  features: Array.isArray(source.features)
    ? source.features.map(code).filter(Boolean)
    : [],
  highlighted: source.highlighted === true,
});

export const createSubscriptionPlanApiClient = (client = jsonApiClient) => ({
  async list(options = {}) {
    const payload = await client.request("/public/subscription-plans", options);
    return plansFrom(payload)
      .map(normalizeSubscriptionPlan)
      .filter((plan) => plan.code);
  },
});

export const subscriptionPlanApiClient = createSubscriptionPlanApiClient();

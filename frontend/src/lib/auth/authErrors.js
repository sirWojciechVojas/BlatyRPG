export const authErrorKey = (error, fallback = "auth.errors.generic") => {
  if (error?.network) return "auth.errors.network";
  if (error?.status === 401) return "auth.errors.invalidCredentials";
  if (error?.status === 403) return "auth.errors.forbidden";
  if (error?.status === 409) return "auth.errors.conflict";
  if (error?.status === 422) return "auth.errors.validation";
  if (error?.status === 429) return "auth.errors.rateLimited";
  return fallback;
};

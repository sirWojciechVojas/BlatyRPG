const CREATE_FIELDS = ["username", "email", "password", "role"];

export const validateAdminUserDraft = (
  draft = {},
  message = (field) => field,
) => {
  const errors = {};
  const username = String(draft.username || "").trim();
  const email = String(draft.email || "").trim();
  const password = String(draft.password || "");
  if (username.length < 3 || username.length > 100) {
    errors.username = message("username");
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/u.test(email)) {
    errors.email = message("email");
  }
  if (
    password.length < 12 ||
    password.length > 200 ||
    !/[a-z]/u.test(password) ||
    !/[A-Z]/u.test(password) ||
    !/[0-9]/u.test(password)
  ) {
    errors.password = message("password");
  }
  if (!["user", "admin"].includes(draft.role)) {
    errors.role = message("role");
  }
  return errors;
};

export const resolveAdminUserApiFieldErrors = (
  details,
  message = (field) => field,
) => {
  if (!details || typeof details !== "object" || Array.isArray(details)) {
    return {};
  }
  return CREATE_FIELDS.reduce((errors, field) => {
    if (!details[field]) return errors;
    const duplicate = /already|zajęt/iu.test(String(details[field]));
    errors[field] = message(duplicate ? `${field}Taken` : field);
    return errors;
  }, {});
};

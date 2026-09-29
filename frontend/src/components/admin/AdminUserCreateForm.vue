<template>
  <form class="admin-create-form" novalidate @submit.prevent="submit">
    <h2>{{ $t("admin.create.title") }}</h2>
    <p>{{ $t("admin.create.description") }}</p>
    <div class="admin-form-grid">
      <label>
        <span>{{ $t("admin.fields.username") }}</span>
        <input
          v-model.trim="draft.username"
          :class="{ 'is-invalid': displayErrors.username }"
          :aria-invalid="Boolean(displayErrors.username)"
          :aria-describedby="
            displayErrors.username ? 'admin-create-username-error' : undefined
          "
          required
          minlength="3"
          maxlength="100"
          @input="clearField('username')"
        />
        <small
          v-if="displayErrors.username"
          id="admin-create-username-error"
          class="admin-field-error"
          >{{ displayErrors.username }}</small
        >
      </label>
      <label>
        <span>{{ $t("admin.fields.email") }}</span>
        <input
          v-model.trim="draft.email"
          :class="{ 'is-invalid': displayErrors.email }"
          :aria-invalid="Boolean(displayErrors.email)"
          :aria-describedby="
            displayErrors.email ? 'admin-create-email-error' : undefined
          "
          required
          type="email"
          maxlength="255"
          @input="clearField('email')"
        />
        <small
          v-if="displayErrors.email"
          id="admin-create-email-error"
          class="admin-field-error"
          >{{ displayErrors.email }}</small
        >
      </label>
      <label>
        <span>{{ $t("admin.fields.password") }}</span>
        <input
          v-model="draft.password"
          :class="{ 'is-invalid': displayErrors.password }"
          :aria-invalid="Boolean(displayErrors.password)"
          aria-describedby="admin-create-password-help"
          required
          type="password"
          minlength="12"
          maxlength="200"
          @input="clearField('password')"
        />
        <small
          id="admin-create-password-help"
          :class="
            displayErrors.password ? 'admin-field-error' : 'admin-field-hint'
          "
          >{{
            displayErrors.password || $t("admin.create.passwordHint")
          }}</small
        >
      </label>
      <label>
        <span>{{ $t("admin.fields.role") }}</span>
        <select
          v-model="draft.role"
          :class="{ 'is-invalid': displayErrors.role }"
          :aria-invalid="Boolean(displayErrors.role)"
          :aria-describedby="
            displayErrors.role ? 'admin-create-role-error' : undefined
          "
          @change="clearField('role')"
        >
          <option v-for="role in roles" :key="role" :value="role">
            {{ $t(`admin.roles.${role}`) }}
          </option>
        </select>
        <small
          v-if="displayErrors.role"
          id="admin-create-role-error"
          class="admin-field-error"
          >{{ displayErrors.role }}</small
        >
      </label>
    </div>
    <p v-if="error" class="admin-alert error" role="alert">{{ error }}</p>
    <button class="admin-primary" type="submit" :disabled="busy">
      {{ busy ? $t("admin.actions.saving") : $t("admin.actions.createUser") }}
    </button>
  </form>
</template>

<script>
import { validateAdminUserDraft } from "@/lib/admin/adminUserValidation";

const emptyDraft = () => ({
  username: "",
  email: "",
  password: "",
  role: "user",
});

export default {
  name: "AdminUserCreateForm",
  props: {
    busy: Boolean,
    error: { type: String, default: "" },
    fieldErrors: { type: Object, default: () => ({}) },
  },
  emits: ["submit", "field-change"],
  data: () => ({
    draft: emptyDraft(),
    roles: ["user", "admin"],
    localErrors: {},
  }),
  computed: {
    displayErrors() {
      return { ...this.fieldErrors, ...this.localErrors };
    },
  },
  methods: {
    submit() {
      this.localErrors = this.validate();
      if (Object.keys(this.localErrors).length) return;
      this.$emit("submit", { ...this.draft });
    },
    validate() {
      return validateAdminUserDraft(this.draft, (field) =>
        this.$t(`admin.errors.fields.${field}`),
      );
    },
    clearField(field) {
      if (this.localErrors[field]) {
        const remaining = { ...this.localErrors };
        delete remaining[field];
        this.localErrors = remaining;
      }
      this.$emit("field-change", field);
    },
    reset() {
      this.draft = emptyDraft();
      this.localErrors = {};
    },
  },
};
</script>

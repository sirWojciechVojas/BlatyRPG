<template>
  <form class="admin-create-form" @submit.prevent="submit">
    <h2>{{ $t("admin.create.title") }}</h2>
    <p>{{ $t("admin.create.description") }}</p>
    <div class="admin-form-grid">
      <label>
        <span>{{ $t("admin.fields.username") }}</span>
        <input
          v-model.trim="draft.username"
          required
          minlength="3"
          maxlength="100"
        />
      </label>
      <label>
        <span>{{ $t("admin.fields.email") }}</span>
        <input
          v-model.trim="draft.email"
          required
          type="email"
          maxlength="255"
        />
      </label>
      <label>
        <span>{{ $t("admin.fields.password") }}</span>
        <input
          v-model="draft.password"
          required
          type="password"
          minlength="12"
          maxlength="200"
        />
      </label>
      <label>
        <span>{{ $t("admin.fields.role") }}</span>
        <select v-model="draft.role">
          <option v-for="role in roles" :key="role" :value="role">
            {{ $t(`admin.roles.${role}`) }}
          </option>
        </select>
      </label>
    </div>
    <p v-if="error" class="admin-alert error" role="alert">{{ error }}</p>
    <button class="admin-primary" type="submit" :disabled="busy">
      {{ busy ? $t("admin.actions.saving") : $t("admin.actions.createUser") }}
    </button>
  </form>
</template>

<script>
const emptyDraft = () => ({
  username: "",
  email: "",
  password: "",
  role: "user",
});

export default {
  name: "AdminUserCreateForm",
  props: { busy: Boolean, error: { type: String, default: "" } },
  emits: ["submit"],
  data: () => ({ draft: emptyDraft(), roles: ["user", "gm", "admin"] }),
  methods: {
    submit() {
      this.$emit("submit", { ...this.draft });
    },
    reset() {
      this.draft = emptyDraft();
    },
  },
};
</script>

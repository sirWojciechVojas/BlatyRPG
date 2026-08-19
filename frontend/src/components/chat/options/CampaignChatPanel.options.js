import { campaignChatApiClient } from "@/lib/chat/campaignChatApiClient";
import {
  createCampaignChatRequestScope,
  createPendingChatMessage,
} from "@/lib/chat/campaignChatRequestScope";
import {
  chatCursor,
  isNearChatBottom,
  mergeChatMessages,
} from "@/lib/chat/campaignChatState";
import { campaignChatText } from "@/lib/chat/campaignChatText";

const MAX_LENGTH = 2000;

const createNonce = () => {
  if (window.crypto?.randomUUID) return window.crypto.randomUUID();
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (token) => {
    const random = Math.floor(Math.random() * 16);
    const value = token === "x" ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
};

export default {
  name: "CampaignChatPanel",
  props: {
    campaignId: { type: [Number, String], required: true },
    apiClient: { type: Object, default: () => campaignChatApiClient },
    pollIntervalMs: {
      type: Number,
      default: 4000,
      validator: (value) => value >= 2000,
    },
    initiallyCollapsed: { type: Boolean, default: false },
  },
  emits: ["unauthorized", "message"],
  data() {
    return {
      messages: [],
      capabilities: { canRead: false, canSend: false, canModerate: false },
      draft: "",
      collapsed: this.initiallyCollapsed,
      loading: false,
      loadingOlder: false,
      polling: false,
      sending: false,
      hasMoreBefore: false,
      error: null,
      timerId: null,
      requestScope: createCampaignChatRequestScope(),
      pendingMessage: createPendingChatMessage(createNonce),
    };
  },
  computed: {
    maxLength: () => MAX_LENGTH,
    locale() {
      const locale = this.$i18n?.locale;
      return typeof locale === "string" ? locale : locale?.value || "pl";
    },
    errorText() {
      if (!this.error) return "";
      const key = this.error.network
        ? "network"
        : ["forbidden", "rate_limited", "validation_failed"].includes(
              this.error.code,
            )
          ? this.error.code
          : "generic";
      return this.text(`errors.${key}`);
    },
    syncLabel() {
      return this.text("polling", {
        seconds: Math.round(this.pollIntervalMs / 1000),
      });
    },
  },
  watch: {
    campaignId(next, previous) {
      if (String(next) === String(previous)) return;
      this.reset();
      if (this.requestScope.isActive()) {
        this.initialize();
        this.schedulePolling();
      }
    },
  },
  mounted() {
    this.requestScope.mount();
    document.addEventListener("visibilitychange", this.onVisibilityChange);
    this.schedulePolling();
    this.initialize();
  },
  beforeUnmount() {
    this.requestScope.unmount();
    this.stopPolling();
    document.removeEventListener("visibilitychange", this.onVisibilityChange);
  },
  methods: {
    text(key, variables) {
      return campaignChatText(this.locale, key, variables);
    },
    reset() {
      this.requestScope.reset();
      this.pendingMessage.clear();
      this.draft = "";
      this.messages = [];
      this.capabilities = {
        canRead: false,
        canSend: false,
        canModerate: false,
      };
      this.hasMoreBefore = false;
      this.error = null;
      this.loading = false;
      this.loadingOlder = false;
      this.polling = false;
      this.sending = false;
    },
    isCurrent(context) {
      return this.requestScope.isCurrent(context, this.campaignId);
    },
    async initialize() {
      if (this.loading) return;
      const context = this.requestScope.capture(this.campaignId);
      this.loading = true;
      this.error = null;
      try {
        const response = await this.apiClient.list(context.campaignId, {
          limit: 50,
        });
        if (!this.isCurrent(context)) return;
        this.messages = mergeChatMessages([], response.items);
        this.capabilities = response.capabilities;
        this.hasMoreBefore = response.pagination.hasMoreBefore;
        await this.$nextTick();
        this.scrollToBottom();
      } catch (error) {
        if (this.isCurrent(context)) this.handleError(error);
      } finally {
        if (this.isCurrent(context)) this.loading = false;
      }
    },
    schedulePolling() {
      this.stopPolling();
      if (this.requestScope.isActive()) {
        this.timerId = window.setInterval(
          () => this.poll(),
          this.pollIntervalMs,
        );
      }
    },
    stopPolling() {
      if (this.timerId !== null) window.clearInterval(this.timerId);
      this.timerId = null;
    },
    onVisibilityChange() {
      if (document.visibilityState === "visible") this.poll();
    },
    async refresh() {
      if (!this.messages.length) return this.initialize();
      return this.poll();
    },
    async poll() {
      if (this.polling || this.loading || document.visibilityState === "hidden")
        return;
      const context = this.requestScope.capture(this.campaignId);
      this.polling = true;
      const list = this.$refs.messageList;
      const shouldFollow = isNearChatBottom(list);
      try {
        const response = await this.apiClient.list(context.campaignId, {
          afterId: chatCursor(this.messages),
          limit: 100,
        });
        if (!this.isCurrent(context)) return;
        this.capabilities = response.capabilities;
        this.acceptMessages(response.items);
        this.error = null;
        if (shouldFollow && response.items.length) {
          await this.$nextTick();
          this.scrollToBottom();
        }
      } catch (error) {
        if (this.isCurrent(context)) this.handleError(error);
      } finally {
        if (this.isCurrent(context)) this.polling = false;
      }
    },
    async loadOlder() {
      if (this.loadingOlder) return;
      const context = this.requestScope.capture(this.campaignId);
      this.loadingOlder = true;
      const list = this.$refs.messageList;
      const previousHeight = list?.scrollHeight || 0;
      try {
        const response = await this.apiClient.list(context.campaignId, {
          beforeId: chatCursor(this.messages, "before"),
          limit: 50,
        });
        if (!this.isCurrent(context)) return;
        this.acceptMessages(response.items);
        this.hasMoreBefore = response.pagination.hasMoreBefore;
        this.capabilities = response.capabilities;
        this.error = null;
        await this.$nextTick();
        if (list) list.scrollTop += list.scrollHeight - previousHeight;
      } catch (error) {
        if (this.isCurrent(context)) this.handleError(error);
      } finally {
        if (this.isCurrent(context)) this.loadingOlder = false;
      }
    },
    async sendMessage() {
      const body = this.draft.trim();
      if (!body || this.sending || !this.capabilities.canSend) return;
      const context = this.requestScope.capture(this.campaignId);
      const nonce = this.pendingMessage.nonceFor(body);
      this.sending = true;
      this.error = null;
      try {
        const result = await this.apiClient.send(
          context.campaignId,
          body,
          nonce,
        );
        if (!this.isCurrent(context)) return;
        this.capabilities = result.capabilities;
        this.acceptMessages([result.message]);
        this.pendingMessage.clear();
        this.draft = "";
        await this.$nextTick();
        this.scrollToBottom();
      } catch (error) {
        if (this.isCurrent(context)) {
          if (error?.code === "nonce_conflict") this.pendingMessage.clear();
          this.handleError(error);
        }
      } finally {
        if (this.isCurrent(context)) this.sending = false;
      }
    },
    acceptMessages(messages) {
      const campaignId = Number(this.campaignId);
      const scoped = messages.filter(
        (message) => Number(message?.campaignId) === campaignId,
      );
      this.messages = mergeChatMessages(this.messages, scoped);
      for (const message of scoped) this.$emit("message", message);
    },
    acceptRealtimeMessage(message) {
      this.acceptMessages([message]);
    },
    handleError(error) {
      this.error = error || { code: "generic" };
      if ([401, 403].includes(Number(error?.status))) {
        this.stopPolling();
        this.capabilities.canSend = false;
        this.$emit("unauthorized", error);
      }
    },
    scrollToBottom() {
      const list = this.$refs.messageList;
      if (list) list.scrollTop = list.scrollHeight;
    },
    formatTime(value) {
      if (!value) return "";
      const date = new Date(String(value).replace(" ", "T"));
      if (Number.isNaN(date.getTime())) return String(value);
      return new Intl.DateTimeFormat(this.locale, {
        hour: "2-digit",
        minute: "2-digit",
      }).format(date);
    },
  },
};

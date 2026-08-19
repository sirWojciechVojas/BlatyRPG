import { authSession } from "@/lib/auth/authSession";
import { isNearChatBottom } from "@/lib/chat/campaignChatState";
import { campaignChatText } from "@/lib/chat/campaignChatText";
import { withCurrentChatUser } from "@/lib/chat/realtimeChatMessage";

const MAX_LENGTH = 2000;
const emptyChat = () => ({
  messages: [],
  capabilities: { canRead: false, canSend: false, canModerate: false },
  initialized: false,
  syncing: false,
  loadingOlder: false,
  sending: false,
  hasMoreBefore: false,
  lastAckNonce: null,
  error: null,
});

export default {
  name: "CampaignChatPanel",
  props: {
    campaignId: { type: [Number, String], required: true },
    initiallyCollapsed: { type: Boolean, default: false },
    embedded: { type: Boolean, default: false },
  },
  emits: ["unauthorized", "message"],
  data() {
    return {
      draft: "",
      collapsed: this.embedded ? false : this.initiallyCollapsed,
      submittedNonce: null,
      olderScrollHeight: 0,
    };
  },
  computed: {
    maxLength: () => MAX_LENGTH,
    realtime() {
      return this.$store.state.realtime || {};
    },
    chat() {
      return this.realtime.chat || emptyChat();
    },
    currentUserId() {
      return Number(authSession.read()?.user?.id) || null;
    },
    messages() {
      return this.chat.messages.map((message) =>
        withCurrentChatUser(message, this.currentUserId),
      );
    },
    capabilities() {
      return this.chat.capabilities;
    },
    loading() {
      return !this.chat.initialized && this.chat.syncing;
    },
    loadingOlder() {
      return this.chat.loadingOlder;
    },
    syncing() {
      return this.chat.syncing;
    },
    sending() {
      return this.chat.sending;
    },
    hasMoreBefore() {
      return this.chat.hasMoreBefore;
    },
    error() {
      return this.chat.error;
    },
    chatAck() {
      return this.chat.lastAckNonce;
    },
    locale() {
      const locale = this.$i18n?.locale;
      return typeof locale === "string" ? locale : locale?.value || "pl";
    },
    errorText() {
      if (!this.error) return "";
      const key = this.error.network
        ? "network"
        : [
              "forbidden",
              "rate_limited",
              "validation_failed",
              "offline",
            ].includes(this.error.code)
          ? this.error.code
          : "generic";
      return this.text(`errors.${key}`);
    },
    syncLabel() {
      if (this.chat.syncing) return this.text("syncing");
      if (
        ["reconnecting", "ticketing", "connecting"].includes(
          this.realtime.status,
        )
      ) {
        return this.text("reconnecting");
      }
      return this.realtime.status === "ready"
        ? this.text("live")
        : this.text("offline");
    },
  },
  watch: {
    campaignId(next, previous) {
      if (String(next) === String(previous)) return;
      this.draft = "";
      this.submittedNonce = null;
      this.ensureSync();
    },
    chatAck(next) {
      if (!next || next !== this.submittedNonce) return;
      this.draft = "";
      this.submittedNonce = null;
      this.$nextTick(this.scrollToBottom);
    },
    error(error) {
      if ([401, 403].includes(Number(error?.status))) {
        this.$emit("unauthorized", error);
      }
    },
    "messages.length"() {
      const follow = isNearChatBottom(this.$refs.messageList);
      if (follow) this.$nextTick(this.scrollToBottom);
    },
    loadingOlder(next, previous) {
      if (previous && !next) {
        this.$nextTick(() => {
          const list = this.$refs.messageList;
          if (list)
            list.scrollTop += list.scrollHeight - this.olderScrollHeight;
        });
      }
    },
  },
  mounted() {
    this.ensureSync();
  },
  methods: {
    text(key, variables) {
      return campaignChatText(this.locale, key, variables);
    },
    ensureSync() {
      if (!this.realtime.chat || this.chat.syncing || this.chat.initialized)
        return;
      this.$store.dispatch("realtime/syncChat");
    },
    refresh() {
      return this.$store.dispatch("realtime/syncChat");
    },
    loadOlder() {
      this.olderScrollHeight = this.$refs.messageList?.scrollHeight || 0;
      return this.$store.dispatch("realtime/loadOlderChat");
    },
    async sendMessage() {
      const body = this.draft.trim();
      if (!body || this.sending || !this.capabilities.canSend) return;
      this.submittedNonce = await this.$store.dispatch(
        "realtime/sendChatMessage",
        body,
      );
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

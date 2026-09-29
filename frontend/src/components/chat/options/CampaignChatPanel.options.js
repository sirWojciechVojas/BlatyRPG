import { authSession } from "@/lib/auth/authSession";
import { campaignChatText } from "@/lib/chat/campaignChatText";
import { withCurrentChatUser } from "@/lib/chat/realtimeChatMessage";
import {
  parseDiceRollMessage,
  presentDiceRollMessage,
} from "@/lib/chat/diceRollMessage";
import { parseMagicCastMessage } from "@/lib/chat/magicCastMessage";

const MAX_LENGTH = 2000;
const authorInitials = (name) => {
  const parts = String(name || "")
    .trim()
    .split(/\s+/)
    .filter(Boolean);
  if (!parts.length) return "?";
  return parts
    .slice(0, 2)
    .map((part) => Array.from(part)[0] || "")
    .join("")
    .toLocaleUpperCase();
};
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
    members: { type: Array, default: () => [] },
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
      scrollPending: false,
      scrollBehavior: "auto",
      scrollFrame: null,
      messageListObserver: null,
      newMessageId: null,
      newMessageTimer: null,
      chatReadyObserved: false,
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
    authorAvatarById() {
      return new Map(
        this.members
          .map((member) => [
            Number(member.userId ?? member.user_id),
            String(member.avatarUrl ?? member.avatar_url ?? "").trim(),
          ])
          .filter(([userId, avatarUrl]) => userId > 0 && avatarUrl),
      );
    },
    messages() {
      return this.chat.messages
        .map((message) => {
          const normalized = withCurrentChatUser(message, this.currentUserId);
          const magicCast = parseMagicCastMessage(normalized.body);
          return {
            ...normalized,
            author: {
              ...normalized.author,
              initials: authorInitials(normalized.author?.name),
              avatarUrl:
                normalized.author?.avatarUrl ||
                this.authorAvatarById?.get(Number(normalized.author?.id)) ||
                "",
            },
            magicCast,
            diceRoll: magicCast
              ? null
              : presentDiceRollMessage(parseDiceRollMessage(normalized.body)),
          };
        })
        .filter((message) => String(message.body || "").trim());
    },
    latestMessageKey() {
      const last = this.messages[this.messages.length - 1];
      if (!last) return "";
      return `${this.campaignId}:${last.id || last.revision || "latest"}`;
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
      this.chatReadyObserved = false;
      this.clearNewMessageHighlight();
      this.ensureSync();
    },
    chatAck(next) {
      if (!next || next !== this.submittedNonce) return;
      this.draft = "";
      this.submittedNonce = null;
      this.requestScrollToBottom();
    },
    error(error) {
      if ([401, 403].includes(Number(error?.status))) {
        this.$emit("unauthorized", error);
      }
    },
    latestMessageKey(next, previous) {
      if (!next || next === previous) return;
      const nextScope = next.slice(0, next.lastIndexOf(":"));
      const previousScope = previous.slice(0, previous.lastIndexOf(":"));
      const appended =
        (Boolean(previous) && nextScope === previousScope) ||
        (!previous && this.chatReadyObserved);
      if (appended) {
        const latest = this.messages[this.messages.length - 1];
        this.markNewMessage(latest?.id);
      }
      this.requestScrollToBottom({ behavior: appended ? "smooth" : "auto" });
    },
    "chat.initialized"(next) {
      if (!next) {
        this.chatReadyObserved = false;
        return;
      }
      this.$nextTick(() => {
        if (this.chat.initialized) this.chatReadyObserved = true;
      });
    },
    collapsed(next) {
      if (next) return;
      this.$nextTick(() => {
        this.observeMessageList();
        this.requestScrollToBottom();
      });
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
    this.chatReadyObserved = this.chat.initialized === true;
    this.ensureSync();
    this.observeMessageList();
    this.requestScrollToBottom();
  },
  beforeUnmount() {
    this.messageListObserver?.disconnect();
    if (this.scrollFrame !== null && typeof window !== "undefined") {
      window.cancelAnimationFrame?.(this.scrollFrame);
    }
    this.clearNewMessageHighlight();
  },
  methods: {
    text(key, variables) {
      return campaignChatText(this.locale, key, variables);
    },
    magicSeverity(severity) {
      return this.text(`magic.severity.${severity}`);
    },
    clearNewMessageHighlight() {
      if (this.newMessageTimer !== null && typeof window !== "undefined") {
        window.clearTimeout(this.newMessageTimer);
      }
      this.newMessageTimer = null;
      this.newMessageId = null;
    },
    markNewMessage(messageId) {
      this.clearNewMessageHighlight();
      if (!messageId) return;
      this.newMessageId = messageId;
      if (typeof window === "undefined") return;
      this.newMessageTimer = window.setTimeout(() => {
        this.newMessageId = null;
        this.newMessageTimer = null;
      }, 3200);
    },
    ensureSync() {
      if (!this.realtime.chat || this.chat.syncing || this.chat.initialized)
        return;
      this.$store.dispatch("realtime/syncChat");
    },
    refresh() {
      if (this.realtime.status !== "ready") {
        return this.$store.dispatch("realtime/retry");
      }
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
    requestScrollToBottom({ behavior = "auto" } = {}) {
      if (behavior === "smooth" || !this.scrollPending) {
        this.scrollBehavior = behavior;
      }
      this.scrollPending = true;
      this.$nextTick(() => {
        if (this.scrollFrame !== null && typeof window !== "undefined") {
          window.cancelAnimationFrame?.(this.scrollFrame);
        }
        const scroll = () => {
          this.scrollFrame = null;
          this.scrollToBottom();
        };
        if (
          typeof window !== "undefined" &&
          typeof window.requestAnimationFrame === "function"
        ) {
          this.scrollFrame = window.requestAnimationFrame(scroll);
        } else {
          scroll();
        }
      });
    },
    observeMessageList() {
      this.messageListObserver?.disconnect();
      this.messageListObserver = null;
      const list = this.$refs.messageList;
      if (!list || typeof ResizeObserver === "undefined") return;
      this.messageListObserver = new ResizeObserver(() => {
        if (this.scrollPending) this.requestScrollToBottom();
      });
      this.messageListObserver.observe(list);
    },
    scrollToBottom() {
      const list = this.$refs.messageList;
      if (!list || list.clientHeight <= 0) return false;
      const reduceMotion =
        typeof window !== "undefined" &&
        window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
      const behavior =
        this.scrollBehavior === "smooth" && !reduceMotion ? "smooth" : "auto";
      if (typeof list.scrollTo === "function") {
        list.scrollTo({ top: list.scrollHeight, behavior });
      } else {
        list.scrollTop = list.scrollHeight;
      }
      this.scrollPending = false;
      this.scrollBehavior = "auto";
      return true;
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

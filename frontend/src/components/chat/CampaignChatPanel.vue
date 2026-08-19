<template>
  <aside
    class="campaign-chat"
    :class="{ 'campaign-chat--collapsed': collapsed }"
  >
    <header class="campaign-chat__header">
      <div>
        <h2>{{ text("title") }}</h2>
        <small v-if="!collapsed">{{ syncLabel }}</small>
      </div>
      <div class="campaign-chat__header-actions">
        <button
          v-if="!collapsed"
          type="button"
          :title="text('refresh')"
          :aria-label="text('refresh')"
          :disabled="loading || polling"
          @click="refresh"
        >
          ↻
        </button>
        <button
          type="button"
          :title="text(collapsed ? 'expand' : 'collapse')"
          :aria-label="text(collapsed ? 'expand' : 'collapse')"
          @click="collapsed = !collapsed"
        >
          {{ collapsed ? "💬" : "×" }}
        </button>
      </div>
    </header>

    <template v-if="!collapsed">
      <p v-if="errorText" class="campaign-chat__error" role="alert">
        {{ errorText }}
      </p>

      <section
        ref="messageList"
        class="campaign-chat__messages"
        role="log"
        aria-live="polite"
        :aria-label="text('title')"
      >
        <button
          v-if="hasMoreBefore"
          class="campaign-chat__older"
          type="button"
          :disabled="loadingOlder"
          @click="loadOlder"
        >
          {{ text("older") }}
        </button>
        <p v-if="loading && !messages.length" class="campaign-chat__state">
          {{ text("loading") }}
        </p>
        <p v-else-if="!messages.length" class="campaign-chat__state">
          {{ text("empty") }}
        </p>
        <article
          v-for="message in messages"
          :key="message.id"
          class="campaign-chat__message"
          :class="{
            'campaign-chat__message--own': message.author.isCurrentUser,
          }"
        >
          <header>
            <strong>{{ message.author.name }}</strong>
            <time :datetime="message.createdAt || undefined">
              {{ formatTime(message.createdAt) }}
            </time>
          </header>
          <p>{{ message.body }}</p>
        </article>
      </section>

      <form
        v-if="capabilities.canSend"
        class="campaign-chat__composer"
        @submit.prevent="sendMessage"
      >
        <textarea
          v-model="draft"
          rows="2"
          :maxlength="maxLength"
          :placeholder="text('placeholder')"
          :disabled="sending"
          @keydown.enter.exact.prevent="sendMessage"
        />
        <div class="campaign-chat__composer-footer">
          <small>{{
            text("counter", { count: draft.length, max: maxLength })
          }}</small>
          <button type="submit" :disabled="sending || !draft.trim()">
            {{ text(sending ? "sending" : "send") }}
          </button>
        </div>
      </form>
      <p v-else class="campaign-chat__readonly">{{ text("readOnly") }}</p>
    </template>
  </aside>
</template>

<script src="./options/CampaignChatPanel.options.js"></script>

<style scoped src="./styles/CampaignChatPanel.css"></style>

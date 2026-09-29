<template>
  <aside
    class="campaign-chat"
    :class="{
      'campaign-chat--collapsed': collapsed,
      'campaign-chat--embedded': embedded,
    }"
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
          :disabled="loading || syncing"
          @click="refresh"
        >
          ↻
        </button>
        <button
          v-if="!embedded"
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
      <div v-if="errorText" class="campaign-chat__error" role="alert">
        <span>{{ errorText }}</span>
        <button type="button" :disabled="loading || syncing" @click="refresh">
          {{ text("retry") }}
        </button>
      </div>

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
            'campaign-chat__message--new': message.id === newMessageId,
            'campaign-chat__message--roll': Boolean(message.diceRoll),
            'campaign-chat__message--magic': Boolean(message.magicCast),
          }"
        >
          <header>
            <span class="campaign-chat__author">
              <span
                class="campaign-chat__avatar"
                :title="message.author.name"
                aria-hidden="true"
              >
                <span>{{ message.author.initials }}</span>
                <img
                  v-if="message.author.avatarUrl"
                  :src="message.author.avatarUrl"
                  alt=""
                  @error="$event.currentTarget.hidden = true"
                />
              </span>
              <strong>{{ message.author.name }}</strong>
              <span
                v-if="message.id === newMessageId"
                class="campaign-chat__new-badge"
              >
                {{ text("newMessage") }}
              </span>
            </span>
            <time :datetime="message.createdAt || undefined">
              {{ formatTime(message.createdAt) }}
            </time>
          </header>
          <div
            v-if="message.magicCast"
            class="campaign-chat__magic"
            :class="{
              'campaign-chat__magic--success': message.magicCast.succeeded,
              'campaign-chat__magic--failure': !message.magicCast.succeeded,
            }"
          >
            <div class="campaign-chat__magic-heading">
              <span>{{ text("magic.flavor") }}</span>
              <strong>{{ message.magicCast.spell }}</strong>
              <small>
                {{ message.magicCast.character
                }}<template v-if="message.magicCast.tradition">
                  · {{ message.magicCast.tradition }}</template
                ><template v-if="message.magicCast.magicType">
                  · {{ message.magicCast.magicType }}</template
                >
              </small>
            </div>
            <dl class="campaign-chat__magic-facts">
              <div>
                <dt>{{ text("magic.castingNumber") }}</dt>
                <dd>{{ message.magicCast.castingNumber }}</dd>
              </div>
              <div>
                <dt>{{ text("magic.powerDice") }}</dt>
                <dd class="campaign-chat__magic-dice">
                  <span
                    v-for="(die, index) in message.magicCast.powerDice"
                    :key="`magic-power-${message.id}-${index}`"
                    >{{ die }}</span
                  >
                </dd>
              </div>
              <div>
                <dt>{{ text("magic.chaosDice") }}</dt>
                <dd
                  v-if="message.magicCast.chaosDice.length"
                  class="campaign-chat__magic-dice campaign-chat__magic-dice--chaos"
                >
                  <span
                    v-for="(die, index) in message.magicCast.chaosDice"
                    :key="`magic-chaos-${message.id}-${index}`"
                    >{{ die }}</span
                  >
                </dd>
                <dd v-else>—</dd>
              </div>
              <div>
                <dt>{{ text("magic.modifier") }}</dt>
                <dd>
                  {{ message.magicCast.modifier >= 0 ? "+" : ""
                  }}{{ message.magicCast.modifier }}
                </dd>
              </div>
              <div>
                <dt>{{ text("magic.powerTotal") }}</dt>
                <dd>{{ message.magicCast.powerTotal }}</dd>
              </div>
              <div class="campaign-chat__magic-result">
                <dt>{{ text("magic.result") }}</dt>
                <dd>
                  {{
                    text(
                      message.magicCast.succeeded
                        ? "magic.success"
                        : "magic.failure",
                    )
                  }}
                </dd>
              </div>
              <div v-if="message.magicCast.automaticFailure">
                <dt>{{ text("magic.automaticFailureLabel") }}</dt>
                <dd>{{ text("magic.automaticFailure") }}</dd>
              </div>
              <div v-if="message.magicCast.channel">
                <dt>{{ text("magic.channel") }}</dt>
                <dd>
                  {{
                    text(
                      message.magicCast.channel.succeeded
                        ? "magic.channelSuccess"
                        : "magic.channelFailure",
                      {
                        roll: message.magicCast.channel.roll ?? "—",
                        bonus: message.magicCast.channel.bonus,
                      },
                    )
                  }}
                </dd>
              </div>
              <div v-if="message.magicCast.ingredient">
                <dt>{{ text("magic.ingredient") }}</dt>
                <dd>
                  {{ message.magicCast.ingredient.name }} ·
                  {{
                    text(
                      message.magicCast.ingredient.consumed
                        ? "magic.consumed"
                        : "magic.notConsumed",
                    )
                  }}
                  · +{{ message.magicCast.ingredient.bonus }}
                </dd>
              </div>
              <div>
                <dt>{{ text("magic.curse") }}</dt>
                <dd v-if="message.magicCast.manifestations.length">
                  <span
                    v-for="manifestation in message.magicCast.manifestations"
                    :key="`${manifestation.face}:${manifestation.matchingDice}`"
                    class="campaign-chat__magic-manifestation"
                  >
                    {{ magicSeverity(manifestation.severity) }} ·
                    {{ manifestation.matchingDice }}×
                    {{ manifestation.face }}
                  </span>
                </dd>
                <dd v-else>{{ text("magic.noCurse") }}</dd>
              </div>
              <div v-if="message.magicCast.targets.length">
                <dt>{{ text("magic.targets") }}</dt>
                <dd>{{ message.magicCast.targets.join(", ") }}</dd>
              </div>
              <div v-if="message.magicCast.defense">
                <dt>{{ text("magic.defense") }}</dt>
                <dd>{{ message.magicCast.defense }}</dd>
              </div>
              <div v-if="message.magicCast.effect">
                <dt>{{ text("magic.effect") }}</dt>
                <dd>{{ message.magicCast.effect }}</dd>
              </div>
            </dl>
          </div>
          <div v-else-if="message.diceRoll" class="campaign-chat__roll">
            <div class="campaign-chat__roll-intro">
              <span class="campaign-chat__roll-flavor">
                {{ text("roll.flavor") }}
              </span>
              <span class="campaign-chat__roll-formula">
                {{ message.diceRoll.formula }}
              </span>
            </div>
            <section
              v-for="(group, groupIndex) in message.diceRoll.groups"
              :key="`${message.id}-${group.type}-${groupIndex}`"
              class="campaign-chat__roll-group"
            >
              <strong class="campaign-chat__roll-group-label">
                {{ group.notation }}
              </strong>
              <div
                class="campaign-chat__roll-dice"
                :aria-label="text('roll.dice')"
              >
                <span
                  v-for="(die, dieIndex) in group.dice"
                  :key="`${message.id}-${groupIndex}-${dieIndex}`"
                  class="campaign-chat__roll-die"
                  :class="{
                    'campaign-chat__roll-die--maximum': die.isMaximum,
                  }"
                >
                  {{ die.value }}
                </span>
              </div>
              <output
                v-if="group.total"
                :aria-label="`${text('roll.subtotal')}: ${group.total}`"
              >
                {{ group.total }}
              </output>
            </section>
            <div
              class="campaign-chat__roll-total"
              :aria-label="`${text('roll.total')}: ${message.diceRoll.total}`"
            >
              <span>{{ text("roll.total") }}</span>
              <strong>{{ message.diceRoll.total }}</strong>
            </div>
          </div>
          <p v-else>{{ message.body }}</p>
        </article>
      </section>

      <form
        v-if="capabilities.canSend"
        class="campaign-chat__composer"
        @submit.prevent="sendMessage"
      >
        <input
          v-model="draft"
          type="text"
          :maxlength="maxLength"
          :placeholder="text('placeholder')"
          :disabled="sending"
          @keydown.enter.exact.prevent="sendMessage"
        />
        <button type="submit" :disabled="sending || !draft.trim()">
          {{ text(sending ? "sending" : "send") }}
        </button>
      </form>
      <p v-else class="campaign-chat__readonly">{{ text("readOnly") }}</p>
    </template>
  </aside>
</template>

<script src="./options/CampaignChatPanel.options.js"></script>

<style scoped src="./styles/CampaignChatPanel.css"></style>

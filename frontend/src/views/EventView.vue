<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { eventsApi, ordersApi } from '@/api'
import { errorMessage, statusOf } from '@/api/http'
import type { EventItem } from '@/api/types'
import { MAX_TICKETS, useCartStore } from '@/stores/cart'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { goToCheckout } from '@/utils/checkout'
import { dateLong, money, providerLabels, time } from '@/utils/format'

const props = defineProps<{ slug: string }>()

const auth = useAuthStore()
const cart = useCartStore()
const toast = useToastStore()
const router = useRouter()

const event = ref<EventItem | null>(null)
const providers = ref<string[]>([])
const loading = ref(true)
const notFound = ref(false)
const buying = ref(false)
const error = ref('')

const currency = computed(() => event.value?.ticket_types?.[0]?.currency ?? 'UAH')
const total = computed(() => (event.value ? cart.total(event.value) : 0))

onMounted(async () => {
  try {
    const [e, p] = await Promise.all([eventsApi.get(props.slug), ordersApi.providers()])
    event.value = e
    providers.value = p
    cart.forEvent(e)
    if (!p.includes(cart.provider)) cart.provider = p[0] ?? ''
  } catch (e) {
    if (statusOf(e) === 404 || statusOf(e) === 403) notFound.value = true
    else error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
})

async function buy() {
  if (!event.value) return
  if (!auth.isLoggedIn) {
    router.push({ name: 'login', query: { redirect: `/events/${props.slug}` } })
    return
  }
  buying.value = true
  error.value = ''
  try {
    const { order, checkout } = await ordersApi.create({
      event_id: event.value.id,
      provider: cart.provider,
      items: cart.items,
    })
    cart.clear()
    toast.info(`Seats reserved for 15 minutes. Order ${order.uuid.slice(0, 8)}.`)
    goToCheckout(checkout)
  } catch (e) {
    error.value = errorMessage(e)
    buying.value = false
    // availability may have changed under us
    event.value = await eventsApi.get(props.slug).catch(() => event.value)
  }
}
</script>

<template>
  <div class="container page">
    <div v-if="loading" class="skeleton" style="height: 420px" />

    <div v-else-if="notFound" class="empty">
      <h2>Event not found</h2>
      <RouterLink to="/" class="btn btn-ghost">Back to events</RouterLink>
    </div>

    <div v-else-if="event" class="layout">
      <article>
        <RouterLink to="/" class="back muted">← All events</RouterLink>
        <p class="eyebrow">{{ event.city }} · {{ event.venue }}</p>
        <h1>{{ event.title }}</h1>
        <div class="meta">
          <div>
            <div class="eyebrow">Date</div>
            <strong>{{ dateLong(event.starts_at) }}</strong>
          </div>
          <div>
            <div class="eyebrow">Time</div>
            <strong>
              {{ time(event.starts_at)
              }}<template v-if="event.ends_at"> – {{ time(event.ends_at) }}</template>
            </strong>
          </div>
          <div v-if="event.organizer">
            <div class="eyebrow">Organizer</div>
            <strong>{{ event.organizer.name }}</strong>
          </div>
        </div>
        <img v-if="event.cover_url" :src="event.cover_url" alt="" class="cover" />
        <p class="description">{{ event.description }}</p>
      </article>

      <aside class="card buy">
        <h2>Tickets</h2>
        <p v-if="!event.on_sale" class="alert alert-warn">
          {{
            event.status === 'cancelled' ? 'This event was cancelled.' : 'Tickets are not on sale.'
          }}
        </p>

        <ul class="types">
          <li v-for="type in event.ticket_types" :key="type.id" class="type">
            <div>
              <strong>{{ type.name }}</strong>
              <div class="muted small">
                {{ money(type.price, type.currency) }} ·
                <span v-if="type.available === 0">sold out</span>
                <span v-else-if="type.available < 20">{{ type.available }} left</span>
                <span v-else>available</span>
              </div>
            </div>
            <div class="stepper" :aria-label="`${type.name} quantity`">
              <button
                type="button"
                :disabled="!cart.quantities[type.id]"
                aria-label="Fewer"
                @click="cart.setQuantity(type.id, (cart.quantities[type.id] ?? 0) - 1)"
              >
                −
              </button>
              <span class="mono">{{ cart.quantities[type.id] ?? 0 }}</span>
              <button
                type="button"
                :disabled="
                  !event.on_sale ||
                  (cart.quantities[type.id] ?? 0) >= type.available ||
                  cart.totalCount >= MAX_TICKETS
                "
                aria-label="More"
                @click="cart.setQuantity(type.id, (cart.quantities[type.id] ?? 0) + 1)"
              >
                +
              </button>
            </div>
          </li>
        </ul>

        <fieldset v-if="providers.length > 1" class="providers">
          <legend class="eyebrow">Pay with</legend>
          <label
            v-for="p in providers"
            :key="p"
            class="provider"
            :class="{ active: cart.provider === p }"
          >
            <input v-model="cart.provider" type="radio" name="provider" :value="p" />
            {{ providerLabels[p] ?? p }}
          </label>
        </fieldset>

        <div class="total row">
          <span>Total</span>
          <span class="spacer" />
          <strong>{{ money(total, currency) }}</strong>
        </div>

        <div v-if="error" class="alert alert-error">{{ error }}</div>
        <p v-if="auth.isLoggedIn && !auth.isVerified" class="alert alert-warn small">
          Confirm your email before buying.
        </p>

        <button
          class="btn btn-accent btn-block"
          :disabled="
            !event.on_sale ||
            cart.totalCount === 0 ||
            buying ||
            (auth.isLoggedIn && !auth.isVerified)
          "
          @click="buy"
        >
          {{
            buying
              ? 'Reserving…'
              : auth.isLoggedIn
                ? `Buy ${cart.totalCount || ''} ticket${cart.totalCount === 1 ? '' : 's'}`
                : 'Sign in to buy'
          }}
        </button>
        <p class="muted small hint">Seats are held for 15 minutes while you pay.</p>
      </aside>
    </div>

    <div v-else-if="error" class="alert alert-error">{{ error }}</div>
  </div>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 48px;
  align-items: start;
}

.back {
  display: inline-block;
  margin-bottom: 20px;
  text-decoration: none;
}

.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 32px;
  padding: 20px 0;
  margin: 8px 0 24px;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
}

.meta strong {
  display: block;
  margin-top: 6px;
}

.cover {
  border-radius: var(--radius);
  margin-bottom: 24px;
}

.description {
  font-size: 1.08rem;
  white-space: pre-line;
  max-width: 62ch;
}

.buy {
  position: sticky;
  top: 88px;
}

.types {
  list-style: none;
  padding: 0;
  margin: 0 0 16px;
}

.type {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 0;
  border-bottom: 1px dashed var(--line);
}

.small {
  font-size: 0.85rem;
}

.stepper {
  display: inline-flex;
  align-items: center;
  border: 1.5px solid var(--line);
  border-radius: 999px;
}

.stepper button {
  width: 36px;
  height: 36px;
  border: 0;
  background: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: var(--ink);
}

.stepper button:disabled {
  color: var(--line);
  cursor: not-allowed;
}

.stepper span {
  min-width: 22px;
  text-align: center;
}

.providers {
  border: 0;
  padding: 0;
  margin: 0 0 16px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.providers legend {
  margin-bottom: 8px;
}

.provider {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 1.5px solid var(--line);
  border-radius: 999px;
  cursor: pointer;
  font-size: 0.9rem;
}

.provider.active {
  border-color: var(--ink);
}

.provider input {
  accent-color: var(--accent);
}

.total {
  font-size: 1.15rem;
  padding: 12px 0 16px;
}

.hint {
  margin: 10px 0 0;
  text-align: center;
}

@media (max-width: 900px) {
  .layout {
    grid-template-columns: 1fr;
    gap: 24px;
  }

  .buy {
    position: static;
  }
}
</style>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { ordersApi } from '@/api'
import { errorMessage, statusOf } from '@/api/http'
import type { Order } from '@/api/types'
import QrCode from '@/components/QrCode.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { useToastStore } from '@/stores/toast'
import { goToCheckout } from '@/utils/checkout'
import { dateLong, money, providerLabels, time } from '@/utils/format'

const props = defineProps<{ uuid: string }>()
const route = useRoute()
const toast = useToastStore()

const order = ref<Order | null>(null)
const error = ref('')
const busy = ref(false)
const now = ref(Date.now())

let poll: number | undefined
let clock: number | undefined
let polls = 0

async function load() {
  try {
    order.value = await ordersApi.get(props.uuid)
  } catch (e) {
    error.value = statusOf(e) === 404 ? 'Order not found.' : errorMessage(e)
  }
}

// After returning from the payment page the webhook may arrive a few seconds
// later, so keep polling while the order is pending (and tickets are issued
// by a queued job right after payment).
function schedulePoll() {
  window.clearTimeout(poll)
  const waitingForTickets =
    order.value?.status === 'paid' && (order.value.tickets?.length ?? 0) === 0
  if ((order.value?.status === 'pending' || waitingForTickets) && polls < 60) {
    poll = window.setTimeout(async () => {
      polls++
      const before = order.value?.status
      await load()
      if (before === 'pending' && order.value?.status === 'paid') toast.success('Payment received!')
      schedulePoll()
    }, 3000)
  }
}

const secondsLeft = computed(() => {
  if (!order.value?.expires_at || order.value.status !== 'pending') return null
  return Math.max(0, Math.floor((new Date(order.value.expires_at).getTime() - now.value) / 1000))
})

const countdown = computed(() => {
  const s = secondsLeft.value ?? 0
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
})

async function payAgain() {
  busy.value = true
  try {
    const { checkout } = await ordersApi.checkout(props.uuid)
    goToCheckout(checkout)
  } catch (e) {
    toast.error(errorMessage(e))
    busy.value = false
  }
}

async function cancel() {
  if (!window.confirm('Cancel this order and release the seats?')) return
  busy.value = true
  try {
    order.value = { ...order.value!, ...(await ordersApi.cancel(props.uuid)) }
    toast.info('Order cancelled.')
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  await load()
  if (route.query.paid) toast.info('Confirming your payment…')
  schedulePoll()
  clock = window.setInterval(() => (now.value = Date.now()), 1000)
})

onBeforeUnmount(() => {
  window.clearTimeout(poll)
  window.clearInterval(clock)
})
</script>

<template>
  <div class="container page">
    <RouterLink to="/orders" class="muted back">← My tickets</RouterLink>

    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-else-if="!order" class="skeleton" style="height: 320px" />

    <template v-else>
      <div class="row head">
        <div>
          <p class="eyebrow">Order {{ order.uuid.slice(0, 8) }}</p>
          <h1>{{ order.event?.title }}</h1>
          <p v-if="order.event" class="muted">
            {{ dateLong(order.event.starts_at) }}, {{ time(order.event.starts_at) }} ·
            {{ order.event.venue }}, {{ order.event.city }}
          </p>
        </div>
        <span class="spacer" />
        <StatusBadge :status="order.status" />
      </div>

      <div v-if="order.status === 'pending'" class="card pending">
        <div>
          <h3>Waiting for payment</h3>
          <p class="muted">
            Paying with {{ providerLabels[order.payment_provider] ?? order.payment_provider }}.
            <template v-if="secondsLeft"
              >Seats are held for another <strong class="mono">{{ countdown }}</strong
              >.</template
            >
            <template v-else>The hold has run out; the order will expire shortly.</template>
          </p>
        </div>
        <div class="row">
          <button class="btn btn-accent" :disabled="busy || !secondsLeft" @click="payAgain">
            Pay now
          </button>
          <button class="btn btn-ghost" :disabled="busy" @click="cancel">Cancel order</button>
        </div>
      </div>

      <div v-else-if="order.status === 'expired'" class="alert alert-warn">
        Payment did not arrive in time, so the seats were released. You can place a new order.
      </div>
      <div v-else-if="order.status === 'refunded'" class="alert alert-warn">
        This order was refunded; its tickets are no longer valid.
      </div>

      <section v-if="order.status === 'paid' && !order.tickets?.length" class="card">
        Issuing your tickets…
      </section>

      <section v-if="order.tickets?.length" class="tickets">
        <article
          v-for="ticket in order.tickets"
          :key="ticket.id"
          class="ticket"
          :class="ticket.status"
        >
          <div class="qr-side">
            <QrCode :value="ticket.code" :size="148" />
          </div>
          <div class="info">
            <div class="eyebrow">{{ order.event?.city }}</div>
            <h3>{{ ticket.ticket_type }}</h3>
            <div class="mono code">{{ ticket.code }}</div>
            <StatusBadge :status="ticket.status" />
          </div>
        </article>
      </section>

      <section class="card summary">
        <h3>Summary</h3>
        <div v-for="item in order.items" :key="item.ticket_type" class="row line">
          <span>{{ item.quantity }} × {{ item.ticket_type }}</span>
          <span class="spacer" />
          <span>{{ money(item.unit_price * item.quantity, order.currency) }}</span>
        </div>
        <div class="row line total">
          <strong>Total</strong>
          <span class="spacer" />
          <strong>{{ money(order.total, order.currency) }}</strong>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.back {
  display: inline-block;
  margin-bottom: 20px;
  text-decoration: none;
}

.head {
  align-items: flex-start;
  margin-bottom: 24px;
}

.pending {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
  margin-bottom: 24px;
  border-color: var(--accent);
}

.pending p {
  margin: 0;
}

.tickets {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.ticket {
  display: grid;
  grid-template-columns: auto 1fr;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  overflow: hidden;
}

.ticket.voided {
  opacity: 0.5;
}

.qr-side {
  position: relative;
  padding: 16px;
  border-right: 2px dashed var(--line);
}

.qr-side::before,
.qr-side::after {
  content: '';
  position: absolute;
  right: -9px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--paper);
  border: 1px solid var(--line);
}

.qr-side::before {
  top: -9px;
}

.qr-side::after {
  bottom: -9px;
}

.info {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-start;
}

.info h3 {
  margin: 0;
}

.code {
  font-size: 0.78rem;
  word-break: break-all;
  color: var(--ink-2);
}

.summary {
  max-width: 480px;
}

.line {
  padding: 8px 0;
  border-bottom: 1px dashed var(--line);
}

.line.total {
  border-bottom: 0;
}

@media (max-width: 480px) {
  .tickets {
    grid-template-columns: 1fr;
  }

  .ticket {
    grid-template-columns: 1fr;
  }

  .qr-side {
    border-right: 0;
    border-bottom: 2px dashed var(--line);
    display: flex;
    justify-content: center;
  }

  .qr-side::before,
  .qr-side::after {
    display: none;
  }
}
</style>

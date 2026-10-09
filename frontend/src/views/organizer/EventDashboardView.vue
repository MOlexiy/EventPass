<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { organizerApi } from '@/api'
import { errorMessage } from '@/api/http'
import type { EventItem, EventStats, Order, Paginated } from '@/api/types'
import StatusBadge from '@/components/StatusBadge.vue'
import { useToastStore } from '@/stores/toast'
import { dateLong, money, providerLabels, time } from '@/utils/format'

const props = defineProps<{ slug: string }>()
const router = useRouter()
const toast = useToastStore()

const event = ref<EventItem | null>(null)
const stats = ref<EventStats | null>(null)
const orders = ref<Paginated<Order> | null>(null)
const error = ref('')
const busy = ref(false)

async function load(page = 1) {
  try {
    const [e, s, o] = await Promise.all([
      organizerApi.event(props.slug),
      organizerApi.stats(props.slug),
      organizerApi.orders(props.slug, page),
    ])
    event.value = e
    stats.value = s
    orders.value = o
  } catch (e) {
    error.value = errorMessage(e)
  }
}

async function act(action: 'publish' | 'cancel' | 'remove') {
  const prompts = {
    publish: 'Publish this event? It becomes visible and tickets go on sale.',
    cancel: 'Cancel this event? Sales stop immediately.',
    remove: 'Delete this draft permanently?',
  }
  if (!window.confirm(prompts[action])) return
  busy.value = true
  try {
    if (action === 'remove') {
      await organizerApi.remove(props.slug)
      toast.info('Event deleted.')
      router.push({ name: 'organizer' })
      return
    }
    event.value = await organizerApi[action](props.slug)
    toast.success(action === 'publish' ? 'Published. Tickets are on sale.' : 'Event cancelled.')
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    busy.value = false
  }
}

async function refund(order: Order) {
  if (
    !window.confirm(
      `Refund ${money(order.total, order.currency)} to ${order.buyer?.name}? Their tickets will stop working.`,
    )
  )
    return
  busy.value = true
  try {
    await organizerApi.refund(order.uuid)
    toast.success('Refunded.')
    await load(orders.value?.meta.current_page)
  } catch (e) {
    toast.error(errorMessage(e))
  } finally {
    busy.value = false
  }
}

const pct = (part: number, whole: number) => (whole ? Math.round((part / whole) * 100) : 0)

onMounted(() => load())
</script>

<template>
  <div class="container page">
    <RouterLink to="/organizer" class="muted back">← My events</RouterLink>
    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-else-if="!event || !stats" class="skeleton" style="height: 360px" />

    <template v-else>
      <div class="row head">
        <div>
          <p class="eyebrow">
            {{ dateLong(event.starts_at) }} · {{ time(event.starts_at) }} · {{ event.city }}
          </p>
          <h1>{{ event.title }}</h1>
          <StatusBadge :status="event.status" />
        </div>
        <span class="spacer" />
        <div class="row">
          <RouterLink
            v-if="event.status === 'published'"
            :to="{ name: 'event', params: { slug } }"
            class="btn btn-ghost btn-sm"
          >
            Public page
          </RouterLink>
          <RouterLink :to="{ name: 'event-edit', params: { slug } }" class="btn btn-ghost btn-sm"
            >Edit</RouterLink
          >
          <button
            v-if="event.status === 'draft'"
            class="btn btn-accent btn-sm"
            :disabled="busy"
            @click="act('publish')"
          >
            Publish
          </button>
          <button
            v-if="event.status === 'draft' && stats.sold === 0"
            class="btn btn-danger btn-sm"
            :disabled="busy"
            @click="act('remove')"
          >
            Delete
          </button>
          <RouterLink
            v-if="event.status === 'published'"
            :to="{ name: 'event-scan', params: { slug } }"
            class="btn btn-sm"
          >
            Scan tickets
          </RouterLink>
          <button
            v-if="event.status === 'published'"
            class="btn btn-danger btn-sm"
            :disabled="busy"
            @click="act('cancel')"
          >
            Cancel event
          </button>
        </div>
      </div>

      <div class="kpis">
        <div class="card kpi">
          <span class="eyebrow">Revenue</span>
          <strong>{{ money(stats.revenue, stats.currency ?? 'UAH') }}</strong>
        </div>
        <div class="card kpi">
          <span class="eyebrow">Tickets sold</span>
          <strong>{{ stats.sold }}</strong>
        </div>
        <div class="card kpi">
          <span class="eyebrow">Checked in</span>
          <strong
            >{{ stats.checked_in }} <small class="muted">/ {{ stats.issued }}</small></strong
          >
        </div>
      </div>

      <section class="card">
        <h3>By ticket type</h3>
        <div v-for="t in stats.ticket_types" :key="t.id" class="type">
          <div class="row">
            <strong>{{ t.name }}</strong>
            <span class="muted">{{ money(t.price, stats.currency ?? 'UAH') }}</span>
            <span class="spacer" />
            <span class="mono"
              >{{ t.sold }} sold<template v-if="t.reserved"> · {{ t.reserved }} reserved</template>
              / {{ t.quantity }}</span
            >
          </div>
          <div class="bar" role="img" :aria-label="`${pct(t.sold, t.quantity)}% sold`">
            <span class="sold" :style="{ width: pct(t.sold, t.quantity) + '%' }" />
            <span class="reserved" :style="{ width: pct(t.reserved, t.quantity) + '%' }" />
          </div>
        </div>
      </section>

      <section class="card">
        <h3>Orders</h3>
        <p v-if="!orders?.data.length" class="muted">No orders yet.</p>
        <div v-else class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Buyer</th>
                <th>Tickets</th>
                <th>Total</th>
                <th>Paid via</th>
                <th>Status</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in orders.data" :key="o.uuid">
                <td>
                  {{ o.buyer?.name }}
                  <div class="muted small">{{ o.buyer?.email }}</div>
                </td>
                <td>{{ o.items?.map((i) => `${i.quantity} × ${i.ticket_type}`).join(', ') }}</td>
                <td class="mono">{{ money(o.total, o.currency) }}</td>
                <td>{{ providerLabels[o.payment_provider] ?? o.payment_provider }}</td>
                <td><StatusBadge :status="o.status" /></td>
                <td>
                  <button
                    v-if="o.status === 'paid'"
                    class="btn btn-danger btn-sm"
                    :disabled="busy"
                    @click="refund(o)"
                  >
                    Refund
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="orders && orders.meta.last_page > 1" class="row pager">
          <button
            class="btn btn-ghost btn-sm"
            :disabled="orders.meta.current_page <= 1"
            @click="load(orders.meta.current_page - 1)"
          >
            ←
          </button>
          <span class="muted">{{ orders.meta.current_page }} / {{ orders.meta.last_page }}</span>
          <button
            class="btn btn-ghost btn-sm"
            :disabled="orders.meta.current_page >= orders.meta.last_page"
            @click="load(orders.meta.current_page + 1)"
          >
            →
          </button>
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
  align-items: flex-end;
  margin-bottom: 24px;
}

.kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.kpi strong {
  display: block;
  margin-top: 10px;
  font: 800 2rem/1 var(--font-display);
}

.kpi small {
  font-size: 1rem;
}

section.card {
  margin-bottom: 20px;
}

.type {
  padding: 12px 0;
}

.bar {
  display: flex;
  height: 10px;
  margin-top: 8px;
  border-radius: 999px;
  background: var(--paper-2);
  overflow: hidden;
}

.bar .sold {
  background: var(--ink);
}

.bar .reserved {
  background: var(--accent);
  opacity: 0.6;
}

.small {
  font-size: 0.85rem;
}

.pager {
  justify-content: center;
  margin-top: 16px;
}
</style>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ordersApi } from '@/api'
import { errorMessage } from '@/api/http'
import type { Order } from '@/api/types'
import { useToastStore } from '@/stores/toast'
import { money } from '@/utils/format'

const props = defineProps<{ uuid: string }>()
const router = useRouter()
const toast = useToastStore()
const order = ref<Order | null>(null)
const busy = ref(false)
const error = ref('')

onMounted(async () => {
  try {
    order.value = await ordersApi.get(props.uuid)
  } catch (e) {
    error.value = errorMessage(e)
  }
})

async function complete(outcome: 'paid' | 'failed') {
  busy.value = true
  try {
    await ordersApi.fakePay(props.uuid, outcome)
    if (outcome === 'failed') toast.error('The test bank declined the payment.')
    router.replace({ name: 'order', params: { uuid: props.uuid } })
  } catch (e) {
    error.value = errorMessage(e)
    busy.value = false
  }
}
</script>

<template>
  <div class="narrow page">
    <div class="bank">
      <div class="bank-head">
        <span class="eyebrow">Test payment gateway</span>
        <strong>Sandbox Bank</strong>
      </div>
      <div class="bank-body">
        <p class="muted">
          This page stands in for LiqPay or Stripe in local development. Your choice is sent through
          the same signed-webhook pipeline as a real provider.
        </p>
        <div v-if="error" class="alert alert-error">{{ error }}</div>
        <template v-if="order">
          <div class="amount">{{ money(order.total, order.currency) }}</div>
          <p class="muted">{{ order.event?.title }}</p>
          <div class="card-mock mono">4242 4242 4242 4242 · 12/34 · 123</div>
          <button
            class="btn btn-accent btn-block"
            :disabled="busy || order.status !== 'pending'"
            @click="complete('paid')"
          >
            Pay {{ money(order.total, order.currency) }}
          </button>
          <button
            class="btn btn-ghost btn-block decline"
            :disabled="busy || order.status !== 'pending'"
            @click="complete('failed')"
          >
            Simulate a declined card
          </button>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bank {
  margin-top: 48px;
  border-radius: var(--radius);
  overflow: hidden;
  border: 1px solid var(--line);
  background: var(--card);
}

.bank-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 24px;
  background: #23314d;
  color: #fff;
}

.bank-head .eyebrow {
  color: #aab8d6;
}

.bank-body {
  padding: 24px;
}

.amount {
  font: 800 2.6rem/1 var(--font-display);
  margin: 12px 0 4px;
}

.card-mock {
  padding: 14px;
  border: 1.5px solid var(--line);
  border-radius: 10px;
  margin: 16px 0 20px;
  color: var(--ink-2);
}

.decline {
  margin-top: 10px;
}
</style>

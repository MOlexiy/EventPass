<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { ordersApi } from '@/api'
import { errorMessage } from '@/api/http'
import type { Order } from '@/api/types'
import StatusBadge from '@/components/StatusBadge.vue'
import { dateLong, money } from '@/utils/format'

const orders = ref<Order[] | null>(null)
const error = ref('')

onMounted(async () => {
  try {
    orders.value = (await ordersApi.list()).data
  } catch (e) {
    error.value = errorMessage(e)
  }
})
</script>

<template>
  <div class="container page">
    <p class="eyebrow">Account</p>
    <h1>My tickets</h1>

    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-else-if="!orders" class="skeleton" style="height: 240px" />

    <div v-else-if="orders.length === 0" class="empty">
      <h3>No orders yet</h3>
      <p>When you buy tickets they show up here, with QR codes for the entrance.</p>
      <RouterLink to="/" class="btn">Browse events</RouterLink>
    </div>

    <ul v-else class="list">
      <li v-for="order in orders" :key="order.uuid">
        <RouterLink :to="{ name: 'order', params: { uuid: order.uuid } }" class="order card">
          <div>
            <div class="eyebrow">{{ order.event ? dateLong(order.event.starts_at) : '' }}</div>
            <h3>{{ order.event?.title }}</h3>
            <div class="muted">
              {{ order.items?.map((i) => `${i.quantity} × ${i.ticket_type}`).join(', ') }}
            </div>
          </div>
          <div class="side">
            <StatusBadge :status="order.status" />
            <strong>{{ money(order.total, order.currency) }}</strong>
          </div>
        </RouterLink>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 12px;
}

.order {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  text-decoration: none;
  transition: border-color 0.15s;
}

.order:hover {
  border-color: var(--ink);
}

.order h3 {
  margin: 8px 0 4px;
}

.side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
}
</style>

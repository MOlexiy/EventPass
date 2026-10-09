<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { organizerApi } from '@/api'
import { errorMessage } from '@/api/http'
import type { EventItem } from '@/api/types'
import StatusBadge from '@/components/StatusBadge.vue'
import { dateLong, time } from '@/utils/format'

const events = ref<EventItem[] | null>(null)
const error = ref('')

onMounted(async () => {
  try {
    events.value = await organizerApi.events()
  } catch (e) {
    error.value = errorMessage(e)
  }
})

const capacity = (e: EventItem) => (e.ticket_types ?? []).reduce((s, t) => s + t.quantity, 0)
const left = (e: EventItem) => (e.ticket_types ?? []).reduce((s, t) => s + t.available, 0)
</script>

<template>
  <div class="container page">
    <div class="row head">
      <div>
        <p class="eyebrow">Organizer</p>
        <h1>My events</h1>
      </div>
      <span class="spacer" />
      <RouterLink :to="{ name: 'event-create' }" class="btn btn-accent">+ New event</RouterLink>
    </div>

    <div v-if="error" class="alert alert-error">{{ error }}</div>
    <div v-else-if="!events" class="skeleton" style="height: 240px" />

    <div v-else-if="events.length === 0" class="empty">
      <h3>No events yet</h3>
      <p>Create a draft, add ticket types, publish when ready.</p>
      <RouterLink :to="{ name: 'event-create' }" class="btn">Create your first event</RouterLink>
    </div>

    <div v-else class="card table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Event</th>
            <th>Date</th>
            <th>Status</th>
            <th>Taken</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in events" :key="e.id">
            <td>
              <RouterLink :to="{ name: 'event-dashboard', params: { slug: e.slug } }">
                <strong>{{ e.title }}</strong>
              </RouterLink>
              <div class="muted small">{{ e.venue }}, {{ e.city }}</div>
            </td>
            <td class="nowrap">
              {{ dateLong(e.starts_at) }}<br /><span class="muted">{{ time(e.starts_at) }}</span>
            </td>
            <td><StatusBadge :status="e.status" /></td>
            <td class="mono">{{ capacity(e) - left(e) }} / {{ capacity(e) }}</td>
            <td class="actions">
              <RouterLink
                :to="{ name: 'event-edit', params: { slug: e.slug } }"
                class="btn btn-ghost btn-sm"
                >Edit</RouterLink
              >
              <RouterLink
                v-if="e.status === 'published'"
                :to="{ name: 'event-scan', params: { slug: e.slug } }"
                class="btn btn-sm"
              >
                Scan
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.head {
  align-items: flex-end;
  margin-bottom: 24px;
}

.small {
  font-size: 0.85rem;
}

.nowrap {
  white-space: nowrap;
}

.actions {
  text-align: right;
  white-space: nowrap;
}

.actions .btn + .btn {
  margin-left: 8px;
}
</style>

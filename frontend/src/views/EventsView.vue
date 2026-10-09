<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { eventsApi } from '@/api'
import { errorMessage } from '@/api/http'
import type { EventItem, Paginated } from '@/api/types'
import EventCard from '@/components/EventCard.vue'

const route = useRoute()
const router = useRouter()

const q = ref(String(route.query.q ?? ''))
const city = ref(String(route.query.city ?? ''))
const cities = ref<string[]>([])
const result = ref<Paginated<EventItem> | null>(null)
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    result.value = await eventsApi.list({
      q: (route.query.q as string) || undefined,
      city: (route.query.city as string) || undefined,
      page: Number(route.query.page ?? 1),
    })
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

let debounce: number | undefined
watch(q, () => {
  window.clearTimeout(debounce)
  debounce = window.setTimeout(applyFilters, 300)
})
watch(city, applyFilters)

function applyFilters() {
  router.replace({ query: { q: q.value || undefined, city: city.value || undefined } })
}

function goToPage(page: number) {
  router.push({ query: { ...route.query, page } })
}

watch(() => route.query, load)
onMounted(async () => {
  load()
  cities.value = await eventsApi.cities().catch(() => [])
})
</script>

<template>
  <section class="hero">
    <div class="container">
      <p class="eyebrow">Concerts · meetups · festivals</p>
      <h1>Tickets in your pocket,<br /><span class="accent">doors in a scan.</span></h1>
      <p class="lead">
        Pick an event, pay with LiqPay or Stripe, get QR tickets by email. Organizers sell and check
        guests in from the same app.
      </p>
      <div class="filters">
        <input
          v-model="q"
          class="input"
          type="search"
          placeholder="Search events or venues"
          aria-label="Search"
        />
        <select v-model="city" class="input" aria-label="City">
          <option value="">All cities</option>
          <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
        </select>
      </div>
    </div>
  </section>

  <section class="container page">
    <div v-if="error" class="alert alert-error">{{ error }}</div>

    <div v-if="loading && !result" class="grid">
      <div v-for="n in 6" :key="n" class="skeleton" style="height: 170px" />
    </div>

    <template v-else-if="result">
      <div v-if="result.data.length === 0" class="empty">
        <h3>No events found</h3>
        <p>Try another city or a shorter search.</p>
      </div>
      <div class="grid" :class="{ dim: loading }">
        <EventCard v-for="event in result.data" :key="event.id" :event="event" />
      </div>
      <div v-if="result.meta.last_page > 1" class="row pager">
        <button
          class="btn btn-ghost btn-sm"
          :disabled="result.meta.current_page <= 1"
          @click="goToPage(result.meta.current_page - 1)"
        >
          ← Previous
        </button>
        <span class="muted"
          >Page {{ result.meta.current_page }} of {{ result.meta.last_page }}</span
        >
        <button
          class="btn btn-ghost btn-sm"
          :disabled="result.meta.current_page >= result.meta.last_page"
          @click="goToPage(result.meta.current_page + 1)"
        >
          Next →
        </button>
      </div>
    </template>
  </section>
</template>

<style scoped>
.hero {
  padding: 64px 0 48px;
  border-bottom: 1px solid var(--line);
  background:
    radial-gradient(
      circle at 85% 20%,
      color-mix(in srgb, var(--accent) 18%, transparent),
      transparent 40%
    ),
    var(--paper);
}

.accent {
  color: var(--accent);
}

.lead {
  max-width: 560px;
  font-size: 1.1rem;
  color: var(--ink-2);
}

.filters {
  display: grid;
  grid-template-columns: 1fr 220px;
  gap: 12px;
  max-width: 640px;
  margin-top: 28px;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 20px;
  transition: opacity 0.2s;
}

.grid.dim {
  opacity: 0.5;
}

.pager {
  justify-content: center;
  margin-top: 32px;
}

@media (max-width: 560px) {
  .filters {
    grid-template-columns: 1fr;
  }

  .grid {
    grid-template-columns: 1fr;
  }
}
</style>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { EventItem } from '@/api/types'
import { dateParts, money, time } from '@/utils/format'

const props = defineProps<{ event: EventItem }>()
const date = computed(() => dateParts(props.event.starts_at))
const soldOut = computed(
  () =>
    (props.event.ticket_types ?? []).length > 0 &&
    props.event.ticket_types!.every((t) => t.available === 0),
)
</script>

<template>
  <RouterLink :to="{ name: 'event', params: { slug: event.slug } }" class="event-card">
    <div class="stub">
      <span class="day">{{ date.day }}</span>
      <span class="month">{{ date.month }}</span>
    </div>
    <div class="body">
      <div class="eyebrow">{{ event.city }} · {{ time(event.starts_at) }}</div>
      <h3>{{ event.title }}</h3>
      <p class="muted venue">{{ event.venue }}</p>
      <div class="row foot">
        <span v-if="soldOut" class="badge badge-bad">Sold out</span>
        <span v-else-if="event.price_from" class="price"
          >from {{ money(event.price_from, event.currency ?? 'UAH') }}</span
        >
        <span class="spacer" />
        <span class="arrow" aria-hidden="true">→</span>
      </div>
    </div>
  </RouterLink>
</template>

<style scoped>
.event-card {
  display: grid;
  grid-template-columns: 84px 1fr;
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  text-decoration: none;
  color: inherit;
  overflow: hidden;
  transition:
    transform 0.15s ease,
    box-shadow 0.15s ease;
}

.event-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow);
}

.stub {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: var(--ink);
  color: var(--paper);
  border-right: 2px dashed var(--paper);
}

/* perforation notches */
.stub::before,
.stub::after {
  content: '';
  position: absolute;
  right: -9px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: var(--paper);
}

.stub::before {
  top: -8px;
}

.stub::after {
  bottom: -8px;
}

.day {
  font: 800 2rem/1 var(--font-display);
}

.month {
  font: 500 0.75rem/1.4 var(--font-mono);
  letter-spacing: 0.15em;
  color: var(--accent);
}

.body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
}

.body h3 {
  margin: 8px 0 4px;
}

.venue {
  margin: 0 0 14px;
  font-size: 0.92rem;
}

.foot {
  margin-top: auto;
}

.price {
  font-weight: 600;
}

.arrow {
  font-size: 1.2rem;
  color: var(--accent);
}
</style>

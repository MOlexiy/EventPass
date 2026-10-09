import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { EventItem } from '@/api/types'

export const MAX_TICKETS = 10

/**
 * Ticket selection for the event being viewed. Kept in a store so the
 * choice survives a detour to the login page and back.
 */
export const useCartStore = defineStore('cart', () => {
  const eventId = ref<number | null>(null)
  const quantities = ref<Record<number, number>>({})
  const provider = ref<string>('')

  function forEvent(event: EventItem) {
    if (eventId.value !== event.id) {
      eventId.value = event.id
      quantities.value = {}
    }
  }

  function setQuantity(ticketTypeId: number, quantity: number) {
    const others = totalCount.value - (quantities.value[ticketTypeId] ?? 0)
    quantities.value[ticketTypeId] = Math.max(0, Math.min(quantity, MAX_TICKETS - others))
  }

  const totalCount = computed(() => Object.values(quantities.value).reduce((sum, q) => sum + q, 0))

  function total(event: EventItem): number {
    return (event.ticket_types ?? []).reduce(
      (sum, type) => sum + type.price * (quantities.value[type.id] ?? 0),
      0,
    )
  }

  const items = computed(() =>
    Object.entries(quantities.value)
      .filter(([, quantity]) => quantity > 0)
      .map(([id, quantity]) => ({ ticket_type_id: Number(id), quantity })),
  )

  function clear() {
    quantities.value = {}
  }

  return { eventId, quantities, provider, forEvent, setQuantity, totalCount, total, items, clear }
})

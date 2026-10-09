import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { MAX_TICKETS, useCartStore } from '@/stores/cart'
import type { EventItem } from '@/api/types'

const event = {
  id: 1,
  ticket_types: [
    { id: 10, name: 'Standard', price: 50000, currency: 'UAH', quantity: 100, available: 100 },
    { id: 11, name: 'VIP', price: 150000, currency: 'UAH', quantity: 10, available: 10 },
  ],
} as EventItem

describe('cart store', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('sums the total in minor units', () => {
    const cart = useCartStore()
    cart.forEvent(event)
    cart.setQuantity(10, 2)
    cart.setQuantity(11, 1)

    expect(cart.total(event)).toBe(250000)
    expect(cart.items).toEqual([
      { ticket_type_id: 10, quantity: 2 },
      { ticket_type_id: 11, quantity: 1 },
    ])
  })

  it('caps the whole order at the per-order limit', () => {
    const cart = useCartStore()
    cart.forEvent(event)
    cart.setQuantity(10, 8)
    cart.setQuantity(11, 5)

    expect(cart.totalCount).toBe(MAX_TICKETS)
    expect(cart.quantities[11]).toBe(MAX_TICKETS - 8)
  })

  it('resets the selection when switching events', () => {
    const cart = useCartStore()
    cart.forEvent(event)
    cart.setQuantity(10, 3)
    cart.forEvent({ ...event, id: 2 })

    expect(cart.totalCount).toBe(0)
  })
})

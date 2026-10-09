export type Role = 'customer' | 'organizer' | 'admin'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  avatar_url: string | null
  email_verified: boolean
  has_password: boolean
}

export interface TicketType {
  id: number
  name: string
  price: number // minor units (kopecks)
  currency: string
  quantity: number
  available: number
}

export type EventStatus = 'draft' | 'published' | 'cancelled'

export interface EventItem {
  id: number
  slug: string
  title: string
  description: string
  venue: string
  city: string
  starts_at: string
  ends_at: string | null
  cover_url: string | null
  status: EventStatus
  on_sale: boolean
  organizer?: { id: number; name: string }
  price_from?: number | null
  currency?: string | null
  ticket_types?: TicketType[]
}

export type OrderStatus = 'pending' | 'paid' | 'cancelled' | 'expired' | 'refunded'

export interface Ticket {
  id: number
  code: string
  status: 'valid' | 'used' | 'voided'
  ticket_type?: string
  checked_in_at: string | null
}

export interface Order {
  uuid: string
  status: OrderStatus
  total: number
  currency: string
  payment_provider: string
  expires_at: string | null
  paid_at: string | null
  refunded_at: string | null
  created_at: string
  buyer?: { name: string; email: string }
  event?: EventItem
  items?: { ticket_type: string; quantity: number; unit_price: number }[]
  tickets?: Ticket[]
}

export interface Checkout {
  type: 'redirect' | 'form'
  url: string
  fields: Record<string, string>
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}

export interface EventStats {
  ticket_types: {
    id: number
    name: string
    price: number
    quantity: number
    sold: number
    reserved: number
    revenue: number
  }[]
  sold: number
  revenue: number
  currency: string | null
  checked_in: number
  issued: number
}

export interface CheckInResult {
  status: 'ok' | 'already_used' | 'voided' | 'invalid'
  message: string
  ticket: { code: string; ticket_type: string; holder: string; checked_in_at: string | null } | null
}

export interface TicketTypeInput {
  id?: number
  name: string
  price: number
  quantity: number
}

export interface EventInput {
  title: string
  description: string
  venue: string
  city: string
  starts_at: string
  ends_at: string | null
  cover_url: string | null
  ticket_types: TicketTypeInput[]
}

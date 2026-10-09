import { http } from './http'
import type {
  CheckInResult,
  Checkout,
  EventInput,
  EventItem,
  EventStats,
  Order,
  Paginated,
  User,
} from './types'

type Data<T> = { data: T }

export const authApi = {
  me: () => http.get<Data<User>>('/api/auth/user').then((r) => r.data.data),
  login: (payload: { email: string; password: string; remember: boolean }) =>
    http.post<Data<User>>('/api/auth/login', payload).then((r) => r.data.data),
  register: (payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
    role: 'customer' | 'organizer'
  }) => http.post<Data<User>>('/api/auth/register', payload).then((r) => r.data.data),
  logout: () => http.post('/api/auth/logout'),
  resendVerification: () => http.post('/api/auth/email/verification-notification'),
  forgotPassword: (email: string) =>
    http.post<{ message: string }>('/api/auth/forgot-password', { email }).then((r) => r.data),
  resetPassword: (payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }) => http.post<{ message: string }>('/api/auth/reset-password', payload).then((r) => r.data),
}

export const eventsApi = {
  list: (params: { q?: string; city?: string; page?: number }) =>
    http.get<Paginated<EventItem>>('/api/events', { params }).then((r) => r.data),
  cities: () => http.get<Data<string[]>>('/api/events/cities').then((r) => r.data.data),
  get: (slug: string) => http.get<Data<EventItem>>(`/api/events/${slug}`).then((r) => r.data.data),
}

export interface CheckoutResponse {
  order: Order
  checkout: Checkout
}

export const ordersApi = {
  providers: () => http.get<Data<string[]>>('/api/payments/providers').then((r) => r.data.data),
  list: () => http.get<Paginated<Order>>('/api/orders').then((r) => r.data),
  get: (uuid: string) => http.get<Data<Order>>(`/api/orders/${uuid}`).then((r) => r.data.data),
  create: (payload: {
    event_id: number
    provider: string
    items: { ticket_type_id: number; quantity: number }[]
  }) => http.post<CheckoutResponse>('/api/orders', payload).then((r) => r.data),
  checkout: (uuid: string) =>
    http.post<CheckoutResponse>(`/api/orders/${uuid}/checkout`).then((r) => r.data),
  cancel: (uuid: string) =>
    http.post<Data<Order>>(`/api/orders/${uuid}/cancel`).then((r) => r.data.data),
  fakePay: (uuid: string, outcome: 'paid' | 'failed') =>
    http.post<{ status: string }>(`/api/payments/fake/${uuid}`, { outcome }).then((r) => r.data),
}

export const organizerApi = {
  events: () => http.get<Data<EventItem[]>>('/api/organizer/events').then((r) => r.data.data),
  event: (slug: string) =>
    http.get<Data<EventItem>>(`/api/organizer/events/${slug}`).then((r) => r.data.data),
  create: (payload: EventInput) =>
    http.post<Data<EventItem>>('/api/organizer/events', payload).then((r) => r.data.data),
  update: (slug: string, payload: EventInput) =>
    http.put<Data<EventItem>>(`/api/organizer/events/${slug}`, payload).then((r) => r.data.data),
  remove: (slug: string) => http.delete(`/api/organizer/events/${slug}`),
  publish: (slug: string) =>
    http.post<Data<EventItem>>(`/api/organizer/events/${slug}/publish`).then((r) => r.data.data),
  cancel: (slug: string) =>
    http.post<Data<EventItem>>(`/api/organizer/events/${slug}/cancel`).then((r) => r.data.data),
  stats: (slug: string) =>
    http.get<Data<EventStats>>(`/api/organizer/events/${slug}/stats`).then((r) => r.data.data),
  orders: (slug: string, page = 1) =>
    http
      .get<Paginated<Order>>(`/api/organizer/events/${slug}/orders`, { params: { page } })
      .then((r) => r.data),
  refund: (uuid: string) =>
    http.post<Data<Order>>(`/api/organizer/orders/${uuid}/refund`).then((r) => r.data.data),
  checkIn: (slug: string, code: string) =>
    http
      .post<CheckInResult>(
        `/api/organizer/events/${slug}/check-in`,
        { code },
        {
          // 404/409 carry a meaningful body for the scanner UI.
          validateStatus: (s) => s < 500 && s !== 401 && s !== 403 && s !== 419 && s !== 422,
        },
      )
      .then((r) => r.data),
}

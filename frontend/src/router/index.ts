import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import EventsView from '@/views/EventsView.vue'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
    organizer?: boolean
    title?: string
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: (_to, _from, saved) => saved ?? { top: 0 },
  routes: [
    { path: '/', name: 'events', component: EventsView },
    {
      path: '/events/:slug',
      name: 'event',
      component: () => import('@/views/EventView.vue'),
      props: true,
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guestOnly: true, title: 'Sign in' },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { guestOnly: true, title: 'Create account' },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/auth/ForgotPasswordView.vue'),
      meta: { guestOnly: true, title: 'Reset password' },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/auth/ResetPasswordView.vue'),
      meta: { title: 'New password' },
    },
    {
      path: '/orders',
      name: 'orders',
      component: () => import('@/views/OrdersView.vue'),
      meta: { requiresAuth: true, title: 'My tickets' },
    },
    {
      path: '/orders/:uuid',
      name: 'order',
      component: () => import('@/views/OrderView.vue'),
      props: true,
      meta: { requiresAuth: true, title: 'Order' },
    },
    {
      path: '/checkout/fake/:uuid',
      name: 'fake-checkout',
      component: () => import('@/views/FakeCheckoutView.vue'),
      props: true,
      meta: { requiresAuth: true, title: 'Test payment' },
    },
    {
      path: '/organizer',
      meta: { requiresAuth: true, organizer: true },
      children: [
        {
          path: '',
          name: 'organizer',
          component: () => import('@/views/organizer/OrganizerEventsView.vue'),
          meta: { title: 'My events' },
        },
        {
          path: 'events/new',
          name: 'event-create',
          component: () => import('@/views/organizer/EventFormView.vue'),
          meta: { title: 'New event' },
        },
        {
          path: 'events/:slug/edit',
          name: 'event-edit',
          component: () => import('@/views/organizer/EventFormView.vue'),
          props: true,
          meta: { title: 'Edit event' },
        },
        {
          path: 'events/:slug',
          name: 'event-dashboard',
          component: () => import('@/views/organizer/EventDashboardView.vue'),
          props: true,
          meta: { title: 'Sales' },
        },
        {
          path: 'events/:slug/scan',
          name: 'event-scan',
          component: () => import('@/views/organizer/ScannerView.vue'),
          props: true,
          meta: { title: 'Check-in' },
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  try {
    await auth.init()
  } catch {
    // Don't block the app when the API is down: public pages still render,
    // and protected ones fall through to the login redirect below.
    useToastStore().error('The API is not responding. Is the backend running?')
  }

  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.meta.guestOnly && auth.isLoggedIn) {
    return { name: 'events' }
  }
  if (to.meta.organizer && !auth.isOrganizer) {
    return { name: 'events' }
  }
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · EventPass` : 'EventPass'
})

export default router

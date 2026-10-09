<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()
const menuOpen = ref(false)

async function logout() {
  await auth.logout()
  menuOpen.value = false
  toast.info('Signed out.')
  router.push({ name: 'events' })
}
</script>

<template>
  <header class="header">
    <div class="container row">
      <RouterLink to="/" class="logo" aria-label="EventPass home">
        <svg viewBox="0 0 32 32" width="30" height="30" aria-hidden="true">
          <rect width="32" height="32" rx="7" fill="currentColor" />
          <path d="M8 10h16v4a2 2 0 0 0 0 4v4H8v-4a2 2 0 0 0 0-4z" fill="#ff5a1f" />
        </svg>
        <span>EventPass</span>
      </RouterLink>

      <span class="spacer" />

      <nav class="nav" :class="{ open: menuOpen }" @click="menuOpen = false">
        <RouterLink to="/">Events</RouterLink>
        <template v-if="auth.isLoggedIn">
          <RouterLink to="/orders">My tickets</RouterLink>
          <RouterLink v-if="auth.isOrganizer" to="/organizer">Organizer</RouterLink>
          <span class="user">
            <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="" class="avatar" />
            {{ auth.user?.name }}
          </span>
          <button class="btn btn-ghost btn-sm" @click.stop="logout">Sign out</button>
        </template>
        <template v-else>
          <RouterLink to="/login">Sign in</RouterLink>
          <RouterLink to="/register" class="btn btn-sm">Create account</RouterLink>
        </template>
      </nav>

      <button
        class="burger"
        :aria-expanded="menuOpen"
        aria-label="Menu"
        @click="menuOpen = !menuOpen"
      >
        <span /><span />
      </button>
    </div>
  </header>
</template>

<style scoped>
.header {
  position: sticky;
  top: 0;
  z-index: 20;
  background: color-mix(in srgb, var(--paper) 88%, transparent);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--line);
}

.header .container {
  min-height: 64px;
  flex-wrap: nowrap;
}

.logo {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font: 800 1.25rem/1 var(--font-display);
  letter-spacing: -0.03em;
  text-decoration: none;
  color: var(--ink);
}

.nav {
  display: flex;
  align-items: center;
  gap: 22px;
  font-weight: 500;
}

.nav a:not(.btn) {
  text-decoration: none;
  color: var(--ink-2);
}

.nav a.router-link-exact-active:not(.btn) {
  color: var(--ink);
  box-shadow: inset 0 -2px 0 var(--accent);
}

.user {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--muted);
  font-size: 0.9rem;
}

.avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
}

.burger {
  display: none;
  width: 44px;
  height: 44px;
  border: 0;
  background: none;
  cursor: pointer;
  flex-direction: column;
  justify-content: center;
  gap: 6px;
  padding: 0 10px;
}

.burger span {
  display: block;
  height: 2px;
  background: var(--ink);
}

@media (max-width: 760px) {
  .burger {
    display: flex;
  }

  .nav {
    display: none;
    position: absolute;
    top: 64px;
    left: 0;
    right: 0;
    flex-direction: column;
    align-items: stretch;
    gap: 4px;
    padding: 12px 16px 20px;
    background: var(--paper);
    border-bottom: 1px solid var(--line);
  }

  .nav.open {
    display: flex;
  }

  .nav a:not(.btn) {
    padding: 10px 0;
  }
}
</style>

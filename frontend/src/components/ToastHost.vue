<script setup lang="ts">
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()
</script>

<template>
  <div class="toasts" role="status" aria-live="polite">
    <TransitionGroup name="toast">
      <button
        v-for="t in toast.toasts"
        :key="t.id"
        class="toast"
        :class="t.kind"
        @click="toast.dismiss(t.id)"
      >
        {{ t.text }}
      </button>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toasts {
  position: fixed;
  right: 16px;
  bottom: 16px;
  z-index: 50;
  display: grid;
  gap: 8px;
  width: min(380px, calc(100% - 32px));
}

.toast {
  text-align: left;
  padding: 14px 16px;
  border: 0;
  border-left: 4px solid var(--ink-2);
  border-radius: 10px;
  background: var(--ink);
  color: var(--paper);
  font: inherit;
  box-shadow: var(--shadow);
  cursor: pointer;
}

.toast.success {
  border-left-color: #4fd18b;
}

.toast.error {
  border-left-color: var(--accent);
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}
</style>

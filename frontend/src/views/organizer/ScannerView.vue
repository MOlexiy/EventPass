<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { RouterLink } from 'vue-router'
import jsQR from 'jsqr'
import { organizerApi } from '@/api'
import { errorMessage } from '@/api/http'
import type { CheckInResult } from '@/api/types'
import { time } from '@/utils/format'

const props = defineProps<{ slug: string }>()

const video = ref<HTMLVideoElement | null>(null)
const cameraOn = ref(false)
const cameraError = ref('')
const manualCode = ref('')
const result = ref<CheckInResult | null>(null)
const history = ref<(CheckInResult & { at: string })[]>([])
const checking = ref(false)

let stream: MediaStream | null = null
let frame = 0
let lastCode = ''
let lastAt = 0
const canvas = document.createElement('canvas')
const ctx = canvas.getContext('2d', { willReadFrequently: true })

async function startCamera() {
  cameraError.value = ''
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    video.value!.srcObject = stream
    await video.value!.play()
    cameraOn.value = true
    frame = requestAnimationFrame(tick)
  } catch {
    cameraError.value = 'Camera is not available. Allow access or type the code below.'
  }
}

function stopCamera() {
  cancelAnimationFrame(frame)
  stream?.getTracks().forEach((t) => t.stop())
  stream = null
  cameraOn.value = false
}

function tick() {
  const v = video.value
  if (v && ctx && v.readyState === v.HAVE_ENOUGH_DATA) {
    canvas.width = v.videoWidth
    canvas.height = v.videoHeight
    ctx.drawImage(v, 0, 0)
    const image = ctx.getImageData(0, 0, canvas.width, canvas.height)
    const qr = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' })
    // The same code stays in frame for a while: scan it once per 3 seconds.
    if (qr?.data && (qr.data !== lastCode || Date.now() - lastAt > 3000)) {
      lastCode = qr.data
      lastAt = Date.now()
      check(qr.data)
    }
  }
  frame = requestAnimationFrame(tick)
}

async function check(code: string) {
  if (checking.value || !code.trim()) return
  checking.value = true
  try {
    result.value = await organizerApi.checkIn(props.slug, code)
    navigator.vibrate?.(result.value.status === 'ok' ? 80 : [60, 60, 60])
  } catch (e) {
    result.value = { status: 'invalid', message: errorMessage(e), ticket: null }
  } finally {
    history.value.unshift({ ...result.value!, at: new Date().toISOString() })
    history.value = history.value.slice(0, 15)
    checking.value = false
  }
}

function submitManual() {
  check(manualCode.value)
  manualCode.value = ''
}

onBeforeUnmount(stopCamera)
</script>

<template>
  <div class="container page scanner">
    <RouterLink :to="{ name: 'event-dashboard', params: { slug } }" class="muted back"
      >← Sales</RouterLink
    >
    <h1>Check-in</h1>

    <div class="layout">
      <section class="card">
        <div class="viewport">
          <video ref="video" playsinline muted :class="{ hidden: !cameraOn }" />
          <div v-if="!cameraOn" class="placeholder">
            <button class="btn btn-accent" @click="startCamera">Start camera</button>
            <p v-if="cameraError" class="error-text">{{ cameraError }}</p>
          </div>
          <div v-else class="frame" aria-hidden="true" />
        </div>
        <button v-if="cameraOn" class="btn btn-ghost btn-sm stop" @click="stopCamera">
          Stop camera
        </button>

        <form class="row manual" @submit.prevent="submitManual">
          <input
            v-model="manualCode"
            class="input mono"
            placeholder="EP-XXXXXXXX…"
            aria-label="Ticket code"
          />
          <button class="btn" :disabled="checking || !manualCode">Check</button>
        </form>
      </section>

      <section>
        <div
          v-if="result"
          class="result"
          :class="result.status"
          role="status"
          aria-live="assertive"
        >
          <span class="eyebrow">{{ result.status.replace('_', ' ') }}</span>
          <strong>{{ result.message }}</strong>
          <div v-if="result.status === 'already_used' && result.ticket?.checked_in_at">
            Scanned at {{ time(result.ticket.checked_in_at) }}
          </div>
          <div v-if="result.ticket">
            {{ result.ticket.holder }} · {{ result.ticket.ticket_type }}
          </div>
        </div>
        <div v-else class="result idle">
          <strong>Point the camera at a ticket QR code</strong>
        </div>

        <h3 class="history-title">Recent scans</h3>
        <ul class="history">
          <li v-for="(h, i) in history" :key="i" :class="h.status">
            <span class="mono">{{ time(h.at) }}</span>
            <span>{{ h.ticket?.holder ?? '—' }}</span>
            <span class="spacer" />
            <span class="badge">{{ h.status.replace('_', ' ') }}</span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<style scoped>
.back {
  display: inline-block;
  margin-bottom: 20px;
  text-decoration: none;
}

.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 24px;
  align-items: start;
}

.viewport {
  position: relative;
  aspect-ratio: 1;
  background: var(--ink);
  border-radius: 12px;
  overflow: hidden;
  display: grid;
  place-items: center;
}

video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

video.hidden {
  display: none;
}

.placeholder {
  text-align: center;
  padding: 20px;
}

.frame {
  position: absolute;
  inset: 18%;
  border: 3px solid var(--accent);
  border-radius: 16px;
  box-shadow: 0 0 0 999px rgba(0, 0, 0, 0.35);
}

.stop {
  margin-top: 12px;
}

.manual {
  margin-top: 16px;
  flex-wrap: nowrap;
}

.result {
  display: grid;
  gap: 6px;
  padding: 28px;
  border-radius: var(--radius);
  font-size: 1.05rem;
  background: var(--paper-2);
}

.result strong {
  font: 800 1.6rem/1.1 var(--font-display);
}

.result.ok {
  background: var(--ok);
  color: #fff;
}

.result.already_used {
  background: var(--warn-bg);
  color: var(--warn);
}

.result.invalid,
.result.voided {
  background: var(--bad);
  color: #fff;
}

.result.ok .eyebrow,
.result.invalid .eyebrow,
.result.voided .eyebrow {
  color: rgba(255, 255, 255, 0.75);
}

.history-title {
  margin-top: 28px;
}

.history {
  list-style: none;
  padding: 0;
  margin: 0;
}

.history li {
  display: flex;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px dashed var(--line);
  font-size: 0.92rem;
}

@media (max-width: 760px) {
  .layout {
    grid-template-columns: 1fr;
  }
}
</style>

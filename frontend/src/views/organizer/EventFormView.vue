<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { organizerApi } from '@/api'
import { errorMessage, fieldErrors } from '@/api/http'
import { useToastStore } from '@/stores/toast'
import { fromLocalInput, toLocalInput } from '@/utils/format'

const props = defineProps<{ slug?: string }>()
const router = useRouter()
const toast = useToastStore()

interface TypeRow {
  id?: number
  name: string
  price: string // in hryvnias in the form, converted to kopecks on save
  quantity: number
}

const isEdit = computed(() => Boolean(props.slug))
const form = reactive({
  title: '',
  description: '',
  venue: '',
  city: '',
  starts_at: '',
  ends_at: '',
  cover_url: '',
  ticket_types: [{ name: 'Standard', price: '500', quantity: 100 }] as TypeRow[],
})
const errors = ref<Record<string, string>>({})
const error = ref('')
const busy = ref(false)
const loading = ref(false)

onMounted(async () => {
  if (!props.slug) return
  loading.value = true
  try {
    const e = await organizerApi.event(props.slug)
    Object.assign(form, {
      title: e.title,
      description: e.description,
      venue: e.venue,
      city: e.city,
      starts_at: toLocalInput(e.starts_at),
      ends_at: toLocalInput(e.ends_at),
      cover_url: e.cover_url ?? '',
      ticket_types: (e.ticket_types ?? []).map((t) => ({
        id: t.id,
        name: t.name,
        price: String(t.price / 100),
        quantity: t.quantity,
      })),
    })
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
})

function addType() {
  form.ticket_types.push({ name: '', price: '', quantity: 50 })
}

function removeType(index: number) {
  form.ticket_types.splice(index, 1)
}

async function save() {
  busy.value = true
  errors.value = {}
  error.value = ''
  const payload = {
    title: form.title,
    description: form.description,
    venue: form.venue,
    city: form.city,
    starts_at: fromLocalInput(form.starts_at) ?? '',
    ends_at: fromLocalInput(form.ends_at),
    cover_url: form.cover_url || null,
    ticket_types: form.ticket_types.map((t) => ({
      ...(t.id ? { id: t.id } : {}),
      name: t.name,
      price: Math.round(parseFloat(t.price.replace(',', '.')) * 100) || 0,
      quantity: Number(t.quantity),
    })),
  }
  try {
    const saved = props.slug
      ? await organizerApi.update(props.slug, payload)
      : await organizerApi.create(payload)
    toast.success(props.slug ? 'Event saved.' : 'Draft created. Publish it when you are ready.')
    router.push({ name: 'event-dashboard', params: { slug: saved.slug } })
  } catch (e) {
    errors.value = fieldErrors(e)
    if (!Object.keys(errors.value).length) error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="container page form-page">
    <RouterLink to="/organizer" class="muted back">← My events</RouterLink>
    <h1>{{ isEdit ? 'Edit event' : 'New event' }}</h1>

    <div v-if="loading" class="skeleton" style="height: 400px" />
    <form v-else novalidate @submit.prevent="save">
      <div v-if="error" class="alert alert-error">{{ error }}</div>

      <section class="card">
        <h3>Details</h3>
        <div class="field">
          <label for="title">Title</label>
          <input id="title" v-model="form.title" class="input" required />
          <span v-if="errors.title" class="error-text">{{ errors.title }}</span>
        </div>
        <div class="field">
          <label for="description">Description</label>
          <textarea id="description" v-model="form.description" class="input" rows="5" />
          <span v-if="errors.description" class="error-text">{{ errors.description }}</span>
        </div>
        <div class="grid-2">
          <div class="field">
            <label for="venue">Venue</label>
            <input id="venue" v-model="form.venue" class="input" />
            <span v-if="errors.venue" class="error-text">{{ errors.venue }}</span>
          </div>
          <div class="field">
            <label for="city">City</label>
            <input id="city" v-model="form.city" class="input" />
            <span v-if="errors.city" class="error-text">{{ errors.city }}</span>
          </div>
          <div class="field">
            <label for="starts">Starts</label>
            <input id="starts" v-model="form.starts_at" class="input" type="datetime-local" />
            <span v-if="errors.starts_at" class="error-text">{{ errors.starts_at }}</span>
          </div>
          <div class="field">
            <label for="ends">Ends (optional)</label>
            <input id="ends" v-model="form.ends_at" class="input" type="datetime-local" />
            <span v-if="errors.ends_at" class="error-text">{{ errors.ends_at }}</span>
          </div>
        </div>
        <div class="field">
          <label for="cover">Cover image URL (optional)</label>
          <input
            id="cover"
            v-model="form.cover_url"
            class="input"
            type="url"
            placeholder="https://…"
          />
          <span v-if="errors.cover_url" class="error-text">{{ errors.cover_url }}</span>
        </div>
      </section>

      <section class="card">
        <div class="row">
          <h3>Ticket types</h3>
          <span class="spacer" />
          <button
            type="button"
            class="btn btn-ghost btn-sm"
            :disabled="form.ticket_types.length >= 10"
            @click="addType"
          >
            + Add type
          </button>
        </div>
        <span v-if="errors.ticket_types" class="error-text">{{ errors.ticket_types }}</span>

        <div v-for="(type, i) in form.ticket_types" :key="type.id ?? `new-${i}`" class="type-row">
          <div class="field">
            <label :for="`tn-${i}`">Name</label>
            <input :id="`tn-${i}`" v-model="type.name" class="input" />
            <span v-if="errors[`ticket_types.${i}.name`]" class="error-text">{{
              errors[`ticket_types.${i}.name`]
            }}</span>
          </div>
          <div class="field">
            <label :for="`tp-${i}`">Price, ₴</label>
            <input :id="`tp-${i}`" v-model="type.price" class="input" inputmode="decimal" />
            <span v-if="errors[`ticket_types.${i}.price`]" class="error-text">{{
              errors[`ticket_types.${i}.price`]
            }}</span>
          </div>
          <div class="field">
            <label :for="`tq-${i}`">Quantity</label>
            <input
              :id="`tq-${i}`"
              v-model.number="type.quantity"
              class="input"
              type="number"
              min="1"
            />
            <span v-if="errors[`ticket_types.${i}.quantity`]" class="error-text">{{
              errors[`ticket_types.${i}.quantity`]
            }}</span>
          </div>
          <button
            type="button"
            class="btn btn-danger btn-sm remove"
            :disabled="form.ticket_types.length === 1"
            aria-label="Remove ticket type"
            @click="removeType(i)"
          >
            ✕
          </button>
        </div>
      </section>

      <div class="row">
        <button class="btn btn-accent" :disabled="busy">
          {{ busy ? 'Saving…' : isEdit ? 'Save changes' : 'Create draft' }}
        </button>
        <RouterLink to="/organizer" class="btn btn-ghost">Cancel</RouterLink>
      </div>
    </form>
  </div>
</template>

<style scoped>
.form-page {
  max-width: 820px;
}

.back {
  display: inline-block;
  margin-bottom: 20px;
  text-decoration: none;
}

.card {
  margin-bottom: 20px;
}

.type-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr auto;
  gap: 12px;
  align-items: start;
  padding-top: 12px;
  border-top: 1px dashed var(--line);
  margin-top: 12px;
}

.remove {
  margin-top: 30px;
}

@media (max-width: 640px) {
  .type-row {
    grid-template-columns: 1fr 1fr;
  }

  .type-row .field:first-child {
    grid-column: 1 / -1;
  }
}
</style>

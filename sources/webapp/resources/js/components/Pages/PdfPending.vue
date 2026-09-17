<template>
  <div class="px-4 py-24 text-center bg-white md:px-12">
    <template v-if="timedOut">
      <p class="mb-2 text-lg">{{ $t('pdf_pending_timeout_message') }}</p>

      <button class="ok-button mb-6" @click="start">
        {{ $t('pdf_pending_retry') }}
      </button>
    </template>

    <template v-else>
      <svg class="w-10 h-10 mx-auto mb-6 animate-spin text-black" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle
          cx="12"
          cy="12"
          r="11"
          stroke="currentColor"
          stroke-width="0.8"
          stroke-linecap="round"
          stroke-dasharray="49.8 19.3"></circle>
      </svg>

      <p class="mb-6 text-lg">{{ $t('pdf_pending_message') }}</p>
    </template>

    <div>
      <a href="javascript:history.back()" class="underline">{{ $t('back_to_results') }}</a>
    </div>
  </div>
</template>

<script setup>
  import { onMounted, onUnmounted, ref } from 'vue'

  const props = defineProps({
    statusUrl: { type: String, required: true },
    interval: { type: Number, required: true },
    timeout: { type: Number, required: true }
  })

  const timedOut = ref(false)

  let deadline = 0
  let timer = null

  async function poll() {
    let ready = false

    try {
      const response = await fetch(props.statusUrl, {
        headers: { Accept: 'application/json' },
        cache: 'no-store'
      })

      ready = response.ok && (await response.json()).ready === true
    } catch {
      ready = false
    }

    if (ready) {
      window.location.reload()
      return
    }

    schedule()
  }

  function schedule() {
    if (Date.now() >= deadline) {
      timedOut.value = true
      return
    }

    timer = setTimeout(poll, props.interval * 1000)
  }

  function start() {
    timedOut.value = false
    deadline = Date.now() + props.timeout * 1000
    schedule()
  }

  onMounted(start)

  onUnmounted(() => clearTimeout(timer))
</script>

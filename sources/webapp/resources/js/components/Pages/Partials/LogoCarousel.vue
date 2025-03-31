<template>
    <div class="relative w-full grid mb-4" ref="container">
      <!-- Temporary hidden grid for measuring -->
      <div
        ref="measureGrid"
        class="invisible row-[1/1] col-[1/1] overflow-hidden divide-y divide-gray-800 divide-y-0 grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-px"
      >
        <div v-for="i in 6" :key="i"></div>
      </div>
  
      <!-- Carousel rows -->
      <div
        v-for="(row, rowIndex) in rows"
        :key="rowIndex"
        class="row-[1/1] col-[1/1] transition-opacity duration-500"
        :class="{
          'opacity-100 pointer-events-auto': currentRow === rowIndex,
          'opacity-0 pointer-events-none': currentRow !== rowIndex,
        }"
      >
        <div
          class="overflow-hidden divide-y divide-gray-800 divide-y-0 grid grid-cols-4 md:grid-cols-8 xl:grid-cols-12 gap-px"
        >
          <a
            v-for="(item, i) in row"
            :key="i"
            :href="item.link"
            target="_blank"
            rel="nofollow"
            class="bg-white p-8 flex items-center justify-center aspect-video hover:bg-ok-orange transition h-full col-span-2"
            :class="{
              'col-start-2': rows.length === 1 && i === 0 && row.length % 2,
            }"
          >
            <img
              :src="item.logo"
              :alt="item.title"
              :width="item.width"
            />
          </a>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
  
  const props = defineProps({
    items: {
      type: Array,
      required: true,
    },
  })
  
  const rows = ref([])
  const currentRow = ref(0)
  const measureGrid = ref(null)
  let intervalId
  
  function groupIntoRows() {
    nextTick(() => {
      const gridEl = measureGrid.value
      if (!gridEl) return
  
      const cols = getComputedStyle(gridEl).gridTemplateColumns.split(' ').length
      const temp = []
      for (let i = 0; i < props.items.length; i += cols) {
        temp.push(props.items.slice(i, i + cols))
      }
      rows.value = temp
    })
  }
  
  function setup() {
    groupIntoRows()
    currentRow.value = 0
  }
  
  onMounted(() => {
    setup()
    intervalId = setInterval(() => {
      if (!rows.value.length) return
      currentRow.value = (currentRow.value + 1) % rows.value.length
    }, 5000)
    window.addEventListener('resize', setup)
  })
  
  onBeforeUnmount(() => {
    clearInterval(intervalId)
    window.removeEventListener('resize', setup)
  })
  
  watch(() => props.items, setup)
  </script>
  
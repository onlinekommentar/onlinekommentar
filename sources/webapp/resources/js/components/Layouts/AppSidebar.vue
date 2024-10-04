<template>
  <div id="sidebar" :class="{ 'open': isSidebarOpen }" class="fixed top-0 bottom-0 z-50 w-6 h-screen xl:w-12 xl:left-0 -left-4 md:left-0 bg-ok-blue rounded-tr-xl rounded-br-xl print:hidden">
    <div id="shadow" class="absolute top-0 bottom-0 left-0 hidden w-2 h-screen bg-transparent xl:block">
    </div>
    
    <div v-if="isSidebarOpen" class="h-screen overflow-y-auto">
      <div class="block px-8 py-4">
        <img src="/img/ok-logo.svg" alt="Onlinekommentar – der frei zugängliche Rechtskommenter" class="w-12 mt-2 md:w-20" />
      </div>

      <div class="mt-4 ml-4">
        <slot name="content" />
      </div>
    </div>
    
    <!-- sidebar handle -->
    <div @click="toggleSidebar" id="handle" class="absolute left-full top-1/2 -ml-2 md:-ml-7 rounded-bl-lg rounded-br-lg bg-ok-blue">
      <div class="flex items-center gap-2 w-max py-1.5 px-4 md:gap-4 md:py-3 md:px-8">
        <img class="w-4 md:w-6 rotate-90" src="/img/sidebar-handle.svg" alt="{{ $t('commentaries') }}">
        <div class="text-xs font-medium tracking-wider uppercase">
          {{ $t('commentaries') }}
        </div>
      </div>
    </div>
    <!-- end sidebar handle -->
  </div>
</template>

<script setup>
  import { ref, onMounted } from 'vue'

  const isSidebarOpen = ref(false)
  
  const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value
  }

  onMounted(() => {
    if (window.matchMedia('(min-width: 768px)').matches) {
      window.addEventListener('toggleMenu', (event) => {
        isSidebarOpen.value = event.detail;
      })
    }
  })
</script>

<style lang="postcss" scoped>
  #shadow {
    box-shadow: inset 10px 0 10px -10px rgba(0, 0, 0, 0.4);
  }

  #sidebar {
    box-shadow: 5px 0 10px -3px rgba(0, 0, 0, 0.1);
    transition: width .3s;

    &.open {
      width: 680px;
      max-width: 95%;
      transition: width .3s;
    }
  }

  #handle {
    cursor: pointer;
    transform-origin: top left;
    transform: rotate(-90deg) translateX(-50%);
  }
</style>
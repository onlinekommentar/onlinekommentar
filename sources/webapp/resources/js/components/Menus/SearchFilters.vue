<template>
  <div class="flex max-md:flex-col gap-2 md:grow">
    <FlyoutMenuWithDividers
      class="md:w-[400px] md:grow-[4] rounded-md uppercase tracking-wider"
      :label="$t('legal_domain_filter_label')"
      :options="legalDomains"
      :active-option="activeLegalDomain"
      @changed="onFilter('legal_domain', $event)"
    />
    <FlyoutMenuWithDividers
      class="md:w-[300px] md:grow-[3] rounded-md uppercase tracking-wider"
      :label="$t('editor_filter_label')"
      :options="editors"
      :active-option="activeEditor"
      @changed="onFilter('editor', $event)"
    />
    <FlyoutMenuWithDividers
      class="md:w-[300px] md:grow-[3] rounded-md uppercase tracking-wider"
      :label="$t('legal_domain_filter_label')"
      :options="authors"
      :active-option="activeAuthor"
      @changed="onFilter('author', $event)"
    />
    <FlyoutMenuWithDividers
      v-if="sorts.length > 0"
      class="md:w-[200px] md:grow-[2] rounded-md uppercase tracking-wider"
      :label="$t('sort_label')"
      :options="sorts"
      :active-option="activeSort"
      @changed="onFilter('sort', $event)"
    />
  </div>
</template>

<script setup>
  import { ref } from 'vue'  
  import FlyoutMenuWithDividers from '@/components/Menus/FlyoutMenuWithDividers'

  const props = defineProps({
    query: { type: String, required: true },
    legalDomains: { type: Array, required: false, default: [] },
    legalDomain: { type: Object, required: false },
    editors: { type: Array, required: false, default: [] },
    editor: { type: Object, required: false },
    authors: { type: Array, required: false, default: [] },
    author: { type: Object, required: false },
    sorts: { type: Array, required: false, default: [] },
    sort: { type: Object, required: false },
  })

  const activeLegalDomain = ref(props.legalDomain ?? props.legalDomains[0])
  const activeEditor = ref(props.editor ?? props.editors[0])
  const activeAuthor = ref(props.author ?? props.authors[0])
  const activeSort = ref(props.sort ?? props.sorts[0])

  const onFilter = (name, value) => {
    const qs = new URLSearchParams(window.location.search)
    qs.set(name, value.id ?? '')
    window.location.href = `?${qs.toString()}`
  }
</script>
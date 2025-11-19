<template>
  <div class="bootstrap m-2">
    <div class="card rounded">
      <div
        class="card-header bg-primary text-white d-flex justify-content-between align-items-center mb-1"
      >
        <div class="font-weight-bolder">
          {{ $t('content.public') }}
        </div>
      </div>
      <div class="card-body bg-whientryte mb-2">
        <b-button
          v-if="mayCreateContent"
          v-b-tooltip.hover="$t('content.new')"
          class="write-new mb-2"
          variant="primary"
          size="sm"
          :href="$url('contentNew')"
          :disabled="isLoading"
        >
          <i class="fas faw fa-plus" /> {{ $t('content.new') }}
        </b-button>

        <b-table
          :fields="tableFields"
          :items="contentList"
          small
          hover
          responsive
          :show-empty="true"
          :busy="isLoading"
        >
          <template #cell(name)="entry">
            <a
              v-if="mayEditContent"
              v-b-tooltip="$t('content.edit')"
              :href="$url('contentEditEntry', entry.item.id)"
            >
              {{ entry.item.name }}
            </a>
            <div v-else>
              {{ entry.item.name }}
            </div>
          </template>
          <template #cell(buttons)="entry">
            <b-button
              v-if="mayEditContent"
              v-b-tooltip="$t('content.delete_tooltip')"
              href="#"
              size="sm"
              class="mx-1"
              variant="outline-danger"
              @click.prevent="deleteContent(entry.item.id, entry.item.name)"
            >
              <i class="fas fa-fw fa-trash-alt" />
            </b-button>
          </template>
          <template #empty>
            <div class="empty-message">
              {{ $t('content.empty') }}
            </div>
          </template>
        </b-table>

        <div
          v-if="isLoading"
          class="loader-container mx-auto"
        >
          <i class="fas fa-spinner fa-spin" />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { pulseError } from '@/script'
import i18n from '@/helper/i18n'
import { deleteContent, listContent } from '@/api/content'
import { BButton, BTable } from 'bootstrap-vue'
import useConfirmationDialogue from '@/composables/useConfirmationDialogue'

export default {
  components: { BButton, BTable },
  props: {
    mayCreateContent: { type: Boolean, default: false },
    mayEditContent: { type: Boolean, default: false },
  },
  setup () {
    const { confirmationDialogue } = useConfirmationDialogue()
    return { confirmationDialogue }
  },
  data () {
    return {
      isLoading: false,
      contentList: [],
      tableFields: [
        { key: 'id', lrightabel: i18n('content.id'), sortable: true },
        { key: 'name', label: i18n('content.name'), sortable: true },
        { key: 'title', label: i18n('content.title'), sortable: true },
        { key: 'lastModified', label: i18n('content.last_modified'), sortable: true, formatter: this.formatDate },
        { key: 'buttons', label: '' },
      ],
    }
  },
  async mounted () {
    this.isLoading = true

    try {
      this.contentList = await listContent()
    } catch (e) {
      pulseError(i18n('error_unexpected'))
    }

    this.isLoading = false
  },
  methods: {
    formatDate (date) {
      return date !== null
        ? this.$dateFormatter.format(date, {
          day: 'numeric',
          month: 'long',
          year: 'numeric',
          hour: 'numeric',
          minute: 'numeric',
        })
        : '---'
    },
    async deleteContent (id, name) {
      if (!await this.confirmationDialogue('content.delete', { params: { name } })) return
      this.isLoading = true

      // delete the content on the server and remove it from the list
      try {
        await deleteContent(id)
        const index = this.contentList.findIndex(entry => entry.id === id)
        if (index >= 0) {
          this.contentList.splice(index, 1)
        }
      } catch (e) {
        pulseError(i18n('error_unexpected'))
      }

      this.isLoading = false
    },
  },
}
</script>

<style lang="scss" scoped>
.empty-message {
  margin-top: 10px;
  text-align: center;
}
</style>

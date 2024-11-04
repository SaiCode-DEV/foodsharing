<template>
  <div>
    <Container :title="$i18n('store_categories.title')" wrap-content>
      <div class="m-2">
        <b-button
          v-b-tooltip.hover="$i18n('store_categories.new')"
          class="write-new mb-2"
          variant="primary"
          size="sm"
          :disabled="isLoading"
          @click="showEditModal(null)"
        >
          <i class="fas faw fa-plus" /> {{ $i18n('store_categories.new') }}
        </b-button>
      </div>
      <b-table
        :items="categories"
        :fields="fields"
        small
        hover
        responsive
        :show-empty="true"
        :busy="isLoading"
      >
        <template #cell(buttons)="entry">
          <OverflowMenu
            class="d-inline m-auto text-nowrap"
            :options="overflowMenuOptions(entry.item)"
          />
        </template>
        <template #empty>
          <div class="empty-message">
            {{ $i18n('store_categories.empty') }}
          </div>
        </template>
      </b-table>
      <div
        v-if="isLoading"
        class="loader-container mx-auto"
      >
        <i class="fas fa-spinner fa-spin" />
      </div>
    </Container>

    <b-modal
      ref="edit_category_modal"
      :title="$i18n(editingCategoryId ? 'store_categories.edit' : 'store_categories.new')"
      :cancel-title="$i18n('button.cancel')"
      :ok-title="$i18n('button.save')"
      modal-class="bootstrap"
      header-class="d-flex"
      content-class="pr-3 pt-3"
      centered
      @ok="editingCategoryId ? editCategory() : addCategory()"
      @shown="$refs.edit_modal_input.focus()"
    >
      <b-form-input ref="edit_modal_input" v-model="editingCategoryName" />
    </b-modal>
  </div>
</template>

<script>
import { pulseError } from '@/script'
import Container from '@/components/Container/Container.vue'
import StoreCategoriesData from '@/stores/storecategories'
import OverflowMenu from '@/components/OverflowMenu.vue'

export default {
  components: { Container, OverflowMenu },
  data () {
    return {
      isLoading: false,
      fields: [
        { key: 'id', label: this.$i18n('store_categories.columns.id'), sortable: true },
        { key: 'name', label: this.$i18n('store_categories.columns.name'), sortable: true },
        { key: 'numberOfStores', label: this.$i18n('store_categories.columns.number_of_stores'), sortable: true },
        { key: 'buttons', label: '' },
      ],
      editingCategoryId: null,
      editingCategoryName: null,
    }
  },
  computed: {
    categories () {
      return StoreCategoriesData.getters.getCategories()
    },
  },
  async mounted () {
    this.isLoading = true

    try {
      await StoreCategoriesData.mutations.fetchCategories()
    } catch (e) {
      pulseError(this.$i18n('error_unexpected'))
    }

    this.isLoading = false
  },
  methods: {
    overflowMenuOptions (category) {
      return [
        { icon: 'pencil-alt', textKey: 'store_categories.edit', callback: () => this.showEditModal(category) },
        { icon: 'trash-alt', textKey: 'store_categories.delete', callback: () => this.deleteCategory(category) },
      ]
    },
    async showEditModal (category) {
      // if category is null, a new category is meant to be added
      if (category) {
        this.editingCategoryId = category.id
        this.editingCategoryName = category.name
      } else {
        this.editingCategoryId = null
        this.editingCategoryName = ''
      }
      this.$refs.edit_category_modal.show()
    },
    async addCategory () {
      if (this.editingCategoryName && this.editingCategoryName.length > 0) {
        this.isLoading = true
        try {
          await StoreCategoriesData.mutations.addCategory(this.editingCategoryName)
        } catch (e) {
          pulseError(this.$i18n('error_unexpected'))
        }
        this.editingCategoryName = null
        this.isLoading = false
      }
    },
    async editCategory () {
      if (this.editingCategoryId) {
        this.isLoading = true
        try {
          await StoreCategoriesData.mutations.editCategory(this.editingCategoryId, this.editingCategoryName)
        } catch (e) {
          pulseError(this.$i18n('error_unexpected'))
        }
        this.editingCategoryId = null
        this.isLoading = false
      }
    },
    async deleteCategory (category) {
      let message = this.$i18n('store_categories.confirm_delete', { name: category.name })
      if (category.numberOfStores > 0) {
        message += ' ' + this.$i18n('store_categories.confirm_delete_hint', { count: category.numberOfStores })
      }
      const confirmed = await this.$bvModal.msgBoxConfirm(message, {
        title: this.$i18n('are_you_sure'),
        okVariant: 'danger',
        okTitle: this.$i18n('button.delete'),
        cancelTitle: this.$i18n('button.cancel'),
        centered: true,
      })
      if (confirmed) {
        try {
          await StoreCategoriesData.mutations.removeCategory(category.id)
        } catch (e) {
          pulseError(this.$i18n('error_unexpected'))
        }
      }
    },
  },
}
</script>

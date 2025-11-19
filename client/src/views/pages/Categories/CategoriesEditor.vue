<template>
  <div>
    <Container :title="$t(`categories.title.${categoryType}`)">
      <div class="list-group-item">
        <b-table
          class="category-table mb-0"
          :items="categories"
          :fields="fields"
          small
          hover
          responsive
          :show-empty="true"
          :busy="isLoading"
          sort-icon-left
        >
          <template #cell(buttons)="entry">
            <OverflowMenu
              class="d-inline m-auto text-nowrap"
              :options="overflowMenuOptions(entry.item)"
            />
          </template>
          <template #empty>
            <div class="empty-message">
              {{ $t('categories.empty') }}
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
      <ContainerButton
        variant="success"
        text-key="categories.new"
        icon="fas fa-plus"
        @click="showEditModal(null)"
      />
    </Container>

    <b-modal
      ref="edit_category_modal"
      :title="$t(editingCategoryId ? 'categories.edit' : 'categories.new')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.save')"
      centered
      @ok="editingCategoryId ? editCategory() : addCategory()"
      @shown="$refs.edit_modal_input.focus()"
    >
      <b-form-input ref="edit_modal_input" v-model="editingCategoryName" />
    </b-modal>

    <b-modal
      ref="merge_category_modal"
      :title="$t('categories.merge_title')"
      :cancel-title="$t('button.cancel')"
      :ok-title="$t('button.save')"
      :ok-disabled="!sourceCategory"
      ok-variant="danger"
      centered
      @ok="mergeCategories()"
    >
      <p>{{ $t('categories.merge_hint', { name: targetCategory?.name }) }}</p>
      <b-form-select
        v-model="sourceCategory"
        :options="categories.map(c => ({ value: c, text: c.name , disabled: c.id === targetCategory?.id }))"
      />
    </b-modal>
  </div>
</template>

<script>
import { pulseError } from '@/script'
import Container from '@/components/Container/Container.vue'
import StoreCategoriesData from '@/stores/categories'
import OverflowMenu from '@/components/OverflowMenu.vue'
import ContainerButton from '@/components/Container/ContainerButton.vue'

export default {
  components: { Container, OverflowMenu, ContainerButton },
  props: {
    categoryType: { type: String, required: true },
  },
  data () {
    return {
      isLoading: false,
      fields: [
        { key: 'id', label: this.$t('categories.columns.id'), sortable: true },
        { key: 'name', label: this.$t('categories.columns.name'), sortable: true },
        { key: 'usageCount', label: this.$t('categories.columns.usage_count'), sortable: true },
        { key: 'buttons', label: '' },
      ],
      editingCategoryId: null,
      editingCategoryName: null,
      sourceCategory: null,
      targetCategory: null,
    }
  },
  computed: {
    categories () {
      return StoreCategoriesData.getters.getCategories(this.categoryType)
    },
  },
  async mounted () {
    this.isLoading = true

    try {
      await StoreCategoriesData.mutations.fetchCategories(this.categoryType)
    } catch (e) {
      pulseError(this.$t('error_unexpected'))
    }

    this.isLoading = false
  },
  methods: {
    overflowMenuOptions (category) {
      return [
        { icon: 'pencil-alt', textKey: 'categories.edit', callback: () => this.showEditModal(category) },
        { icon: 'trash-alt', textKey: 'categories.delete', callback: () => this.deleteCategory(category) },
        { icon: 'code-merge', textKey: 'categories.merge', callback: () => this.showMergeModal(category) },
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
          await StoreCategoriesData.mutations.addCategory(this.categoryType, this.editingCategoryName)
        } catch (e) {
          pulseError(this.$t('error_unexpected'))
        }
        this.editingCategoryName = null
        this.isLoading = false
      }
    },
    async editCategory () {
      if (this.editingCategoryId) {
        this.isLoading = true
        try {
          await StoreCategoriesData.mutations.editCategory(this.categoryType, this.editingCategoryId, this.editingCategoryName)
        } catch (e) {
          pulseError(this.$t('error_unexpected'))
        }
        this.editingCategoryId = null
        this.isLoading = false
      }
    },
    async deleteCategory (category) {
      let message = this.$t('categories.confirm_delete', { name: category.name })
      if (category.numberOfStores > 0) {
        message += ' ' + this.$t(`categories.confirm_delete_hint.${this.categoryType}`, { count: category.usageCount })
      }
      const confirmed = await this.$bvModal.msgBoxConfirm(message, {
        title: this.$t('are_you_sure'),
        okVariant: 'danger',
        okTitle: this.$t('button.delete'),
        cancelTitle: this.$t('button.cancel'),
        centered: true,
      })
      if (confirmed) {
        try {
          await StoreCategoriesData.mutations.removeCategory(this.categoryType, category.id)
        } catch (e) {
          pulseError(this.$t('error_unexpected'))
        }
      }
    },
    async showMergeModal (category) {
      this.targetCategory = category
      this.$refs.merge_category_modal.show()
    },
    async mergeCategories () {
      if (this.targetCategory) {
        try {
          await StoreCategoriesData.mutations.mergeCategories(this.categoryType, this.sourceCategory.id, this.targetCategory.id)
          this.$refs.merge_category_modal.hide()
          this.sourceCategory = null
          this.targetCategory = null
        } catch (e) {
          pulseError(this.$t('error_unexpected'))
        }
      }
    },
  },
}
</script>
<style lang="css">
.category-table th {
  border-top: none;
}
</style>

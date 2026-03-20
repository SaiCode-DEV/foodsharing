<template>
  <div class="oauth-clients-admin card">
    <div class="card-header">
      <h3>{{ $t('oauth.admin.title') }}</h3>
      <b-button
        variant="primary"
        @click="showCreateModal"
      >
        <i class="fas fa-plus" /> {{ $t('oauth.admin.create_client') }}
      </b-button>
    </div>

    <div class="card-body">
      <b-alert
        v-if="error"
        variant="danger"
        dismissible
        @dismissed="error = null"
      >
        {{ error }}
      </b-alert>

      <b-alert
        v-if="success"
        variant="success"
        dismissible
        @dismissed="success = null"
      >
        {{ success }}
      </b-alert>

      <b-table
        :items="clients"
        :fields="fields"
        :busy="loading"
        striped
        hover
        responsive
      >
        <template #cell(active)="data">
          <b-badge :variant="data.value ? 'success' : 'secondary'">
            {{ data.value ? $t('yes') : $t('no') }}
          </b-badge>
        </template>

        <template #cell(confidential)="data">
          <b-badge :variant="data.value ? 'info' : 'secondary'">
            {{ data.value ? $t('oauth.admin.confidential') : $t('oauth.admin.public') }}
          </b-badge>
        </template>

        <template #cell(scopes)="data">
          <b-badge
            v-for="scope in data.value"
            :key="scope"
            variant="secondary"
            class="mr-1"
          >
            {{ scope }}
          </b-badge>
        </template>

        <template #cell(required_region_ids)="data">
          <span v-if="!data.value || data.value.length === 0">
            <i class="text-muted">{{ $t('oauth.admin.no_restrictions') }}</i>
          </span>
          <b-badge
            v-else
            variant="warning"
            :title="$t('oauth.admin.region_restriction_tooltip')"
          >
            {{ data.value.length }} {{ $t('oauth.admin.regions') }}
          </b-badge>
        </template>

        <template #cell(actions)="data">
          <b-button
            size="sm"
            variant="outline-primary"
            @click="editClient(data.item)"
          >
            <i class="fas fa-edit" />
          </b-button>
          <b-button
            size="sm"
            variant="outline-danger"
            class="ml-1"
            @click="confirmDelete(data.item)"
          >
            <i class="fas fa-trash" />
          </b-button>
        </template>
      </b-table>
    </div>

    <!-- Create/Edit Modal -->
    <b-modal
      v-model="showModal"
      :title="editingClient ? $t('oauth.admin.edit_client') : $t('oauth.admin.create_client')"
      size="lg"
      no-close-on-backdrop
      :hide-header-close="newSecret && !confirmSaveSecret"
      :cancel-disabled="newSecret && !confirmSaveSecret"
      :ok-disabled="!formValid || (newSecret && !confirmSaveSecret)"
      @ok="handleModalOk"
    >
      <b-form v-if="!loading && !newSecret">
        <b-form-group :label="$t('oauth.admin.identifier')" label-for="identifier">
          <b-form-input
            id="identifier"
            v-model="formData.identifier"
            :disabled="!!editingClient"
            required
          />
        </b-form-group>

        <b-form-group :label="$t('oauth.admin.name')" label-for="name">
          <b-form-input
            id="name"
            v-model="formData.name"
            required
          />
        </b-form-group>

        <b-form-group v-if="!editingClient">
          <b-form-checkbox v-model="formData.confidential">
            {{ $t('oauth.admin.confidential_client') }}
          </b-form-checkbox>
          <small class="text-muted">{{ $t('oauth.admin.confidential_help') }}</small>
        </b-form-group>

        <b-form-group v-if="editingClient">
          <b-form-checkbox v-model="formData.active">
            {{ $t('oauth.admin.active') }}
          </b-form-checkbox>
        </b-form-group>

        <b-form-group :label="$t('oauth.admin.redirect_uris')" label-for="redirect_uris">
          <b-form-tags
            id="redirect_uris"
            v-model="formData.redirect_uris"
            separator=" ,;"
            placeholder="https://example.com/callback"
            :add-on-change="true"
            :state="redirectUrisValid"
          />
          <b-form-invalid-feedback v-if="!redirectUrisValid">
            {{ $t('oauth.admin.redirect_uris_invalid') }}
          </b-form-invalid-feedback>
          <small class="text-muted">{{ $t('oauth.admin.redirect_uris_help') }}</small>
        </b-form-group>
        <b-form-group :label="$t('oauth.admin.scopes')" label-for="scopes">
          <b-form-checkbox-group
            id="scopes"
            v-model="formData.scopes"
            :options="availableScopes"
            stacked
          />
        </b-form-group>

        <b-form-group :label="$t('oauth.admin.grant_types')" label-for="grant_types">
          <b-form-checkbox-group
            id="grant_types"
            v-model="formData.grant_types"
            :options="availableGrantTypes"
            stacked
          />
        </b-form-group>

        <b-form-group :label="$t('oauth.admin.required_regions')" label-for="required_region_ids">
          <div class="d-flex gap-2 justify-content-between">
            <RegionTree
              :include-working-groups="true"
              @change="onRegionTreeChange"
            />
            <div class="d-flex flex-column align-items-end">
              <b-button
                class="mb-2"
                size="sm"
                variant="success"
                :disabled="!selectedRegion || (formData.required_region_ids && formData.required_region_ids.includes(selectedRegion.id))"
                @click="addSelectedRegion"
              >
                <i class="fas fa-plus" /> {{ $t('add') }}
              </b-button>
              <b-list-group>
                <b-list-group-item
                  v-for="region in selectedRegionsList"
                  :key="region.id"
                  class="d-flex justify-content-between align-items-center"
                >
                  <span>{{ region.name }}</span>
                  <div class="ml-2 d-flex align-items-center">
                    <code class="text-muted">{{ region.id }}</code>
                    <b-button
                      size="xs"
                      variant="danger"
                      class="ml-1 py-0 px-1"
                      @click="removeRegion(region.id)"
                    >
                      <i class="fas fa-times" />
                    </b-button>
                  </div>
                </b-list-group-item>
              </b-list-group>
            </div>
          </div>
          <small class="text-muted">{{ $t('oauth.admin.required_regions_help') }}</small>
        </b-form-group>

        <b-form-group v-if="editingClient && editingClient.confidential">
          <b-form-checkbox v-model="formData.regenerate_secret">
            {{ $t('oauth.admin.regenerate_secret') }}
          </b-form-checkbox>
          <small :class="formData.regenerate_secret ? 'text-danger' : 'text-muted'">{{ $t('oauth.admin.regenerate_secret_warning') }}</small>
        </b-form-group>
      </b-form>

      <b-alert
        v-if="newSecret"
        variant="warning"
        show
        class="mt-3"
      >
        <h5>{{ $t('oauth.admin.secret_generated') }}</h5>
        <p>{{ $t('oauth.admin.secret_save_warning') }}</p>
        <pre class="bg-dark text-light p-2">{{ newSecret }}</pre>

        <b-form-checkbox
          v-model="confirmSaveSecret"
        >
          {{ $t('oauth.admin.confirm_save_secret') }}
        </b-form-checkbox>
      </b-alert>
    </b-modal>

    <!-- Delete Confirmation Modal -->
    <b-modal
      v-model="showDeleteModal"
      :title="$t('oauth.admin.confirm_delete')"
      @ok="deleteClient"
    >
      <p>{{ $t('oauth.admin.delete_warning', { name: deletingClient?.name }) }}</p>
    </b-modal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import i18n from '@/helper/i18n'
import { get, post, patch, remove } from '@/api/base'
import { BAlert, BBadge, BButton, BForm, BFormCheckbox, BFormCheckboxGroup, BFormGroup, BFormInput, BFormTags, BModal, BTable } from 'bootstrap-vue'
import RegionTree from '@/components/regiontree/RegionTree.vue'
import { getPublicRegionData } from '@/api/regions'

// Multi-region selection logic
const selectedRegion = ref(null)

const clients = ref([])
const loading = ref(false)
const error = ref(null)
const success = ref(null)
const showModal = ref(false)
const showDeleteModal = ref(false)
const editingClient = ref(null)
const deletingClient = ref(null)
const newSecret = ref(null)
const confirmSaveSecret = ref(false)
const selectedRegionsList = ref([])

const availableScopes = [
  { value: 'openid', text: 'openid' },
  { value: 'profile', text: 'profile' },
  { value: 'email', text: 'email' },
  { value: 'regions', text: 'regions' },
]

const availableGrantTypes = [
  { value: 'authorization_code', text: 'authorization_code' },
  { value: 'refresh_token', text: 'refresh_token' },
  { value: 'client_credentials', text: 'client_credentials' },
]

const fields = computed(() => [
  { key: 'identifier', label: i18n('oauth.admin.identifier'), sortable: true },
  { key: 'name', label: i18n('oauth.admin.name'), sortable: true },
  { key: 'active', label: i18n('oauth.admin.active'), sortable: true },
  { key: 'confidential', label: i18n('oauth.admin.type'), sortable: true },
  { key: 'scopes', label: i18n('oauth.admin.scopes') },
  { key: 'required_region_ids', label: i18n('oauth.admin.restrictions') },
  { key: 'actions', label: i18n('oauth.admin.actions') },
])

function onRegionTreeChange (node) {
  if (node && node.states && node.states.id) {
    selectedRegion.value = { id: node.states.id, name: node.text }
  } else {
    selectedRegion.value = null
  }
}

const redirectUrisValid = computed(() => {
  if (!formData.value.redirect_uris || formData.value.redirect_uris.length === 0) {
    return true
  }
  return formData.value.redirect_uris.every(uri => {
    if (typeof uri !== 'string') return false
    if (!/^https?:\/\//.test(uri)) return false
    try {
      // eslint-disable-next-line no-new
      new URL(uri)
      return true
    } catch (e) {
      return false
    }
  })
})

const formValid = computed(() => {
// Required fields: identifier, name, redirect_uris valid
  if (!formData.value.identifier || !formData.value.name) return false
  if (!redirectUrisValid.value) return false
  // Optionally, check for at least one redirect URI
  if (!formData.value.redirect_uris || formData.value.redirect_uris.length === 0) return false
  return true
})

function addSelectedRegion () {
  if (!selectedRegion.value) return
  if (!formData.value.required_region_ids) formData.value.required_region_ids = []
  if (!formData.value.required_region_ids.includes(selectedRegion.value.id)) {
    formData.value.required_region_ids.push(selectedRegion.value.id)
  }
  selectedRegionsList.value.push({
    id: selectedRegion.value.id,
    name: selectedRegion.value.name,
  })
}

function removeRegion (id) {
  if (!formData.value.required_region_ids) return
  formData.value.required_region_ids = formData.value.required_region_ids.filter(rid => rid !== id)
  selectedRegionsList.value = selectedRegionsList.value.filter(region => region.id !== id)
}

function getEmptyFormData () {
  return {
    identifier: '',
    name: '',
    confidential: true,
    active: true,
    redirect_uris: [],
    scopes: ['openid', 'profile', 'email'],
    grant_types: ['authorization_code', 'refresh_token'],
    required_region_ids: null,
    regenerate_secret: false,
  }
}

const formData = ref(getEmptyFormData())

async function loadClients () {
  loading.value = true
  try {
    clients.value = await get('/admin/oauthclients')
  } catch (err) {
    error.value = i18n('oauth.admin.load_error')
    console.error(err)
  } finally {
    loading.value = false
  }
}

function showCreateModal () {
  editingClient.value = null
  formData.value = getEmptyFormData()
  newSecret.value = null
  showModal.value = true
}

async function editClient (client) {
  editingClient.value = client
  formData.value = {
    identifier: client.identifier,
    name: client.name,
    confidential: client.confidential,
    active: client.active,
    redirect_uris: [...client.redirect_uris],
    scopes: [...client.scopes],
    grant_types: [...client.grant_types],
    required_region_ids: client.required_region_ids ? [...client.required_region_ids] : [],
    regenerate_secret: false,
  }
  newSecret.value = null
  showModal.value = true
  selectedRegionsList.value = await Promise.all((client.required_region_ids ?? []).map(async (id) => {
    const region = await getPublicRegionData(id)
    return { id: region.id, name: region.name }
  }))
}

async function saveClient (evt) {
  evt.preventDefault()
  if (!redirectUrisValid.value) {
    error.value = i18n('oauth.admin.redirect_uris_invalid')
    return
  }
  loading.value = true
  try {
    if (editingClient.value) {
      // Update existing client
      const updateData = {
        name: formData.value.name,
        active: formData.value.active,
        redirect_uris: formData.value.redirect_uris,
        scopes: formData.value.scopes,
        grant_types: formData.value.grant_types,
        required_region_ids: formData.value.required_region_ids && formData.value.required_region_ids.length > 0
          ? formData.value.required_region_ids
          : null,
        regenerate_secret: formData.value.regenerate_secret,
      }
      const result = await patch(`/admin/oauthclients/${editingClient.value.identifier}`, updateData)
      if (result.secret) {
        newSecret.value = result.secret
        confirmSaveSecret.value = false
        return
      }
      success.value = i18n('oauth.admin.update_success')
    } else {
      // Create new client
      const result = await post('/admin/oauthclients', {
        identifier: formData.value.identifier,
        name: formData.value.name,
        confidential: formData.value.confidential,
        redirect_uris: formData.value.redirect_uris,
        scopes: formData.value.scopes,
        grant_types: formData.value.grant_types,
        required_region_ids: formData.value.required_region_ids && formData.value.required_region_ids.length > 0
          ? formData.value.required_region_ids
          : null,
      })
      if (result.secret) {
        newSecret.value = result.secret
        confirmSaveSecret.value = false
        return
      }
      success.value = i18n('oauth.admin.create_success')
    }
    showModal.value = false
    await loadClients()
  } catch (err) {
    error.value = err.message || i18n('oauth.admin.save_error')
    console.error(err)
  } finally {
    loading.value = false
  }
}

function handleModalOk (evt) {
  // If a new secret is shown, require confirmation before closing
  if (newSecret.value && !confirmSaveSecret.value) {
    evt.preventDefault()
    return
  }
  if (!newSecret.value) {
    saveClient(evt)
  } else {
    // User confirmed, close modal and reset
    showModal.value = false
    confirmSaveSecret.value = false
    newSecret.value = null
    success.value = null
    error.value = null
    loadClients()
  }
}

function confirmDelete (client) {
  deletingClient.value = client
  showDeleteModal.value = true
}

async function deleteClient () {
  try {
    await remove(`/admin/oauthclients/${deletingClient.value.identifier}`)
    success.value = i18n('oauth.admin.delete_success')
    await loadClients()
  } catch (err) {
    error.value = i18n('oauth.admin.delete_error')
    console.error(err)
  } finally {
    deletingClient.value = null
  }
}

onMounted(loadClients)
</script>

<style scoped>
.oauth-clients-admin .card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

pre {
  word-break: break-all;
  white-space: pre-wrap;
}
</style>

import { get, patch } from './base'
export async function fetchAllFeatureToggles () {
  return get('/feature-toggles')
}

export async function fetchFeatureToggle (featureToggleIdentifier) {
  return get(`/feature-toggles/${featureToggleIdentifier}`)
}

export async function switchFeatureToggleState (featureToggleIdentifier) {
  return patch(`/feature-toggles/${featureToggleIdentifier}`)
}

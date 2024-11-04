# Datastores

Stores are simply a middleman, to keep the frontend separated from the API also provide the possibility to reduce API calls to the backend because each component has the possibility to access the store data. Data is also between each accessing component, synchronized when a change happens.

```plantuml
"Components" --> "Stores": Mutations
"Components" <-- "Stores": Getter

"Stores" --> "Stores": Mutations
"Stores" <-- "Stores": Getter

"Stores" --> "API-Wrappers": Call
"Stores" <-- "API-Wrappers": Response

"API-Wrappers" --> "ENDPOINTs": Request
"API-Wrappers" <-- "ENDPOINTs": Response
```

# Getter / Mutations
This convention increases the understanding of the usage of functions, if they are changing something or only showing a value.

## Getters
These functions should be used to check, filter or get values like `isFoodsaver()`, `hasID(1337)` or `getConversationByID(1337)`.

## Mutations
These functions should be used when manipulating something, like `fetchUserDetailsData()`, `setAcceptedStatus(id)` or `updateReadStatus(id)`


# Basic Store

## Setup
Located at [client/src/stores](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/client/src/stores)

```js
// example.js
import Vue from 'vue'

export const store = Vue.observable({
  state: false,
})

export const getters = {
  getState: () => store.state,
}

export const mutations = {
  async toggle() {
    store.state = !store.state
  },
}

export default { store, getters, mutations }
```

## Usage
```js
// example.vue
import DataStore from '@/stores/example.js'

console.log('get', DataStore.getters.getState())
DataStore.mutations.toggle()
console.log('mutations', DataStore.getters.getState())
```

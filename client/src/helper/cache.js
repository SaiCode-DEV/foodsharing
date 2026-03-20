const cacheName = 'foodsharing.network'

// Helper to extract cache key from string or cache config object
function getCacheKeyString (cacheRequestName) {
  return typeof cacheRequestName === 'string'
    ? cacheRequestName
    : cacheRequestName.cacheKey
}

export async function getCacheAge (cacheRequestName) {
  const key = getCacheKeyString(cacheRequestName)
  const cacheRequestNameWithSlash = '/' + key
  const lastFetchTimeRequestName = `${cacheRequestNameWithSlash}_lastFetchTime`
  let cachedLastFetchTime = 0
  try {
    const cache = await caches.open(cacheName)
    cachedLastFetchTime = await cache.match(lastFetchTimeRequestName)
  } catch (error) {
    console.error(`Error by call cache entry ${lastFetchTimeRequestName}:`, error)
  }
  const lastFetchTime = cachedLastFetchTime ? parseInt(await cachedLastFetchTime.text()) : 0
  const currentTime = Date.now()
  return currentTime - lastFetchTime
}

export async function getCacheInterval (cacheRequestName, rateLimitInterval) {
  return (await getCacheAge(cacheRequestName)) >= rateLimitInterval
}

export async function setCache (cacheRequestName, cacheValue) {
  const key = getCacheKeyString(cacheRequestName)
  const cacheRequestNameWithSlash = '/' + key

  try {
    const cache = await caches.open(cacheName)
    await setCacheValue(cacheRequestNameWithSlash, cacheValue, cache)
    await setCacheLastFetch(cacheRequestNameWithSlash, cache)
  } catch (error) {
    console.error(`Error by open cache ${cacheRequestNameWithSlash}:`, error)
  }
}

async function setCacheValue (cacheRequestNameWithSlash, cacheValue, cache) {
  try {
    const response = new Response(JSON.stringify(cacheValue))
    await cache.put(cacheRequestNameWithSlash, response)
  } catch (error) {
    console.error(`Error by set the cache entry ${cacheRequestNameWithSlash}:`, error)
  }
}

async function setCacheLastFetch (cacheRequestNameWithSlash, cache) {
  const lastFetchTimeRequestName = `${cacheRequestNameWithSlash}_lastFetchTime`
  const currentTime = Date.now()
  const timeResponse = new Response(currentTime.toString())

  try {
    await cache.put(lastFetchTimeRequestName, timeResponse)
  } catch (error) {
    console.error(`Error by set the cache entry ${lastFetchTimeRequestName} for time ${timeResponse} :`, error)
  }
}

export async function getCache (cacheRequestName) {
  const key = getCacheKeyString(cacheRequestName)
  const cacheRequestNameWithSlash = '/' + key
  try {
    const cache = await caches.open(cacheName)
    const cacheResponse = await cache.match(cacheRequestNameWithSlash)
    return cacheResponse ? JSON.parse(await cacheResponse.text()) : null
  } catch (error) {
    console.error(`Error by reading cache ${cacheRequestNameWithSlash}:`, error)
    return null
  }
}

export async function clearCaches () {
  // Return early if we are not in a secure context. caches are not available in
  // insecure contexts.
  if (!window.isSecureContext) {
    console.warn('Not in a secure context, skipping cache clearing.')
    return
  }
  const cache = await caches.open(cacheName)
  const keys = await cache.keys()
  return await Promise.all(keys.map(key => cache.delete(key)))
}

export async function invalidateCache (cacheRequestName) {
  const key = getCacheKeyString(cacheRequestName)
  const cacheRequestNameWithSlash = '/' + key
  const lastFetchTimeRequestName = `${cacheRequestNameWithSlash}`
  try {
    const cache = await caches.open(cacheName)
    await cache.delete(cacheRequestNameWithSlash)
    await cache.delete(lastFetchTimeRequestName + '_lastFetchTime')
    console.debug(`Invalidated cache ${key}`)
  } catch (error) {
    console.error(`Error while invalidating cache ${cacheRequestName}:`, error)
  }
}

const cacheName = 'foodsharing.network'

export async function getCacheInterval (cacheRequestName, rateLimitInterval) {
  const cacheRequestNameWithSlash = '/' + cacheRequestName
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
  const timeSinceLastFetch = currentTime - lastFetchTime
  return timeSinceLastFetch >= rateLimitInterval
}

export async function setCache (cacheRequestName, cacheValue) {
  const cacheRequestNameWithSlash = '/' + cacheRequestName

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
  const cacheRequestNameWithSlash = '/' + cacheRequestName
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
  const cache = await caches.open(cacheName)
  const keys = await cache.keys()
  return await Promise.all(keys.map(key => cache.delete(key)))
}

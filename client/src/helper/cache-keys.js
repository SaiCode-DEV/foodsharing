// Centralized cache key definitions
// These are used for caching API responses

const MINUTES = 60 * 1000

const cacheKeys = {
  listPickupOptions: () => ({
    cacheKey: 'pickup-options',
    cacheDuration: 15 * MINUTES,
  }),
  listRegisteredPickups: (userId = 'current') => ({
    cacheKey: `pickup-registered-${userId}`,
    cacheDuration: 5 * MINUTES,
  }),
}

export { cacheKeys }

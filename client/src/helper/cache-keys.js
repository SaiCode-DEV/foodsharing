// Centralized cache key definitions
// These are used for caching API responses

const MINUTES = 60 * 1000

const cacheKeys = {
  // Slots that others take or that a manager cancels change without this browser
  // noticing, so the list must not be kept for long.
  listPickupOptions: () => ({
    cacheKey: 'pickup-options',
    cacheDuration: 2 * MINUTES,
  }),
  listRegisteredPickups: (userId = 'current') => ({
    cacheKey: `pickup-registered-${userId}`,
    cacheDuration: 5 * MINUTES,
  }),
}

export { cacheKeys }

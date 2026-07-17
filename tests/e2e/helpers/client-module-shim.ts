// The client helper modules read window.serverData at import time. Provide a
// minimal window object so pure client functions can be exercised from
// Playwright specs. Import this BEFORE any client module.
process.env.TZ = "UTC";
(globalThis as any).window = { serverData: {} };

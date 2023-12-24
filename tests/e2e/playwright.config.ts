import { defineConfig, devices } from "@playwright/test";

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: "./specs/",

  /* Run tests in files in parallel */
  fullyParallel: true,

  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,

  /* Limit the number of failures on CI to save resources */
  maxFailures: process.env.CI ? 10 : undefined,

  /* Retry on CI only */
  retries: process.env.CI ? 1 : 0,

  /* Limit the number of workers on CI, use default locally. */
  workers: process.env.CI ? "80%" : undefined,

  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: "html",

  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: process.env.CI_ENVIRONMENT_URL || "http://nginx:8080",

    /* Capture screenshot after each test failure. */
    screenshot: "only-on-failure",

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: "on-first-retry",

    /* Viewport used for all pages in the context. */
    viewport: { width: 1920, height: 1080 },

    /* Record video only when retrying a test for the first time. */
    video: "on-first-retry",
  },

  /* Configure projects for major browsers */
  projects: [
    /* Test against desktop viewports. */
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "firefox",
      use: { ...devices["Desktop Firefox"] },
    },
    {
      name: "webkit",
      use: { ...devices["Desktop Safari"] },
    },

    /* Test against mobile viewports. */
    {
      name: "Mobile Chrome",
      use: { ...devices["Pixel 7"] },
    },
    {
      name: "Mobile Safari",
      use: { ...devices["iPhone 14"] },
    },
  ],
});

import { defineConfig, devices } from "@playwright/test";
import os from "os";

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: "./specs/",
  outputDir: "../_output/test-results",
  fullyParallel: true,
  retries: 1,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Limit the number of failures on CI to save resources */
  maxFailures: process.env.CI ? 10 : undefined,
  /* Limit the number of workers on CI, use default locally. */
  workers: process.env.CI
    ? "70%"
    : (() => {
        const cores = os.cpus().length;
        if (cores <= 4) return 1; // 1-4 cores: 1 worker (safe for weak machines)
        if (cores <= 8) return 2; // 5-8 cores
        if (cores <= 16) return 3; // 9-16 cores
        return "50%"; // 17+ cores: 50%
      })(), // you can always override manually by passing --workers=X to playwright test
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
    ["list"],
    ["html", { outputFolder: "../_output/html-report" }],
    ["junit", { outputFile: "../_output/report-playwright.xml" }],
  ],
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto('/')`. */
    baseURL: process.env.CI_ENVIRONMENT_URL || "http://nginx:8080",

    /* Viewport used for all pages in the context. */
    viewport: { width: 1920, height: 1080 },

    /* Capture screenshot after each test failure. */
    screenshot: "only-on-failure",

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: "retain-on-failure",

    /* Record video only when retrying a test for the first time. */
    video: "on-first-retry",

    /* Global timeout settings */
    navigationTimeout: 30000,
    actionTimeout: 15000,
  },
  /* Path to global teardown module */
  globalTeardown: "./global-teardown.ts",
  /* Configure projects for major browsers */
  projects: [
    /* Test against desktop viewports. */
    {
      name: "Desktop Chrome",
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "Desktop dark mode",
      use: { ...devices["Desktop Chrome"], colorScheme: "dark" },
    },
    {
      name: "Desktop Firefox",
      use: { ...devices["Desktop Firefox"] },
    },
    /* Disable webkit tests for now, as they are quite faulty
    {
      name: "Desktop Safari",
      use: { ...devices["Desktop Safari"] },
    },  */

    /* Test against mobile viewports. */
    {
      name: "Mobile Chrome",
      use: { ...devices["Pixel 7"] },
    },
    /* Disable Mobile Safari tests for now, as they are quite faulty
    {
      name: "Mobile Safari",
      use: { ...devices["iPhone 15 Pro"] },
    },  */
  ],
});

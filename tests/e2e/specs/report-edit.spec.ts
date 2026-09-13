import { test, expect } from "../helpers/acceptance";
import { foodsharing } from "../helpers/foodsharing";
import { Database } from "../helpers/database";
import WorkgroupFunctions from "../helpers/constants/Region/WorkgroupFunctions";

const WARNING = "reports.consequences.warning";

async function seedReportGroup() {
  const region = await foodsharing.createRegion(null, { parent_id: 0 });
  const reportGroup = await foodsharing.createWorkingGroup(
    "Meldungsbearbeitung",
    { parent_id: region.id },
  );
  await Database.addToDatabase("fs_region_function", {
    region_id: reportGroup.id,
    function_id: WorkgroupFunctions.REPORT,
    target_id: region.id,
  });
  const reportAdmin = await foodsharing.createStoreCoordinator(null, {
    bezirk_id: region.id,
  });
  await foodsharing.addRegionMember(reportGroup.id, reportAdmin.id);
  await foodsharing.addRegionAdmin(reportGroup.id, reportAdmin.id);

  return { region, reportGroup, reportAdmin };
}

async function seedReport(group: any, extraParams: any = {}) {
  const reported = await foodsharing.createFoodsaver(null, {
    bezirk_id: group.region.id,
  });
  const reporter = await foodsharing.createFoodsharer();

  return foodsharing.addReport(
    reporter.id,
    reported.id,
    0,
    null,
    null,
    extraParams,
  );
}

async function seedReportWithConsequence(consequence: string) {
  const group = await seedReportGroup();
  const report = await seedReport(group, { consequence });

  return { ...group, report };
}

test("Choosing no consequence removes the consequence of a report", async ({
  page,
  acceptanceHelper,
}) => {
  const { region, reportAdmin, report } =
    await seedReportWithConsequence(WARNING);

  await acceptanceHelper.login(reportAdmin.email);
  await page.goto(`/report/region/${region.id}`);
  const editButton = page.locator("button:has(i.fa-edit)").first();
  await expect(editButton).toBeVisible();
  await acceptanceHelper.waitForActiveAPICalls();

  await editButton.click();
  const modal = page.locator(".modal-dialog");
  await expect(modal).toBeVisible();

  const consequenceSelect = modal.locator(".v-select").nth(1);
  await expect(consequenceSelect).toContainText("Verwarnung");
  await consequenceSelect.locator(".vs__dropdown-toggle").click();
  await page
    .locator(".vs__dropdown-option", { hasText: "Keine Konsequenz" })
    .click();
  await expect(consequenceSelect).toContainText("Keine Konsequenz");

  await modal.getByRole("button", { name: "Speichern" }).click();
  await expect(modal).toBeHidden();
  await acceptanceHelper.waitForActiveAPICalls();

  expect(
    await Database.seeInDatabase("fs_report", {
      id: report.id,
      consequence: null,
    }),
  ).toBe(true);
});

test("Linking a forum thread keeps the consequence of a report", async ({
  page,
  acceptanceHelper,
}) => {
  // The endpoint stores every field it receives, so the list has to send the
  // consequence along with the forum thread instead of dropping it.
  const { region, reportGroup, reportAdmin, report } =
    await seedReportWithConsequence(WARNING);
  const threadName = "Konsequenz bleibt erhalten";
  const thread = await foodsharing.seedForumThread(
    reportGroup.id,
    reportAdmin.id,
    false,
    { name: threadName },
  );

  await acceptanceHelper.login(reportAdmin.email);
  await page.goto(`/report/region/${region.id}`);
  const threadButton = page.locator("button:has(i.fa-comment-alt)").first();
  await expect(threadButton).toBeVisible();
  await acceptanceHelper.waitForActiveAPICalls();

  await threadButton.click();
  const modal = page.locator(".modal-dialog");
  await expect(modal).toBeVisible();
  await modal.locator("#thread-search").fill(threadName);

  const selectButton = modal
    .getByRole("button", { name: "Thread auswählen" })
    .first();
  await expect(selectButton).toBeVisible();
  await selectButton.click();

  await expect
    .poll(async () =>
      Database.seeInDatabase("fs_report", {
        id: report.id,
        forum_thread_id: thread.id,
      }),
    )
    .toBe(true);

  expect(
    await Database.seeInDatabase("fs_report", {
      id: report.id,
      consequence: WARNING,
    }),
  ).toBe(true);
});

test("Removing the linked forum thread in the edit dialog unlinks it", async ({
  page,
  acceptanceHelper,
}) => {
  const group = await seedReportGroup();
  const thread = await foodsharing.seedForumThread(
    group.reportGroup.id,
    group.reportAdmin.id,
  );
  const report = await seedReport(group, {
    forum_thread_id: thread.id,
    consequence: WARNING,
  });

  await acceptanceHelper.login(group.reportAdmin.email);
  await page.goto(`/report/region/${group.region.id}`);
  const editButton = page.locator("button:has(i.fa-edit)").first();
  await expect(editButton).toBeVisible();
  await acceptanceHelper.waitForActiveAPICalls();

  await editButton.click();
  const modal = page.locator(".modal-dialog");
  await expect(modal).toBeVisible();

  const threadInput = modal.locator("#forum-thread");
  await expect(threadInput).toHaveValue(String(thread.id));
  await modal.getByRole("button", { name: "Entfernen" }).click();
  await expect(threadInput).toHaveValue("");

  await modal.getByRole("button", { name: "Speichern" }).click();
  await expect(modal).toBeHidden();
  await acceptanceHelper.waitForActiveAPICalls();

  expect(
    await Database.seeInDatabase("fs_report", {
      id: report.id,
      forum_thread_id: null,
    }),
  ).toBe(true);

  // removing the thread leaves the rest of the editable state alone
  expect(
    await Database.seeInDatabase("fs_report", {
      id: report.id,
      consequence: WARNING,
    }),
  ).toBe(true);
});

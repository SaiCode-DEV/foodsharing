import { test, expect } from '../helpers/acceptance';
import { foodsharing } from '../helpers/foodsharing';
import Role from '../helpers/constants/Foodsaver/Role';
import QuizID from '../helpers/constants/Quiz/QuizID';

test.describe('Quiz', () => {
  let foodsharer: any;
  let foodsaver: any;
  let quizzes: any = {};

  test.beforeEach(async () => {
    // Create users without auto-creating quizzes
    foodsharer = await foodsharing.createFoodsharer(null, { skip_quiz_creation: true });
    foodsaver = await foodsharing.createFoodsaver(null, { skip_quiz_creation: true });

    // Create quizzes for different roles
    const quizRoles = [Role.FOODSAVER, Role.STORE_MANAGER, QuizID.HYGIENE];
    for (const role of quizRoles) {
      quizzes[role] = await foodsharing.createQuiz(role);
    }
  });

  const testCases = [
    {
      userType: 'foodsharer',
      quizName: 'Foodsaver:innen-Quiz',
      buttonTextPattern: 'Quiz ohne Zeitlimit',
      description: 'foodsharer starting foodsaver quiz'
    },
    {
      userType: 'foodsaver', 
      quizName: 'Betriebsverantwortlichen-Quiz',
      buttonTextPattern: 'Quiz mit Zeitlimit',
      description: 'foodsaver starting store manager quiz'
    }
  ];

  testCases.forEach(({ userType, quizName, buttonTextPattern, description }) => {
    test(`can start quiz: ${description}`, async ({ page, acceptanceHelper }) => {
      test.setTimeout(60000); // Extended timeout for quiz operations

      // eslint-disable-next-line playwright/no-conditional-in-test
      const user = userType === 'foodsharer' ? foodsharer : foodsaver;
      
      // Login as the user
      await acceptanceHelper.login(user.email);

      // Navigate to settings page
      await page.goto('/user/current/settings');
      await expect(page.locator(`text=${quizName}`)).toBeVisible();

      // Calculate quiz role (user role + 1)
      const quizRole = user.rolle + 1;
      const quizUrl = `/user/current/settings?sub=rise_role&role=${quizRole}`;
      const expectedQuizUrl = quizUrl.replace('current', user.id.toString());
      
      // Navigate to quiz page
      await page.goto(quizUrl);
      await expect(page).toHaveURL(expectedQuizUrl);
      
      // Click on the quiz to start
      await page.click(`text=${quizName}`);
      await acceptanceHelper.waitForActiveAPICalls();

      // Wait for quiz intro and start quiz
      await expect(page.locator('text=Jetzt das Quiz durchführen!').first()).toBeVisible();
      await page.getByRole('button', { name: buttonTextPattern }).click();
      await expect(page.getByRole('button', { name: 'Los geht’s!' })).toBeVisible();
      await page.getByRole('button', { name: 'Los geht’s!' }).click();

      // Should see first question
      await expect(page.locator('text=Frage 1 von ')).toBeVisible();
      await acceptanceHelper.waitForActiveAPICalls();

      // Answer first question
      const correct = await page.getByText(/(CORRECT)/).all();
      // eslint-disable-next-line playwright/no-conditional-in-test
      if (correct.length > 0) {
        for (const element of correct) {
          await element.click();
        }
      } else {
        await page.getByText('Keine Antwort ist korrekt.').click();
      }

      // Click for answer the question
      await page.getByRole('button', { name: 'Weiter', exact: true }).click();
      // Click for next question
      await page.getByRole('button', { name: 'Weiter', exact: true }).click();

      // Should see second question
      await expect(page.locator('text=Frage 2 von ')).toBeVisible();

      // Test resume functionality
      await page.reload();
      await page.click(`text=${quizName}`);
      await expect(page.locator('text=Quiz jetzt weiter beantworten!')).toBeVisible();
      await page.getByRole('button', { name: 'Quiz jetzt weiter beantworten!' }).click();
      await expect(page.getByRole('button', { name: 'Los geht’s!' })).toBeVisible();
      await page.getByRole('button', { name: 'Los geht’s!' }).click();

      // Should resume at second question
      await expect(page.locator('text=Frage 2 von ')).toBeVisible();
    });
  });

});
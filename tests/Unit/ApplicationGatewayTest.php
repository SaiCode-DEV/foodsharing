<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use Faker\Factory;
use Foodsharing\Modules\Application\ApplicationGateway;
use Tests\Support\UnitTester;

class ApplicationGatewayTest extends Unit
{
    protected UnitTester $tester;
    private ApplicationGateway $gateway;
    private array $group;
    private array $groupAdmin;
    private array $userMember;
    private array $userApplicant;

    final public function _before(): void
    {
        $this->faker = Factory::create('de_DE');
        $this->gateway = $this->tester->get(ApplicationGateway::class);

        $this->group = $this->tester->createWorkingGroup(null);

        $this->groupAdmin = $this->tester->createAmbassador();
        $this->tester->addRegionMember($this->group['id'], $this->groupAdmin['id']);
        $this->tester->addRegionAdmin($this->group['id'], $this->groupAdmin['id']);

        $this->userMember = $this->tester->createFoodsharer();
        $this->tester->addRegionMember($this->group['id'], $this->userMember['id']);

        $this->userApplicant = $this->tester->createFoodsharer();
    }

    public function testGetApplication(): void
    {
        // before applying, there should be no application
        $this->assertNull($this->gateway->getApplication($this->group['id'], $this->userApplicant['id']));

        // after applying, the function should return the application
        $applicationText = $this->apply($this->group['id'], $this->userApplicant['id']);
        $application = $this->gateway->getApplication($this->group['id'], $this->userApplicant['id']);
        $this->assertNotNull($application);
        $this->assertEquals($application->applicationText, $applicationText);
        $this->assertEquals($application->applicant->id, $this->userApplicant['id']);
        $this->assertEquals($application->groupId, $this->group['id']);

        // for active team members, the function should return null
        $this->assertNull($this->gateway->getApplication($this->group['id'], $this->userMember['id']));
        $this->assertNull($this->gateway->getApplication($this->group['id'], $this->groupAdmin['id']));
    }

    public function testAcceptOpenApplication(): void
    {
        // can not accept an application before it was made
        $this->gateway->acceptApplication($this->group['id'], $this->userApplicant['id']);
        $this->tester->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'bezirk_id' => $this->group['id'],
            'foodsaver_id' => $this->userApplicant['id']]
        );

        // can accept an application after it was made
        $this->apply($this->group['id'], $this->userApplicant['id']);
        $this->gateway->acceptApplication($this->group['id'], $this->userApplicant['id']);
        $this->tester->seeInDatabase('fs_foodsaver_has_bezirk', [
            'bezirk_id' => $this->group['id'],
            'foodsaver_id' => $this->userApplicant['id'],
            'active' => 1
        ]);

        // accepting an application for active group members should not have any effect
        $this->gateway->acceptApplication($this->group['id'], $this->userMember['id']);
        $this->tester->seeInDatabase('fs_foodsaver_has_bezirk', [
            'bezirk_id' => $this->group['id'],
            'foodsaver_id' => $this->userMember['id'],
            'active' => 1
        ]);
    }

    public function testDenyOpenApplication(): void
    {
        // can not deny an application before it was made
        $this->gateway->denyApplication($this->group['id'], $this->userApplicant['id']);
        $this->tester->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
                'bezirk_id' => $this->group['id'],
                'foodsaver_id' => $this->userApplicant['id']]
        );

        // can deny an application after it was made
        $this->apply($this->group['id'], $this->userApplicant['id']);
        $this->gateway->denyApplication($this->group['id'], $this->userApplicant['id']);
        $this->tester->dontSeeInDatabase('fs_foodsaver_has_bezirk', [
            'bezirk_id' => $this->group['id'],
            'foodsaver_id' => $this->userApplicant['id'],
        ]);

        // denying an application for active group members should have no effect
        $this->gateway->denyApplication($this->group['id'], $this->userMember['id']);
        $this->tester->seeInDatabase('fs_foodsaver_has_bezirk', [
            'bezirk_id' => $this->group['id'],
            'foodsaver_id' => $this->userMember['id'],
            'active' => 1
        ]);
    }

    /**
     * Adds an application by the user to the group with a random text and returns that text.
     */
    private function apply(int $groupId, int $userId): string
    {
        $applicationText = $this->faker->realTextBetween(20, 100);
        $this->tester->haveInDatabase('fs_foodsaver_has_bezirk', [
            'bezirk_id' => $groupId,
            'foodsaver_id' => $userId,
            'active' => 0,
            'application' => $applicationText
        ]);

        return $applicationText;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use Codeception\Test\Unit;
use DateTime;
use Faker\Factory;
use Faker\Generator;
use Foodsharing\Modules\Core\DBConstants\Event\EventType;
use Foodsharing\Modules\Core\DTO\Address;
use Foodsharing\Modules\Core\DTO\GeoLocation;
use Foodsharing\Modules\Event\DTO\Event;
use Foodsharing\Modules\Event\EventGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Tests\Support\UnitTester;

class EventGatewayTest extends Unit
{
    protected UnitTester $tester;
    private EventGateway $gateway;
    private Generator $faker;

    protected $foodsaver;
    protected $regionGateway;
    protected $region;
    protected $childRegion;

    public function _before()
    {
        $this->gateway = $this->tester->get(EventGateway::class);
        $this->faker = Factory::create('de_DE');

        $this->regionGateway = $this->tester->get(RegionGateway::class);
        $this->foodsaver = $this->tester->createFoodsaver();
        $this->region = $this->tester->createRegion('God', fillMailbox: false);
        $this->tester->addRegionMember($this->region['id'], $this->foodsaver['id']);
        $this->childRegion = $this->tester->createRegion('Jesus', ['parent_id' => $this->region['id']], false);
    }

    public function testAddLocation(): void
    {
        $event = new Event();
        $event->location = GeoLocation::createFromArray([
            'lat' => $this->faker->latitude(),
            'lon' => $this->faker->longitude(),
        ]);
        $event->address = Address::createFromArray([
            'street' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'postalCode' => $this->faker->postcode(),
        ]);
        $event->locationDetails = $this->faker->company();
        $id = $this->gateway->addLocation($event);
        $this->assertGreaterThan(0, $id);
        $this->tester->seeInDatabase('fs_location', [
            'id' => $id,
            'name' => $event->locationDetails,
            'lat' => $event->location->lat,
            'lon' => $event->location->lon,
            'street' => $event->address->street,
            'zip' => $event->address->postalCode,
            'city' => $event->address->city,
        ]);
    }

    public function testAddEvent(): void
    {
        $event = new Event();
        $event->regionId = $this->region['id'];
        $event->name = 'name';
        $event->startDate = new DateTime();
        $event->endDate = new DateTime();
        $event->description = 'desc';
        $event->type = EventType::ONLINE;

        $id = $this->gateway->addEvent($this->foodsaver['id'], $event, null);
        $this->assertGreaterThan(0, $id);
        $this->tester->seeInDatabase('fs_event', [
            'id' => $id,
            'name' => $event->name,
            'description' => $event->description,
            'online' => $event->type->value,
        ]);
    }

    public function testListEvents(): void
    {
        $dateFormat = 'Y-m-d H:i';

        $dateMinusTwoHours = date($dateFormat, time() - (2 * 60 * 60));
        $dateMinusOneHour = date($dateFormat, time() - (60 * 60));
        $datePlusOneHour = date($dateFormat, time() + (60 * 60));
        $datePlusTwoHours = date($dateFormat, time() + (2 * 60 * 60));
        $events = [
            [
                'bezirk_id' => $this->region['id'],
                'region_name' => $this->region['name'],
                'location_id' => null,
                'name' => 'EventInPast',
                'start' => $dateMinusTwoHours,
                'end' => $dateMinusOneHour,
                'description' => 'd',
                'online' => 1,
                'is_public' => 0,
            ],
            [
                'bezirk_id' => $this->region['id'],
                'region_name' => $this->region['name'],
                'location_id' => null,
                'name' => 'EventRunning',
                'start' => $dateMinusOneHour,
                'end' => $datePlusOneHour,
                'description' => 'd',
                'online' => 1,
                'is_public' => 0,
            ],
            [
                'bezirk_id' => $this->region['id'],
                'region_name' => $this->region['name'],
                'location_id' => null,
                'name' => 'EventInFuture',
                'start' => $datePlusOneHour,
                'end' => $datePlusTwoHours,
                'description' => 'd',
                'online' => 1,
                'is_public' => 1,
            ],
        ];
        foreach ($events as $eventData) {
            $event = Event::createFromArray($eventData);
            $eventid = $this->gateway->addEvent($this->foodsaver['id'], $event, null);
            $this->assertGreaterThan(0, $eventid);
        }
        $listedEvents = $this->gateway->listForRegion($this->region['id']);

        $this->assertEquals(sizeof($events), sizeof($listedEvents), 'All events of a region should be listed');

        foreach ($events as $eventData) {
            $this->assertNotEmpty(array_filter($listedEvents, fn ($listedEvent) => $listedEvent->name == $eventData['name']));
        }
    }
}

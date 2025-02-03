<?php

namespace Foodsharing\Mock;

use Faker\Factory;
use Faker\Generator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class GeoapifyMock extends AbstractController
{
    private readonly Generator $faker;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        $this->faker = Factory::create('de_DE');
    }

    /**
     * Emulates the map tile provider for acceptance tests. It returns ./img/mock_tile.png for all coordinates.
     */
    #[Route(path: '/geoapify/{z}/{x}/{y}.png')]
    public function tile_api(): Response
    {
        return new BinaryFileResponse(
            $this->projectDir . '/src/Mock/img/mock_tile.png',
            Response::HTTP_OK,
            ['Content-Type' => 'text/png']
        );
    }

    /**
     * Barebones "emulation" of https://api.geoapify.com/v1/geocode/reverse.
     *
     * @see client/src/api/geocode.js
     */
    #[Route(path: '/geocode/reverse')]
    public function geocode_reverse(#[MapQueryParameter] ?float $lat, #[MapQueryParameter] ?float $lon): JsonResponse
    {
        // Generate geo-stable random test data
        $fakedData = [];
        $seedPrimeFactor = 23411;
        $this->faker->seed(round($lat, 4) * round($lon, 4) * $seedPrimeFactor);
        $fakedData['housenumber'] = $this->faker->numberBetween(1, 200);
        $this->faker->seed(round($lat * 2, 2) * round($lon * 2, 2) * $seedPrimeFactor);
        $fakedData['street'] = $this->faker->streetName();
        $this->faker->seed(round($lat * 3, 1) * round($lon * 3, 1) * $seedPrimeFactor);
        $fakedData['postcode'] = $this->faker->postcode();
        $this->faker->seed(round($lat, 1) * round($lon, 1) * $seedPrimeFactor);
        $fakedData['city'] = $this->faker->city();

        $data = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [
                            round($lon, 4),
                            round($lat, 4)
                        ]
                    ],
                    'properties' => [
                        'country_code' => 'de',
                        'housenumber' => $fakedData['housenumber'],
                        'street' => $fakedData['street'],
                        'country' => 'Deutschland',
                        'county' => 'Eichsfeld',
                        'datasource' => [
                            'sourcename' => 'openaddresses',
                            'attribution' => '© OpenAddresses contributors',
                            'license' => 'BSD-3-Clause License'
                        ],
                        'state' => 'Thüringen',
                        'district' => 'Ershausen/Geismar',
                        'city' => $fakedData['city'],
                        'state_code' => 'TH',
                        'lon' => round($lon, 4),
                        'lat' => round($lat, 4),
                        'distance' => 0,
                        'result_type' => 'building',
                        'postcode' => $fakedData['postcode'],
                        'formatted' => $fakedData['street'] . ' ' . $fakedData['housenumber'] . ', ' . $fakedData['postcode'] . ' ' . $fakedData['city'] . ', Deutschland',
                        'address_line1' => $fakedData['street'] . ' ' . $fakedData['housenumber'],
                        'address_line2' => $fakedData['postcode'] . ' ' . $fakedData['city'] . ', Deutschland',
                        'timezone' => [
                            'name' => 'Europe/Berlin',
                            'offset_STD' => '+01:00',
                            'offset_STD_seconds' => 3600,
                            'offset_DST' => '+02:00',
                            'offset_DST_seconds' => 7200,
                            'abbreviation_STD' => 'CET',
                            'abbreviation_DST' => 'CEST'
                        ],
                        'plus_code' => '9F3G65M7+3R',
                        'plus_code_short' => 'M7+3R Geismar, Eichsfeld, Deutschland',
                        'rank' => [
                            'popularity' => 3.8270057864484
                        ],
                        'place_id' => '51b1dcd26a4854244059ec33677dca9d4940c00203e2034a6f70656e6164647265737365733a616464726573733a64652f74682f7374617465776964652d6164647265737365732d73746174652e6373763a62316364323464656234306264386161'
                    ]
                ]
            ],
            'query' => [
                'lat' => $lat,
                'lon' => $lon,
                'plus_code' => '9F3G65M7+4V'
            ]
        ];

        return new JsonResponse($data);
    }

    /**
     * Barebones "emulation" of https://api.geoapify.com/v1/geocode/search.
     *
     * @see client/src/api/geocode.js
     */
    #[Route(path: '/geocode/search')]
    public function geocode_search(#[MapQueryParameter] ?string $text): JsonResponse
    {
        $this->faker->seed(crc32($text));
        $data = [
            'type' => 'FeatureCollection',
            'features' => [],
            'query' => [
                'text' => $text,
                'parsed' => [
                    'house' => 'Schwimmbad',
                    'street' => 'Teststraße',
                    'postcode' => '37073',
                    'city' => 'Teststadt',
                    'country' => 'Deutschland',
                    'expected_type' => 'amenity'
                ]
            ]
        ];
        for ($i = $this->faker->numberBetween(1, 4); $i > 0; --$i) {
            $data['features'][] = $this->getFeature($text);
        }

        return new JsonResponse($data);
    }

    private function getFeature(?string $name): array
    {
        $fakedData = [
            'lat' => $this->faker->latitude(46, 55),
            'lon' => $this->faker->longitude(4, 16),
            'housenumber' => $this->faker->numberBetween(1, 200),
            'street' => $this->faker->streetName(),
            'postcode' => $this->faker->postcode(),
            'city' => $this->faker->city(),
        ];
        $feature = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    $fakedData['lon'],
                    $fakedData['lat'],
                ]
            ],
            'properties' => [
                'country_code' => 'de',
                'country' => 'Deutschland',
                'county' => $fakedData['city'],
                'datasource' => [
                    'sourcename' => 'openstreetmap',
                    'attribution' => '© OpenStreetMap contributors',
                    'license' => 'Open Database License',
                    'url' => 'https://www.openstreetmap.org/copyright'
                ],
                'street' => $fakedData['street'],
                'housenumber' => $fakedData['housenumber'],
                'state' => 'Bayern',
                'district' => 'Teststadt',
                'city' => $fakedData['city'],
                'state_code' => 'BY',
                'lon' => $fakedData['lon'],
                'lat' => $fakedData['lat'],
                'result_type' => 'amenity',
                'postcode' => $fakedData['postcode'],
                'formatted' => $name . ', ' . $fakedData['street'] . ' ' . $fakedData['housenumber'] . ', ' . $fakedData['postcode'] . ' ' . $fakedData['city'] . ', Deutschland',
                'address_line1' => $name . ', ' . $fakedData['street'] . ' ' . $fakedData['housenumber'],
                'address_line2' => $fakedData['postcode'] . ' ' . $fakedData['city'] . ', Deutschland',
                'timezone' => [
                    'name' => 'Europe/Berlin',
                    'offset_STD' => '+01:00',
                    'offset_STD_seconds' => 3600,
                    'offset_DST' => '+02:00',
                    'offset_DST_seconds' => 7200,
                    'abbreviation_STD' => 'CET',
                    'abbreviation_DST' => 'CEST'
                ],
                'plus_code' => '9F2F2632+HJ',
                'plus_code_short' => '32+HJ Teststadt, Deutschland',
                'rank' => [
                    'popularity' => 5.6154076634886,
                    'confidence' => 1,
                    'confidence_city_level' => 1,
                    'confidence_street_level' => 1,
                    'match_type' => 'full_match'
                ],
                'place_id' => '51293e3e213b67224059b309302c7f004940f00102f901baa5200200000000c00201e203206f70656e7374726565746d61703a76656e75653a7761792f3335363934303130'
            ],
            'bbox' => [
                9.2015582,
                50.0037556,
                9.2018814,
                50.0038824
            ]
        ];

        return $feature;
    }
}

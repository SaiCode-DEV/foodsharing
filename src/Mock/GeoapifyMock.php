<?php

namespace Foodsharing\Mock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GeoapifyMock extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
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
    public function geocode_reverse(): JsonResponse
    {
        $data = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [
                            10.164615,
                            51.232742
                        ]
                    ],
                    'properties' => [
                        'country_code' => 'de',
                        'housenumber' => '69',
                        'street' => 'Reverse Geocode',
                        'country' => 'Codeland',
                        'county' => 'Eichsfeld',
                        'datasource' => [
                            'sourcename' => 'openaddresses',
                            'attribution' => '© OpenAddresses contributors',
                            'license' => 'BSD-3-Clause License'
                        ],
                        'state' => 'Thüringen',
                        'district' => 'Ershausen/Geismar',
                        'city' => 'Codedorf',
                        'state_code' => 'TH',
                        'lon' => 10.164615,
                        'lat' => 51.232742,
                        'distance' => 8.303305961163,
                        'result_type' => 'building',
                        'postcode' => '69420',
                        'formatted' => 'Reverse Geocode 5, 37308 Geismar, Deutschland',
                        'address_line1' => 'Hintergasse 5',
                        'address_line2' => '37308 Geismar, Deutschland',
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
                'lat' => 51.232788355726,
                'lon' => 10.16470849514,
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
    public function geocode_search(): JsonResponse
    {
        $data = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [
                            10.184615,
                            51.239742,
                        ]
                    ],
                    'properties' => [
                        'country_code' => 'de',
                        'country' => 'Deutschland',
                        'county' => 'Aschaffenburg',
                        'datasource' => [
                            'sourcename' => 'openstreetmap',
                            'attribution' => '© OpenStreetMap contributors',
                            'license' => 'Open Database License',
                            'url' => 'https://www.openstreetmap.org/copyright'
                        ],
                        'street' => 'Teststraße 1',
                        'state' => 'Bayern (nicht teil von DE)',
                        'district' => 'Teststadt',
                        'city' => 'Teststadt',
                        'state_code' => 'BY',
                        'lon' => 10.184615,
                        'lat' => 51.239742,
                        'result_type' => 'amenity',
                        'postcode' => '37073',
                        'formatted' => 'Teststraße 1, 37073 Teststadt, Deutschland',
                        'address_line1' => 'Teststraße 1',
                        'address_line2' => '63768 Hösbach, Deutschland',
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
                        'plus_code_short' => '32+HJ Hösbach, Aschaffenburg, Deutschland',
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
                ]
            ],
            'query' => [
                'text' => 'Behindi Toillette, Rathausplatz, 63768 Hösbach, Deutschland',
                'parsed' => [
                    'house' => 'behindi toillette',
                    'street' => 'rathausplatz',
                    'postcode' => '63768',
                    'city' => 'hösbach',
                    'country' => 'deutschland',
                    'expected_type' => 'amenity'
                ]
            ]
        ];

        return new JsonResponse($data);
    }
}

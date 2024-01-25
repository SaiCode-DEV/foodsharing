<?php

namespace Foodsharing\Mock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PhotonMock extends AbstractController
{
    /**
     * Barebones "emulation" of https://photon.komoot.io/api.
     *
     * @see client/src/addressPicker.js
     */
    #[Route(path: '/photon/api')]
    public function api(): JsonResponse
    {
        $data = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [
                            9.0,
                            51.0
                        ]
                    ],
                    'properties' => [
                        'osm_id' => 0,
                        'country' => 'Deutschland',
                        'city' => 'Teststadt',
                        'countrycode' => 'DE',
                        'postcode' => '37073',
                        'locality' => 'Bahnhof-Ost',
                        'county' => 'Landkreis Teststadt',
                        'type' => 'house',
                        'osm_type' => 'N',
                        'osm_key' => 'railway',
                        'housenumber' => '1',
                        'street' => 'Teststraße',
                        'district' => 'Innenstadt',
                        'osm_value' => 'station',
                        'name' => 'Test',
                        'state' => 'Niedersachsen'
                    ]
                ]
            ]
        ];

        return new JsonResponse($data);
    }
}

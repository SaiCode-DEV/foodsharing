<?php

namespace Foodsharing\Mock;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TwingleMock extends AbstractController
{
    public function __construct()
    {
    }

    /**
     * Emulation of https://report.twingle.de/api/v2/project/{projectId}/projectstatus.
     *
     * See TwingleDonationDataQuery.php
     */
    #[Route(path: '/twingle/{projectId}/projectstatus')]
    public function projectStatus(int $projectId): JsonResponse
    {
        return new JsonResponse([
            'amount' => 29810,
            'donators' => 899,
            'percentage' => 29.81,
            'target' => 100000,
            'allow_more' => false,
        ]);
    }

    /**
     * Emulation of https://report.twingle.de/api/v2/by-organisation/{organisationId}?options=0.
     *
     * See TwingleDonationDataQuery.php
     */
    #[Route(path: '/twingle/by-organisation/{organisationId}')]
    public function projectList(int $organisationId): JsonResponse
    {
        return new JsonResponse([
            [
                'id' => 384,
                'internal_id' => '',
                'name' => 'Einmal-Spenden',
                'organisation_id' => $organisationId,
                'slug' => 'einmal-spenden',
                'identifier' => 'tw5ba1eb3588eb2',
                'type' => '',
                'transaction_type' => 'donation',
                'project_target' => 0,
                'image_directory' => '/bundles/twinglepublic/upload/images/' . $organisationId,
                'allow_more' => false,
                'last_update' => 1705347096,
                'created_at' => 1537338165,
                'host' => 'https://spenden.twingle.de/',
                'event_gallery' => [],
            ],
            [
                'id' => 398,
                'internal_id' => '',
                'name' => 'Freundeskreis',
                'organisation_id' => $organisationId,
                'slug' => 'freundeskreis',
                'identifier' => 'tw5ba5f44dcb36f',
                'type' => 'membership',
                'transaction_type' => 'donation',
                'project_target' => 0,
                'image_directory' => '/bundles/twinglepublic/upload/images/' . $organisationId,
                'allow_more' => false,
                'last_update' => 1616519007,
                'created_at' => 1537602637,
                'host' => 'https://spenden.twingle.de/',
                'event_gallery' => [],
            ],
            [
                'id' => 12573,
                'internal_id' => '',
                'name' => 'Spendenkampagne überregionale Arbeit',
                'organisation_id' => $organisationId,
                'slug' => 'spendenkampagne-ueberregionale-arbeit',
                'identifier' => 'tw65a581c764fa1',
                'type' => '',
                'transaction_type' => null,
                'project_target' => 100000,
                'image_directory' => '/bundles/twinglepublic/upload/images/' . $organisationId,
                'allow_more' => false,
                'last_update' => 1771011741,
                'created_at' => 1705345479,
                'host' => 'https://spenden.twingle.de/',
                'event_gallery' => [],
            ],
        ]);
    }
}

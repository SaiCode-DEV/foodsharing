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
}

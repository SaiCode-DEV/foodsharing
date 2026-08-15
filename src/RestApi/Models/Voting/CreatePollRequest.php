<?php

namespace Foodsharing\RestApi\Models\Voting;

use DateTime;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

class CreatePollRequest
{
    #[OA\Property(description: 'Title of the poll', type: 'string', example: 'Test poll')]
    #[Assert\NotBlank]
    public string $name;

    #[OA\Property(description: 'Description of the poll', type: 'string', example: 'This is a test poll')]
    #[Assert\NotBlank]
    public string $description;

    #[OA\Property(description: 'Date and time at which voting starts', example: '2105-01-01 01:23:45')]
    public DateTime $startDate;

    #[OA\Property(description: 'Date and time at which voting ends', example: '2152-01-01 12:34:56')]
    public DateTime $endDate;

    #[OA\Property(description: 'Region to which the poll belongs', type: 'integer', example: 1)]
    #[Assert\Positive]
    public int $regionId;

    #[OA\Property(description: 'Who will be eligible to vote', type: 'integer', example: 1)]
    #[Assert\Range(min: 0, max: 4)]
    public int $scope;

    #[OA\Property(description: 'The voting type, i.e. how options can be chosen', type: 'integer', example: 1)]
    #[Assert\Range(min: 0, max: 3)]
    public int $type;

    #[OA\Property(
        description: 'All options that can be voted for',
        type: 'array',
        items: new OA\Items(type: 'string')
    )]
    #[Assert\All(new Assert\NotBlank())]
    public array $options;

    #[OA\Property(description: 'If all users who are eligible to vote shall be noticed by email', type: 'boolean')]
    public bool $notifyVoters;

    #[OA\Property(description: 'If the options shall be displayed in random order when voting', type: 'boolean')]
    public bool $shuffleOptions = true;
}

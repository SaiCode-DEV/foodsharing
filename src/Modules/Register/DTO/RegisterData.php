<?php

namespace Foodsharing\Modules\Register\DTO;

use DateTime;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Gender;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class that represents the registration data for a new user from the registration form.
 */
class RegisterData
{
    #[OA\Property(example: 'Melanie')]
    #[Assert\NotBlank]
    public string $firstName;

    #[OA\Property(example: 'Musterfrau')]
    #[Assert\NotBlank]
    public string $lastName;

    #[OA\Property(example: 'melanie.musterfrau@example.com')]
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[OA\Property(example: 'Password123!')]
    #[Assert\NotBlank]
    public string $password;

    #[OA\Property(example: 2)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [Gender::MALE, Gender::FEMALE, Gender::DIVERSE, Gender::NOT_SELECTED])]
    public int $gender;

    public DateTime $birthdate;

    #[OA\Property(example: '+491234567890')]
    public string $mobilePhone;

    #[OA\Property(description: 'Whether the user wants to subscribe to the newsletter')]
    public bool $subscribeNewsletter;
}

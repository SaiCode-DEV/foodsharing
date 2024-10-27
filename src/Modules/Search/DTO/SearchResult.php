<?php

namespace Foodsharing\Modules\Search\DTO;

use Foodsharing\Modules\Foodsaver\Profile;
use OpenApi\Annotations as OA;

class SearchResult
{
    /**
     * Unique identifier of the entity represented by the search result.
     *
     * @OA\Property(example=1)
     */
    public int $id;

    /**
     * Name of the entity represented by the search result.
     *
     * @OA\Property(example="Name")
     */
    public ?string $name = null;

    /**
     * Search criteria to test the search against.
     *
     * @var ?string search criteria string in which query words must be contained
     *
     * @OA\Property(example="Münster;meunster")
     */
    public ?string $search_string = null;

    protected static function formatUserList(array $data, string $namespace): array
    {
        $keys = ['id', 'name', 'photo', 'is_sleeping'];
        if (empty($data[$namespace . '_ids'])) {
            return [];
        } else {
            return array_map(
                fn (...$values) => new Profile(array_combine($keys, $values)),
                ...array_map(fn ($key) => explode(',', (string)$data[$namespace . '_' . $key . 's']), $keys)
            );
        }
    }

    protected function setSearchString($data): void
    {
        if (array_key_exists('search_string', $data)) {
            $this->search_string = $data['search_string'];
        }
    }
}

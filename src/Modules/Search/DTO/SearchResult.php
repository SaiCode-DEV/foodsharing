<?php

namespace Foodsharing\Modules\Search\DTO;

use Foodsharing\Modules\Foodsaver\Profile;
use OpenApi\Attributes as OA;

class SearchResult
{
    #[OA\Property(example: 1, description: 'Unique identifier of the entity represented by the search result.')]
    public int $id;

    #[OA\Property(example: 'Name', description: 'Name of the entity represented by the search result.')]
    public ?string $name = null;

    #[OA\Property(example: 'Münster;meunster', description: 'Search criteria to test the search against.')]
    public ?string $searchString = null;

    protected static function formatUserList(array $data, string $namespace): array
    {
        $keys = ['id', 'name', 'photo', 'is_sleeping'];
        if (empty($data[$namespace . '_ids'])) {
            return [];
        } else {
            return array_map(
                fn (...$values) => new Profile(array_combine($keys, (array)$values)),
                ...array_map(fn ($key) => explode(',', (string)$data[$namespace . '_' . $key . 's']), $keys)
            );
        }
    }

    protected function setSearchString($data): void
    {
        if (array_key_exists('search_string', $data)) {
            $this->searchString = $data['search_string'];
        }
    }
}

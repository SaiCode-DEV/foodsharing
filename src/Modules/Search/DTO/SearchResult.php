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

        $idsRaw = (string)($data[$namespace . '_ids'] ?? '');
        if ($idsRaw === '') {
            return [];
        }

        // Use the new safe separator if present, otherwise fall back to comma for legacy queries.
        $sep = str_contains($idsRaw, "\x1F") ? "\x1F" : ',';

        $lists = [];
        foreach ($keys as $key) {
            $raw = (string)($data[$namespace . '_' . $key . 's'] ?? '');
            $lists[$key] = ($raw === '') ? [] : explode($sep, $raw);
        }

        $profiles = [];
        $count = count($lists['id']);

        for ($i = 0; $i < $count; ++$i) {
            $id = $lists['id'][$i] ?? null;
            if ($id === null || $id === '') {
                continue;
            }

            $profiles[] = new Profile([
                'id' => (int)$id,
                'name' => $lists['name'][$i] ?? null,
                'photo' => $lists['photo'][$i] ?? '',
                'is_sleeping' => $lists['is_sleeping'][$i] ?? 0,
            ]);
        }

        return $profiles;
    }

    protected function setSearchString($data): void
    {
        if (array_key_exists('search_string', $data)) {
            $this->searchString = $data['search_string'];
        }
    }
}

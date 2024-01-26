<?php

namespace Foodsharing\Modules\Content;

use Carbon\Carbon;
use DateTimeZone;
use Foodsharing\Modules\Content\DTO\Content;
use Foodsharing\Modules\Core\BaseGateway;

class ContentGateway extends BaseGateway
{
    /**
     * @deprecated use getContent instead
     */
    public function get($id): array
    {
        return $this->db->fetchByCriteria('fs_content', ['title', 'body'], ['id' => $id]);
    }

    /**
     * Returns the content with the specific id or null if the id does not exist.
     */
    public function getContent(int $id): ?Content
    {
        $content = $this->db->fetchByCriteria('fs_content', ['name', 'title', 'body', 'last_mod'], ['id' => $id]);

        if ($content == null) {
            return null;
        }

        $lastModified = $content['last_mod'] != null
            ? Carbon::createFromFormat('Y-m-d H:i:s', $content['last_mod'], new DateTimeZone('Europe/Berlin'))
                ->shiftTimezone(new DateTimeZone('UTC'))
            : null;

        return Content::create($id, $content['name'], $content['title'], $content['body'], $lastModified);
    }

    public function getMultiple(array $ids): array
    {
        return $this->db->fetchAllByCriteria('fs_content', ['id', 'title', 'body'], ['id' => $ids]);
    }

    /**
     * @param int[] $filter a list of content ids to restrict the results to, or null to list all pages
     */
    public function list(array $filter = null): array
    {
        $list = $this->db->fetchAllByCriteria('fs_content', ['id', 'name', 'title', 'last_mod'], $filter ? ['id' => $filter] : []);

        return array_map(function ($content) {
            $lastModified = $content['last_mod'] != null
                ? Carbon::createFromFormat('Y-m-d H:i:s', $content['last_mod'], new DateTimeZone('Europe/Berlin'))
                    ->shiftTimezone(new DateTimeZone('UTC'))
                : null;

            return Content::create($content['id'], $content['name'], $content['title'], '', $lastModified);
        }, $list);
    }

    public function getDetail($id): array
    {
        return $this->db->fetchByCriteria('fs_content', ['id', 'name', 'title', 'body', 'last_mod'], ['id' => $id]);
    }

    public function create($data): int
    {
        return $this->db->insert('fs_content', [
            'name' => strip_tags((string)$data['name']),
            'title' => strip_tags((string)$data['title']),
            'body' => $data['body'],
            'last_mod' => $data['last_mod']
        ]);
    }

    public function update($id, $data): int
    {
        return $this->db->update('fs_content', [
            'name' => strip_tags((string)$data['name']),
            'title' => strip_tags((string)$data['title']),
            'body' => $data['body'],
            'last_mod' => $data['last_mod']
        ], ['id' => $id]);
    }

    public function delete($id): int
    {
        return $this->db->delete('fs_content', ['id' => $id]);
    }
}

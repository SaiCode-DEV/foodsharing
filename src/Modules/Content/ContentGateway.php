<?php

namespace Foodsharing\Modules\Content;

use Carbon\Carbon;
use DateTimeZone;
use Foodsharing\Modules\Content\DTO\Content;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\Database;
use Foodsharing\RestApi\Models\Content\ContentEntry;
use Foodsharing\Utility\Sanitizer;

class ContentGateway extends BaseGateway
{
    private readonly Sanitizer $sanitizer;

    public function __construct(
        Database $db,
        Sanitizer $sanitizer,
    ) {
        parent::__construct($db);
        $this->sanitizer = $sanitizer;
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

        $config = Sanitizer::getPurifierConfig();
        $content['body'] = $this->sanitizer->purifyHtml($content['body'] ?? '', $config);

        return Content::create($id, $content['name'], $content['title'], $content['body'], $lastModified);
    }

    /**
     * Returns the contents for the given ids.
     */
    public function getMultiple(array $ids): array
    {
        $contents = $this->db->fetchAllByCriteria('fs_content', ['id', 'title', 'body'], ['id' => $ids]);
        foreach ($contents as $content) {
            $content['body'] = $this->sanitizer->purifyHtml($content['body']);
        }

        return $contents;
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

    /**
     * Adds a new content entry and returns the id.
     */
    public function create(ContentEntry $data): int
    {
        return $this->db->insert('fs_content', [
            'name' => strip_tags($data->name),
            'title' => strip_tags($data->title),
            'body' => $data->body,
            'last_mod' => date('Y-m-d H:i:s')
        ]);
    }

    public function update(int $id, ContentEntry $data): int
    {
        return $this->db->update('fs_content', [
            'name' => strip_tags($data->name),
            'title' => strip_tags($data->title),
            'body' => $data->body,
            'last_mod' => date('Y-m-d H:i:s')
        ], ['id' => $id]);
    }

    public function delete($id): int
    {
        return $this->db->delete('fs_content', ['id' => $id]);
    }
}

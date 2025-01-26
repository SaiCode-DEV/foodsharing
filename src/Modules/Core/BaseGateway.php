<?php

namespace Foodsharing\Modules\Core;

abstract class BaseGateway
{
    /**
     * @var Database
     */
    protected $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function buildPaginationSqlLimit(Pagination $pagination): string
    {
        if ($pagination->pageSize > 0) {
            return ' LIMIT :page_size OFFSET :start_item_index ';
        }

        return '';
    }

    public function addPaginationSqlLimitParameters(Pagination $pagination, array $params): array
    {
        if ($pagination->pageSize > 0) {
            $params['start_item_index'] = $pagination->offset;
            $params['page_size'] = $pagination->pageSize;
        }

        return $params;
    }
}

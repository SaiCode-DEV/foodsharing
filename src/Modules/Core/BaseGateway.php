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

    public function buildPaginationSqlLimit(?Pagination $pagination): string
    {
        if (!is_null($pagination) && $pagination->limit > 0) {
            return ' LIMIT :limit OFFSET :offset ';
        }

        return '';
    }

    public function addPaginationSqlLimitParameters(?Pagination $pagination, array $params): array
    {
        if (!is_null($pagination) && $pagination->limit > 0) {
            $params['offset'] = $pagination->offset;
            $params['limit'] = $pagination->limit;
        }

        return $params;
    }
}

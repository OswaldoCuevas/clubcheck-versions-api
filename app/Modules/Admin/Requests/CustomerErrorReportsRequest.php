<?php

namespace App\Modules\Admin\Requests;

final class CustomerErrorReportsRequest extends AdminRequest
{
    public readonly int $page;
    public readonly int $perPage;
    public readonly array $filters;

    public function __construct()
    {
        parent::__construct(['GET']);

        $this->page = $this->queryInt('page', 1, 1, PHP_INT_MAX);
        $this->perPage = $this->queryInt('perPage', 30, 10, 100);
        $this->filters = [
            'search' => $this->queryString('search', ''),
            'status' => $this->queryString('status', 'all'),
            'type' => $this->queryString('type', 'all'),
        ];
    }
}

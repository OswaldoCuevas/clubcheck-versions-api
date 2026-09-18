<?php

namespace App\Modules\Admin\Requests;

final class CustomerLoginAttemptsRequest extends AdminRequest
{
    public readonly int $page;
    public readonly int $perPage;
    public readonly array $filters;

    public function __construct(string $appId)
    {
        parent::__construct(['GET']);

        $this->page = $this->queryInt('page', 1, 1, PHP_INT_MAX);
        $this->perPage = $this->queryInt('perPage', 50, 10, 200);
        $this->filters = [
            'search' => $this->queryString('search', ''),
            'status' => $this->queryString('status', 'all'),
            'codeAccess' => $this->queryString('codeAccess', ''),
            'from' => $this->queryString('from', ''),
            'to' => $this->queryString('to', ''),
            'appId' => $appId,
        ];
    }
}

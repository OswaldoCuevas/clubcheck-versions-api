<?php

namespace App\Modules\Admin\Requests;

final class DownloadsRequest extends AdminRequest
{
    public readonly int $page;
    public readonly int $perPage;
    public readonly ?string $ip;

    public function __construct()
    {
        parent::__construct(['GET']);

        $this->page = $this->queryInt('page', 1, 1, PHP_INT_MAX);
        $this->perPage = $this->queryInt('perPage', 20, 10, 100);
        $this->ip = $this->queryString('ip');
    }
}

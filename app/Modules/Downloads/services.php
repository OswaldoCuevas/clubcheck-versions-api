<?php

declare(strict_types=1);
use App\Modules\Downloads\Contracts\IDownloadRepository;
use App\Modules\Downloads\Repositories\DownloadRepository;
use Core\Container;

return static function (Container $c): void {
    $c->bind(IDownloadRepository::class, DownloadRepository::class);
};

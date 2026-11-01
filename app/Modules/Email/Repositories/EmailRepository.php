<?php

declare(strict_types=1);

namespace App\Modules\Email\Repositories;

use App\Infrastructure\Database\IDatabaseConnection;
use App\Modules\Email\Contracts\IEmailRepository;

final readonly class EmailRepository implements IEmailRepository
{
    public function __construct(private IDatabaseConnection $database)
    {
    }

    public function activeTypes(): array
    {
        return $this->database->fetchAll(
            'SELECT * FROM EmailTypes WHERE IsActive = 1 ORDER BY Id ASC',
        );
    }
}

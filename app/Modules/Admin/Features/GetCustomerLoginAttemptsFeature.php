<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\CustomerLoginAttemptsRequest;
use Models\CustomerWebLoginAttemptModel;

final class GetCustomerLoginAttemptsFeature
{
    public function __construct(private ?CustomerWebLoginAttemptModel $attempts = null)
    {
        $this->attempts ??= new CustomerWebLoginAttemptModel();
    }

    public function handle(CustomerLoginAttemptsRequest $request, string $appId): array
    {
        return [
            'attempts' => $this->attempts->getAttempts($request->filters, $request->page, $request->perPage),
            'summary' => $this->attempts->getSummary($appId),
            'generatedAt' => date('Y-m-d H:i:s'),
        ];
    }
}

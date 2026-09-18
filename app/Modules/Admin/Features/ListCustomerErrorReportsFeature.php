<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\CustomerErrorReportsRequest;
use Models\CustomerErrorReportModel;

final class ListCustomerErrorReportsFeature
{
    public function __construct(private ?CustomerErrorReportModel $reports = null)
    {
        $this->reports ??= new CustomerErrorReportModel();
    }

    public function handle(CustomerErrorReportsRequest $request, string $appId): array
    {
        return [
            'reports' => $this->reports->list($request->filters, $request->page, $request->perPage, $appId),
            'summary' => $this->reports->getSummary($appId),
            'generatedAt' => date('Y-m-d H:i:s'),
        ];
    }
}

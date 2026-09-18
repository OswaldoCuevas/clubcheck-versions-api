<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\ValidationException;
use App\Modules\Admin\Requests\CustomerErrorReportIdRequest;
use Models\CustomerErrorReportModel;

final class MarkCustomerErrorReportReadFeature
{
    public function __construct(private ?CustomerErrorReportModel $reports = null)
    {
        $this->reports ??= new CustomerErrorReportModel();
    }

    public function handle(CustomerErrorReportIdRequest $request, string $appId, ?string $readBy): array
    {
        $report = $this->reports->markAsRead($request->id, $readBy, $appId);
        if ($report === null) {
            throw new ValidationException('Reporte no encontrado');
        }

        return [
            'success' => true,
            'report' => $report,
            'summary' => $this->reports->getSummary($appId),
        ];
    }
}

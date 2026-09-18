<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\SelectApplicationRequest;
use Models\ApplicationModel;

final class SelectApplicationFeature
{
    public function __construct(private ?ApplicationModel $applications = null)
    {
        $this->applications ??= new ApplicationModel();
    }

    public function handle(SelectApplicationRequest $request): array
    {
        $this->applications->setSelectedApp($request->appId);

        return ['redirect' => $request->redirect];
    }
}

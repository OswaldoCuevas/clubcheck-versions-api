<?php

namespace App\Modules\Admin\Features;

use App\Modules\Admin\Requests\SaveApplicationRequest;
use Models\ApplicationModel;

final class SaveApplicationFeature
{
    public function __construct(private ?ApplicationModel $applications = null)
    {
        $this->applications ??= new ApplicationModel();
    }

    public function handle(SaveApplicationRequest $request): array
    {
        $app = $this->applications->save($request->getAttributes());
        $this->applications->setSelectedApp($app['id']);

        return ['redirect' => '/admin/applications'];
    }
}

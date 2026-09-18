<?php

namespace App\Modules\Admin\Features;

use App\Exceptions\NotFoundException;
use App\Modules\Admin\Requests\WhatsAppConfigurationIdRequest;
use Models\WhatsAppConfigurationModel;

final class DeleteWhatsAppConfigurationFeature
{
    public function __construct(private ?WhatsAppConfigurationModel $configurations = null)
    {
        $this->configurations ??= new WhatsAppConfigurationModel();
    }

    public function handle(WhatsAppConfigurationIdRequest $request): array
    {
        $result = $this->configurations->delete($request->id);

        if (!$result['success']) {
            throw new NotFoundException($result['error']);
        }

        return [
            'success' => true,
            'message' => 'Configuracion eliminada correctamente',
        ];
    }
}

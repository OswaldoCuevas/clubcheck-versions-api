<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Controllers;

use App\Application\Contracts\IMediator;
use App\Http\JsonPayload;
use App\Http\JsonResponse;
use App\Http\ViewResponse;
use App\Modules\WhatsApp\Features\CreateConfiguration\Command as CreateConfigurationCommand;
use App\Modules\WhatsApp\Features\CreateTemplate\Command as CreateTemplateCommand;
use App\Modules\WhatsApp\Features\DeleteConfiguration\Command as DeleteConfigurationCommand;
use App\Modules\WhatsApp\Features\DeleteTemplate\Command as DeleteTemplateCommand;
use App\Modules\WhatsApp\Features\GetConfigurationStatus\Query as GetConfigurationStatusQuery;
use App\Modules\WhatsApp\Features\ListConfigurations\Query as ListConfigurationsQuery;
use App\Modules\WhatsApp\Features\ListTemplates\Query as ListTemplatesQuery;
use App\Modules\WhatsApp\Features\RegisterConfiguration\Command as RegisterConfigurationCommand;
use App\Modules\WhatsApp\Features\SendTestMessage\Command as SendTestMessageCommand;
use App\Modules\WhatsApp\Features\ShowConfigurations\Query as ShowConfigurationsQuery;
use App\Modules\WhatsApp\Features\ShowMessages\Query as ShowMessagesQuery;
use Core\UrlHelper;

final readonly class AdminWhatsAppController
{
    public function __construct(private IMediator $mediator)
    {
    }

    public function whatsapp(): void
    {
        ViewResponse::render(
            'admin/whatsapp',
            $this->mediator->send(new ShowConfigurationsQuery()),
        );
    }

    public function whatsappMessages(): void
    {
        ViewResponse::render(
            'admin/whatsapp-messages',
            $this->mediator->send(new ShowMessagesQuery($_GET)),
        );
    }

    public function sendWhatsappTestMessage(): void
    {
        $result = $this->mediator->send(new SendTestMessageCommand($_POST));

        if ($result->statusCode !== null) {
            http_response_code($result->statusCode);
            echo $result->body;

            return;
        }

        header('Location: ' . UrlHelper::url($result->redirectTo));
        exit;
    }

    public function whatsappListJson(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new ListConfigurationsQuery()),
        );
    }

    public function whatsappCreateJson(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new CreateConfigurationCommand(JsonPayload::parse()),
            ),
        );
    }

    public function whatsappDeleteJson(string $id): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new DeleteConfigurationCommand($id)),
        );
    }

    public function whatsappRegisterJson(string $id): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new RegisterConfigurationCommand($id)),
        );
    }

    public function whatsappStatusJson(string $id): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new GetConfigurationStatusQuery($id)),
        );
    }

    public function whatsappTemplatesJson(): void
    {
        JsonResponse::sendResult($this->mediator->send(new ListTemplatesQuery()));
    }

    public function whatsappTemplateCreateJson(): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(
                new CreateTemplateCommand(JsonPayload::parse()),
            ),
        );
    }

    public function whatsappTemplateDeleteJson(string $id): void
    {
        JsonResponse::sendResult(
            $this->mediator->send(new DeleteTemplateCommand($id)),
        );
    }
}

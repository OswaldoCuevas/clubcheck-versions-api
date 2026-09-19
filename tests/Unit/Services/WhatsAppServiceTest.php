<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WhatsAppService;
use Models\MessageSentModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class WhatsAppServiceTest extends TestCase
{
    private WhatsAppService $service;
    private MessageSentModel&MockObject $messageModel;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 3) . '/app/Services/WhatsAppService.php';

        $reflection = new ReflectionClass(WhatsAppService::class);
        $this->service = $reflection->newInstanceWithoutConstructor();
        $this->setProperty('config', [
            'default_country_code' => '52',
            'max_bulk_size' => 100,
        ]);

        $this->messageModel = $this->createMock(MessageSentModel::class);
        $this->setProperty('messageSentModel', $this->messageModel);
    }

    public function testPhoneNormalizationIsPureAndDoesNotSendAnything(): void
    {
        self::assertSame('526141234567', $this->service->normalizePhone('(614) 123-4567'));
        self::assertSame('5216141234567', $this->service->normalizePhone('+52 1 614 123 4567'));
    }

    public function testTemplatePayloadHasMetaRequiredShapeWithoutCallingNetwork(): void
    {
        $method = (new ReflectionClass(WhatsAppService::class))->getMethod('buildTemplatePayload');
        $components = [[
            'type' => 'body',
            'parameters' => [['type' => 'text', 'text' => 'Juan']],
        ]];

        $payload = $method->invoke($this->service, '526141234567', 'subscription', 'es_MX', $components);

        self::assertSame('whatsapp', $payload['messaging_product']);
        self::assertSame('individual', $payload['recipient_type']);
        self::assertSame('526141234567', $payload['to']);
        self::assertSame('template', $payload['type']);
        self::assertSame('subscription', $payload['template']['name']);
        self::assertSame('es_MX', $payload['template']['language']['code']);
        self::assertSame($components, $payload['template']['components']);
    }

    public function testBulkWithMissingSubscriptionIdNeverReachesSendingCode(): void
    {
        $this->messageModel->expects(self::once())->method('countSuccessfulByMonth')->willReturn(0);

        $result = $this->service->sendBulk([
            ['template' => 'subscription', 'phone' => '526141234567'],
        ], 'customer-test');

        self::assertSame(0, $result['successCount']);
        self::assertSame(1, $result['failedCount']);
        self::assertSame('subscriptionId es requerido', $result['failed'][0]['error']);
    }

    public function testBulkWithMissingPhoneFailsOnceAndNeverSends(): void
    {
        $this->messageModel->expects(self::once())->method('countSuccessfulByMonth')->willReturn(0);

        $result = $this->service->sendBulk([
            ['template' => 'subscription', 'subscriptionId' => 'sub-test'],
        ], 'customer-test');

        self::assertSame(0, $result['successCount']);
        self::assertSame(1, $result['failedCount']);
        self::assertSame('El teléfono es requerido', $result['failed'][0]['error']);
    }

    public function testUnknownBulkTemplateNeverSends(): void
    {
        $this->messageModel->expects(self::once())->method('countSuccessfulByMonth')->willReturn(0);

        $result = $this->service->sendBulk([
            ['template' => 'not-a-template', 'subscriptionId' => 'sub-test', 'phone' => '526141234567'],
        ], 'customer-test');

        self::assertSame(0, $result['successCount']);
        self::assertSame(1, $result['failedCount']);
        self::assertSame('Template desconocido: not-a-template', $result['failed'][0]['error']);
    }

    public function testResultContractKeepsAllExpectedFields(): void
    {
        self::assertSame([
            'success' => true,
            'errorMessage' => null,
            'responseContent' => '{"ok":true}',
            'statusCode' => 200,
            'subscriptionId' => 'sub-1',
            'messageId' => 'wamid-1',
        ], WhatsAppService::createResult(true, null, '{"ok":true}', 200, 'sub-1', 'wamid-1'));
    }

    private function setProperty(string $name, mixed $value): void
    {
        $property = (new ReflectionClass(WhatsAppService::class))->getProperty($name);
        $property->setValue($this->service, $value);
    }
}

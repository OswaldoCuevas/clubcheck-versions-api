<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WhatsApp;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
use App\Modules\WhatsApp\Features\UpdateManualProspect\Command;
use App\Modules\WhatsApp\Features\UpdateManualProspect\Handler;
use PHPUnit\Framework\TestCase;

final class UpdateManualProspectHandlerTest extends TestCase
{
    public function testHandlerNormalizesManualProspectFieldsAndAttribution(): void
    {
        $repository = $this->createMock(IWhatsAppProspectRepository::class);
        $repository->expects(self::once())
            ->method('updateManualProspect')
            ->with(
                'prospect-1',
                self::callback(static function (array $change): bool {
                    self::assertSame('5216131210267', $change['wa_id']);
                    self::assertSame('+526131210267', $change['phone_number']);
                    self::assertSame('Cliente corregido', $change['name']);
                    self::assertSame('click_to_whatsapp', $change['origin']);
                    self::assertSame('ad-123', $change['ad_id']);
                    self::assertSame('campaign-456', $change['campaign_id']);
                    self::assertSame('ctwa-789', json_decode(
                        $change['attribution_json'],
                        true,
                    )['ctwa_clid']);
                    self::assertTrue($change['marketing_consent']);

                    return true;
                }),
            )
            ->willReturn([
                'updated' => true,
                'prospect' => ['Id' => 'prospect-1'],
            ]);

        $result = (new Handler($repository))->handle(new Command('prospect-1', [
            'name' => 'Cliente corregido',
            'country_calling_code' => '52',
            'national_phone_number' => '6131210267',
            'contact_at' => null,
            'origin' => 'click_to_whatsapp',
            'ctwa_clid' => 'ctwa-789',
            'source_id' => 'ad-123',
            'campaign_id' => 'campaign-456',
            'notes' => 'Nota corregida',
            'marketing_consent' => true,
            'converted_at' => gmdate(DATE_ATOM),
            'sale_value' => 1200,
        ]));

        self::assertSame(['Id' => 'prospect-1'], $result->data['prospect']);
    }

    public function testHandlerRejectsMexicanPhoneWithWhatsAppPrefixOne(): void
    {
        $repository = $this->createMock(IWhatsAppProspectRepository::class);
        $repository->expects(self::never())->method('updateManualProspect');

        try {
            (new Handler($repository))->handle(new Command('prospect-1', [
                'country_calling_code' => '52',
                'national_phone_number' => '16131210267',
                'origin' => 'unknown',
                'marketing_consent' => false,
            ]));
            self::fail('Expected validation failure.');
        } catch (ApiException $exception) {
            self::assertSame(
                ApiErrorCode::WhatsAppValidationFailed,
                $exception->getApiErrorCode(),
            );
        }
    }

    public function testHandlerForbidsEditingWebhookProspects(): void
    {
        $repository = $this->createStub(IWhatsAppProspectRepository::class);
        $repository->method('updateManualProspect')
            ->willReturn([
                'updated' => false,
                'reason' => 'not_manual',
            ]);

        try {
            (new Handler($repository))->handle(new Command('prospect-1', [
                'country_calling_code' => '52',
                'national_phone_number' => '6131210267',
                'origin' => 'unknown',
                'marketing_consent' => false,
            ]));
            self::fail('Expected forbidden error.');
        } catch (ApiException $exception) {
            self::assertSame(ApiErrorCode::Forbidden, $exception->getApiErrorCode());
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WhatsApp;

use App\Exceptions\ApiException;
use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
use App\Modules\WhatsApp\Features\RegisterManualCustomer\Command;
use App\Modules\WhatsApp\Features\RegisterManualCustomer\Handler;
use PHPUnit\Framework\TestCase;

final class RegisterManualCustomerHandlerTest extends TestCase
{
    public function testManualCustomerNormalizesPhoneAndPreservesConfirmedMetaAttribution(): void
    {
        $repository = $this->createMock(IWhatsAppProspectRepository::class);
        $repository->expects(self::once())
            ->method('registerManualCustomer')
            ->with(self::callback(static function (array $customer): bool {
                self::assertSame('5215512345678', $customer['wa_id']);
                self::assertSame('+525512345678', $customer['phone_number']);
                self::assertSame('click_to_whatsapp', $customer['origin']);
                self::assertSame('ctwa-click-123', $customer['ad_id']);
                self::assertSame('campaign-456', $customer['campaign_id']);
                self::assertTrue($customer['marketing_consent']);

                $attribution = json_decode($customer['attribution_json'], true);
                self::assertSame('ctwa-click-123', $attribution['ctwa_clid']);
                self::assertSame('campaign-456', $attribution['campaign_id']);
                self::assertTrue($attribution['manual_entry']);

                return true;
            }))
            ->willReturn([
                'id' => 'prospect-1',
                'created' => true,
                'purchase_event_status' => 'pending',
                'purchase_event_error' => null,
                'origin' => 'click_to_whatsapp',
                'marketing_consent' => true,
            ]);

        $handler = new Handler($repository);
        $result = $handler->handle(new Command([
            'name' => 'Cliente anterior',
            'country_calling_code' => '52',
            'national_phone_number' => '5512345678',
            'origin' => 'click_to_whatsapp',
            'ctwa_clid' => 'ctwa-click-123',
            'source_id' => 'ctwa-click-123',
            'campaign_id' => 'campaign-456',
            'converted_at' => gmdate(DATE_ATOM),
            'contact_at' => null,
            'sale_value' => 1299.50,
            'marketing_consent' => true,
        ]));

        self::assertSame('prospect-1', $result->data['prospect_id']);
        self::assertSame('pending', $result->data['purchase_event_status']);
    }

    public function testManualCustomerRejectsPhoneWithoutCountryCode(): void
    {
        $repository = $this->createMock(IWhatsAppProspectRepository::class);
        $repository->expects(self::never())->method('registerManualCustomer');

        $this->expectException(ApiException::class);

        (new Handler($repository))->handle(new Command([
            'country_calling_code' => '52',
            'national_phone_number' => '551234567',
            'converted_at' => gmdate(DATE_ATOM),
        ]));
    }

    public function testMexicanNumberRequiresTenNationalDigits(): void
    {
        $repository = $this->createMock(IWhatsAppProspectRepository::class);
        $repository->expects(self::never())->method('registerManualCustomer');

        $this->expectException(ApiException::class);

        (new Handler($repository))->handle(new Command([
            'country_calling_code' => '52',
            'national_phone_number' => '15512345678',
            'converted_at' => gmdate(DATE_ATOM),
        ]));
    }

    public function testOtherCountryNumberUsesSelectedCallingCode(): void
    {
        $repository = $this->createMock(IWhatsAppProspectRepository::class);
        $repository->expects(self::once())
            ->method('registerManualCustomer')
            ->with(self::callback(static function (array $customer): bool {
                self::assertSame('+14155550123', $customer['phone_number']);
                self::assertSame('14155550123', $customer['wa_id']);

                return true;
            }))
            ->willReturn([
                'id' => 'prospect-2',
                'created' => true,
                'purchase_event_status' => 'pending',
                'purchase_event_error' => null,
                'origin' => 'unknown',
                'marketing_consent' => false,
            ]);

        (new Handler($repository))->handle(new Command([
            'country_calling_code' => '1',
            'national_phone_number' => '4155550123',
            'converted_at' => gmdate(DATE_ATOM),
        ]));
    }

    public function testManualAttributionCannotBeAddedToAnUnconfirmedOrigin(): void
    {
        $repository = $this->createMock(IWhatsAppProspectRepository::class);
        $repository->expects(self::never())->method('registerManualCustomer');

        $this->expectException(ApiException::class);

        (new Handler($repository))->handle(new Command([
            'country_calling_code' => '52',
            'national_phone_number' => '5512345678',
            'origin' => 'unknown',
            'ctwa_clid' => 'not-confirmed',
            'converted_at' => gmdate(DATE_ATOM),
        ]));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WhatsApp;

use App\Modules\WhatsApp\Domain\WhatsAppPhoneNumber;
use PHPUnit\Framework\TestCase;

final class WhatsAppPhoneNumberTest extends TestCase
{
    public function testMexicanNationalNumberKeepsE164SeparateFromWhatsAppId(): void
    {
        self::assertSame(
            [
                'e164' => '+526131210267',
                'wa_id' => '5216131210267',
            ],
            WhatsAppPhoneNumber::fromNationalNumber('52', '6131210267'),
        );
    }

    public function testMexicanE164AndWhatsAppIdAreEquivalentIdentityCandidates(): void
    {
        self::assertContains(
            '5216131210267',
            WhatsAppPhoneNumber::identityCandidates('+526131210267'),
        );
        self::assertContains(
            '526131210267',
            WhatsAppPhoneNumber::identityCandidates('5216131210267'),
        );
    }

    public function testMetaPhoneNormalizationRemovesMexicanWhatsAppOne(): void
    {
        self::assertSame(
            '+526131210267',
            WhatsAppPhoneNumber::normalizeForMeta('5216131210267'),
        );
    }
}

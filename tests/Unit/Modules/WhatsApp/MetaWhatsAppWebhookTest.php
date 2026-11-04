<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WhatsApp;

use App\Modules\WhatsApp\Contracts\IWhatsAppProspectRepository;
use App\Modules\WhatsApp\Features\VerifyMetaWebhook\Handler as VerifyMetaWebhookHandler;
use App\Modules\WhatsApp\Features\VerifyMetaWebhook\Query as VerifyMetaWebhookQuery;
use App\Modules\WhatsApp\Services\MetaWebhookSignatureVerifier;
use App\Modules\WhatsApp\Services\MetaWhatsAppSettings;
use App\Modules\WhatsApp\Services\WhatsAppWebhookProcessor;
use PHPUnit\Framework\TestCase;

final class MetaWhatsAppWebhookTest extends TestCase
{
    public function testSignatureUsesExactRawBodyAndRejectsTampering(): void
    {
        $secret = 'test-meta-app-secret';
        $rawBody = '{"object":"whatsapp_business_account","entry":[]}';
        $settings = new MetaWhatsAppSettings([
            'app_secret' => $secret,
        ]);
        $verifier = new MetaWebhookSignatureVerifier($settings);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        self::assertTrue($verifier->isValid($rawBody, $signature));
        self::assertFalse($verifier->isValid($rawBody . ' ', $signature));
        self::assertFalse($verifier->isValid($rawBody, null));
    }

    public function testSignatureIsRejectedWhenAppSecretIsNotConfigured(): void
    {
        $verifier = new MetaWebhookSignatureVerifier(new MetaWhatsAppSettings([]));

        self::assertFalse($verifier->isValid('{}', 'sha256=' . str_repeat('a', 64)));
    }

    public function testWebhookVerificationRequiresSubscribeModeAndMatchingToken(): void
    {
        $handler = new VerifyMetaWebhookHandler(
            new MetaWhatsAppSettings([
                'webhook_verify_token' => 'configured-token',
            ]),
        );

        self::assertSame(
            'challenge-value',
            $handler->handle(
                new VerifyMetaWebhookQuery('subscribe', 'configured-token', 'challenge-value'),
            ),
        );
        self::assertNull(
            $handler->handle(
                new VerifyMetaWebhookQuery('subscribe', 'incorrect-token', 'challenge-value'),
            ),
        );
        self::assertNull(
            $handler->handle(
                new VerifyMetaWebhookQuery('unsubscribe', 'configured-token', 'challenge-value'),
            ),
        );
    }

    public function testIncomingClickToWhatsAppMessagePreservesReferralData(): void
    {
        $repository = $this->createMock(IWhatsAppProspectRepository::class);
        $repository->expects(self::once())
            ->method('recordMessage')
            ->with(self::callback(static function (array $message): bool {
                self::assertSame('5215550101234', $message['wa_id']);
                self::assertSame('Prospecto de prueba', $message['name']);
                self::assertSame('incoming', $message['direction']);
                self::assertSame('wamid.test-1', $message['wamid']);
                self::assertSame('click_to_whatsapp', $message['origin']);
                self::assertSame('ad-123', $message['ad_id']);
                self::assertSame('campaign-456', $message['campaign_id']);
                self::assertSame('Hola, quiero información', $message['content']);
                self::assertStringContainsString('ctwa-test-id', $message['attribution_json']);

                return true;
            }))
            ->willReturn('prospect-id');

        $processor = new WhatsAppWebhookProcessor(
            $repository,
            new MetaWhatsAppSettings([
                'phone_number_id' => '1061545387037365',
            ]),
        );

        $processed = $processor->process([
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => '1061545387037365',
                                ],
                                'contacts' => [
                                    [
                                        'wa_id' => '5215550101234',
                                        'profile' => [
                                            'name' => 'Prospecto de prueba',
                                        ],
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'id' => 'wamid.test-1',
                                        'from' => '5215550101234',
                                        'timestamp' => '1790000000',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Hola, quiero información',
                                        ],
                                        'referral' => [
                                            'source_type' => 'ad',
                                            'source_id' => 'ad-123',
                                            'campaign_id' => 'campaign-456',
                                            'ctwa_clid' => 'ctwa-test-id',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame(1, $processed);
    }

    public function testUnknownPhoneNumberEventsAreIgnoredWhenPhoneIdIsConfigured(): void
    {
        $repository = $this->createMock(IWhatsAppProspectRepository::class);
        $repository->expects(self::never())->method('recordMessage');
        $repository->expects(self::never())->method('recordMessageStatus');

        $processor = new WhatsAppWebhookProcessor(
            $repository,
            new MetaWhatsAppSettings([
                'phone_number_id' => 'configured-phone-id',
            ]),
        );

        $processed = $processor->process([
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => 'different-phone-id',
                                ],
                                'messages' => [
                                    [
                                        'from' => '5215550101234',
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame(0, $processed);
    }
}

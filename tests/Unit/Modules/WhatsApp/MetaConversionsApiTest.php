<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WhatsApp;

use App\Modules\WhatsApp\Services\MetaConversionsApi;
use App\Modules\WhatsApp\Services\MetaWhatsAppSettings;
use PHPUnit\Framework\TestCase;

final class MetaConversionsApiTest extends TestCase
{
    public function testEventsOlderThanSevenDaysAreNotSentToMeta(): void
    {
        $api = new MetaConversionsApi(new MetaWhatsAppSettings([
            'access_token' => 'test-token',
            'dataset_id' => 'test-dataset',
            'graph_api_version' => 'v26.0',
        ]));
        $eventTime = gmdate('Y-m-d H:i:s', time() - (8 * 24 * 60 * 60));

        $result = $api->send([
            'EventName' => 'Purchase',
            'EventTime' => $eventTime,
            'EventJson' => json_encode([
                'event_name' => 'Purchase',
                'event_id' => 'purchase-1',
                'event_time' => strtotime($eventTime . ' UTC'),
                'action_source' => 'business_messaging',
            ], JSON_THROW_ON_ERROR),
            'PhoneNumber' => '+525512345678',
            'AttributionJson' => null,
            'SaleValue' => null,
        ]);

        self::assertFalse($result['success']);
        self::assertFalse($result['retryable']);
        self::assertSame(
            'Meta solo acepta eventos de conversión ocurridos en los últimos 7 días.',
            $result['error'],
        );
    }
}

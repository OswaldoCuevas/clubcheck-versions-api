<?php

declare(strict_types=1);

namespace Tests\Characterization\Customers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Characterization\Support\CustomerHttpTestCase;

#[Group('characterization')]
final class CustomerBaselineContractTest extends CustomerHttpTestCase
{
    /** @return iterable<string, array{0: array<string, mixed>}> */
    public static function baselineCases(): iterable
    {
        foreach (self::inventory()['endpoints'] as $case) {
            yield $case['id'] => [$case];
        }
    }

    #[DataProvider('baselineCases')]
    public function test_current_http_contract_for_every_customer_method(array $case): void
    {
        $this->assertInventoryCase($case);
    }
}

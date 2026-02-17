<?php

namespace Models;

require_once __DIR__ . '/BaseDesktopSyncModel.php';

class BarcodeLookupCacheDesktopModel extends BaseDesktopSyncModel
{
    protected string $table = 'barcodelookupcachedesktop';
    protected string $primaryKey = 'Id';
    protected bool $autoIncrement = false;
    protected array $columns = [
        'Id',
        'Barcode',
        'Provider',
        'Found',
        'RawJson',
        'CachedAt',
        'CustomerApiId',
    ];
    protected array $nullableColumns = [
        'RawJson',
    ];
    protected array $booleanColumns = ['Found'];
    protected ?string $softDeleteColumn = null;
    protected ?string $orderBy = 'CachedAt';
}

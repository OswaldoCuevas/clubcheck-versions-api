<?php

require_once __DIR__ . '/../app/Services/IsapiCommandService.php';
require_once __DIR__ . '/../app/Services/IsapiPictureResult.php';

use App\Services\IsapiCommandService;
use App\Services\IsapiPictureResult;

function rejects(callable $test): void
{
    try { $test(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Expected validation rejection.');
}

$service = new IsapiCommandService();
$path = '/LOCALS/pic/acsLinkCap/202609_00/25_162800_30075_0.jpeg@WEB000000000063';
$definition = $service->definition('get_event_picture', ['picturePath' => $path]);
if ($definition['parameters']['picturePath'] !== $path) {
    throw new RuntimeException('The Hikvision suffix must be preserved.');
}
foreach ([
    'https://192.168.1.72' . $path, '//evil.test' . $path,
    '/LOCALS/pic/acsLinkCap/../secret.jpeg',
    '/LOCALS/pic/acsLinkCap/%2e%2e/secret.jpeg',
    '/LOCALS/pic/acsLinkCap/%252e%252e/secret.jpeg',
    '/LOCALS/pic/acsLinkCap/foo\\bar.jpeg',
    $path . '?redirect=1', $path . '#fragment',
    '/LOCALS/pic/acsLinkCap/image.svg', $path . "\n", [],
] as $invalid) {
    rejects(fn() => $service->definition('get_event_picture', ['picturePath' => $invalid]));
}

$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC';
$result = ['body' => $png, 'contentType' => 'image/png', 'metadata' => ['bodyEncoding' => 'base64']];
IsapiPictureResult::validate($result);
rejects(fn() => IsapiPictureResult::validate(array_replace($result, ['contentType' => 'image/jpeg'])));
rejects(fn() => IsapiPictureResult::validate(array_replace($result, ['body' => base64_encode('<svg></svg>')])));
rejects(fn() => IsapiPictureResult::validate(array_replace($result, ['body' => $png . "\n"])));
rejects(fn() => IsapiPictureResult::validate(array_replace($result, ['body' => '%%%'])));
rejects(fn() => IsapiPictureResult::validate(array_replace($result, ['body' => ''])));
rejects(fn() => IsapiPictureResult::validate(array_replace($result, ['metadata' => []])));
rejects(fn() => IsapiPictureResult::validate(array_replace($result, ['body' => base64_encode(str_repeat('x', IsapiPictureResult::MAX_BYTES + 1))])));
echo "ISAPI picture validation passed.\n";

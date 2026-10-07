<?php
// Only against a disposable MySQL instance explicitly supplied by the operator.
// php tests/member-images-integration.php 33157
if (PHP_SAPI !== 'cli' || !isset($argv[1]) || !ctype_digit($argv[1])) {
    throw new RuntimeException('Supply the port of an isolated disposable MySQL server.');
}
$root = dirname(__DIR__);
$scratch = $root . '/tmp/member-images-' . bin2hex(random_bytes(4));
mkdir($scratch, 0700, true);
mkdir($scratch . '/private', 0700);
$database = 'member_images_test_' . bin2hex(random_bytes(4));
putenv('DEV_DB_HOST=127.0.0.1');
putenv('DEV_DB_PORT=' . $argv[1]);
putenv('DEV_DB_DATABASE=' . $database);
putenv('DEV_DB_USERNAME=root');
putenv('DEV_DB_PASSWORD=');
putenv('MEMBER_IMAGES_DIRECTORY=' . $scratch . '/private');
$_ENV['APP_MODE'] = 'DEV';
$_ENV['JWT_SECRET_KEY'] = 'isolated-test-key';
$_SERVER['DOCUMENT_ROOT'] = $root . '/public';
require $root . '/config/bootstrap.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$admin = new mysqli('127.0.0.1', 'root', '', '', (int) $argv[1]);
$admin->query("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$server = null;
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function rejects(callable $call, int $status): void {
    try { $call(); } catch (App\Exceptions\ApiException $e) {
        check($e->getStatusCode() === $status, 'Unexpected status: ' . $e->getStatusCode());
        return;
    }
    throw new RuntimeException('Expected rejection');
}
function multipart(string $bytes, int $version, ?string $hash = null): string {
    $fields = ['archivo' => $bytes, 'hash' => $hash ?? hash('sha256', $bytes), 'baseVersion' => (string) $version];
    $body = '';
    foreach ($fields as $name => $value) {
        $body .= '--test-boundary' . "\r\nContent-Disposition: form-data; name=\"$name\"" . ($name === 'archivo' ? '; filename="local.png"' : '') . "\r\n\r\n$value\r\n";
    }
    return $body . "--test-boundary--\r\n";
}
try {
    $db = new Database();
    $db->query('CREATE TABLE Customers (Id VARCHAR(64) PRIMARY KEY, Token VARCHAR(100), TokenJwt TEXT, IsActive TINYINT, LastSeen DATETIME) ENGINE=InnoDB');
    $schema = file_get_contents($root . '/database/schema.sql');
    preg_match('/CREATE TABLE `UsersDesktop` \([\s\S]+?ENGINE=InnoDB[^;]+;/', $schema, $table);
    $db->query($table[0]);
    $db->query('ALTER TABLE UsersDesktop ADD Email VARCHAR(255) NULL');
    $migration = file_get_contents($root . '/database/migrations/016_create_member_images.sql');
    $db->query($migration);
    $db->query($migration);
    check(true, 'Migration runs twice');
    $jwt = new App\Services\JwtService('isolated-test-key');
    $customerTokens = [];
    $portalTokens = [];
    foreach (['gym-a', 'gym-b'] as $gym) {
        $customerTokens[$gym] = $jwt->createToken(['typ' => 'customer', 'cid' => $gym, 'mkt' => 'machine']);
        $portalTokens[$gym] = $jwt->createToken(['customerId' => $gym, 'name' => $gym, 'adminId' => 'admin', 'role' => 2]);
        $db->insert('Customers', ['Id' => $gym, 'Token' => 'machine', 'TokenJwt' => $customerTokens[$gym], 'IsActive' => 1]);
    }
    $users = new Models\UsersDesktopModel();
    $record = ['Id' => 'member-a', 'Fullname' => 'Member', 'PhoneNumber' => '123', 'BirthDate' => '2000-01-01', 'CreatedOn' => '2026-10-01 12:00:00'];
    check($users->push('gym-a', [$record])[0]['success'], 'Legacy push');
    check($users->pull('gym-a')[0]['imagen'] === null, 'Legacy record has null image');
    $storage = new App\Services\MemberImageStorage($scratch . '/private');
    $service = new App\Services\MemberImageService($db, $storage);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC');
    $image = imagecreatetruecolor(2, 2);
    ob_start(); imagejpeg($image); $jpeg = ob_get_clean(); imagedestroy($image);
    $contentType = 'multipart/form-data; boundary=test-boundary';
    $upload = fn($bytes, $version) => App\Services\MemberImageUpload::parse($contentType, multipart($bytes, $version));
    $first = $service->put('gym-a', 'member-a', $upload($png, 0));
    check($first['version'] === 1, 'Initial upload');
    check($service->put('gym-a', 'member-a', $upload($png, 0)) === $first, 'Idempotent retry');
    check(count(glob($scratch . '/private/*')) === 1, 'Retry does not duplicate files');
    rejects(fn() => $service->put('gym-b', 'member-a', $upload($jpeg, 1)), 404);
    rejects(fn() => $service->get('gym-b', 'member-a'), 404);
    rejects(fn() => $service->put('gym-a', 'missing', $upload($png, 0)), 404);
    rejects(fn() => $service->put('gym-a', 'member-a', $upload($jpeg, 0)), 409);
    rejects(fn() => $service->put('gym-a', 'member-a', $upload('<svg/>', 1)), 415);
    rejects(fn() => App\Services\MemberImageUpload::validate($png, str_repeat('0', 64)), 422);
    rejects(fn() => App\Services\MemberImageUpload::validate(str_repeat('x', 5242881), ''), 413);
    rejects(fn() => App\Services\MemberImageUpload::parse($contentType, substr(multipart($png, 0), 0, -8)), 422);
    rejects(fn() => App\Services\MemberImageUpload::parse('application/json', '{}'), 415);
    $second = $service->put('gym-a', 'member-a', $upload($jpeg, 1));
    check($second['version'] === 2 && $second['imageId'] !== $first['imageId'], 'Replacement');
    check($service->get('gym-a', 'member-a')['bytes'] === $jpeg, 'Exact stored bytes');
    check($users->pull('gym-a')[0]['imagen'] === $second, 'Pull metadata');
    check(!$users->push('gym-b', [$record])[0]['success'], 'Push cannot transfer member');
    check($users->push('gym-a', [$record + ['imagen' => null]])[0]['success'], 'Old push preserves image');
    check($users->pull('gym-a')[0]['imagen'] === $second, 'Image survives push');
    // File loss is recoverable by an identical pending upload without incrementing version.
    unlink($scratch . '/private/' . $second['imageId']);
    $repaired = $service->put('gym-a', 'member-a', $upload($jpeg, 1));
    check($repaired['version'] === 2 && $service->get('gym-a', 'member-a')['bytes'] === $jpeg, 'Repair');
    // Inject a database failure after file write and verify the old reference survives.
    $db->query("CREATE TRIGGER reject_image_update BEFORE UPDATE ON MemberImagesDesktop FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'simulated failure'");
    try { $service->put('gym-a', 'member-a', $upload($png, 2)); throw new RuntimeException('Expected DB failure'); }
    catch (mysqli_sql_exception $e) { check(true, 'DB failure injected'); }
    check($users->pull('gym-a')[0]['imagen'] === $repaired, 'Rollback preserves reference');
    check($service->get('gym-a', 'member-a')['bytes'] === $jpeg, 'Rollback preserves file');
    $db->query('DROP TRIGGER reject_image_update');
    // Use the real front controller, router, JWT middleware and HTTP endpoints.
    $router = $scratch . '/router.php';
    file_put_contents($router, '<?php $_ENV["APP_MODE"]="DEV"; $_ENV["JWT_SECRET_KEY"]="isolated-test-key"; $_SERVER["SCRIPT_NAME"]="/public/index.php"; unset($_SERVER["REMOTE_ADDR"]); require ' . var_export($root . '/public/index.php', true) . ';');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $root . '/public', $router], [0 => ['pipe', 'r'], 1 => ['file', $scratch . '/http.log', 'a'], 2 => ['file', $scratch . '/http.log', 'a']], $pipes, $root);
    check(is_resource($server), 'HTTP server starts');
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    $http = function(string $method, string $path, ?string $token, ?string $body = null) use ($address, $contentType): array {
        $curl = curl_init('http://' . $address . $path);
        $headers = ['Content-Type: ' . $contentType];
        if ($token !== null) { $headers[] = 'Authorization: Bearer ' . $token; }
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HEADER => true]);
        if ($body !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, $body); }
        $response = curl_exec($curl);
        if ($response === false) { throw new RuntimeException(curl_error($curl)); }
        $result = [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), substr($response, curl_getinfo($curl, CURLINFO_HEADER_SIZE)), substr($response, 0, curl_getinfo($curl, CURLINFO_HEADER_SIZE))];
        curl_close($curl); return $result;
    };
    $path = '/api/customers/socios/member-a/imagen';
    check($users->push('gym-a', [array_replace($record, ['Id' => 'member-noimage', 'Fullname' => 'Z No image'])])[0]['success'], 'Member without image');
    check($http('GET', '/api/desktop/socios/member-noimage/imagen', $portalTokens['gym-a'])[0] === 404, 'HTTP no image fallback');
    check($http('GET', '/api/desktop/socios/member-a/imagen', null)[0] === 401, 'HTTP unauthenticated portal read');
    check($http('PUT', $path, null, multipart($png, 2))[0] === 401, 'HTTP unauthenticated');
    check($http('PUT', $path, 'invalid', multipart($png, 2))[0] === 401, 'HTTP invalid JWT');
    check($http('PUT', $path, $customerTokens['gym-b'], multipart($png, 2))[0] === 404, 'HTTP cross gym PUT');
    check($http('GET', $path, $customerTokens['gym-b'])[0] === 404, 'HTTP cross gym GET');
    check($http('PUT', $path, $portalTokens['gym-a'], multipart($png, 2))[0] === 401, 'Portal JWT cannot upload');
    $response = $http('PUT', $path, $customerTokens['gym-a'], multipart($png, 2));
    check($response[0] === 200, 'HTTP PUT: ' . $response[1]);
    $third = json_decode($response[1], true);
    check($third['version'] === 3, 'HTTP version');
    $response = $http('PUT', $path, $customerTokens['gym-a'], multipart($png, 2));
    check(json_decode($response[1], true) === $third, 'HTTP retry');
    check($http('PUT', $path, $customerTokens['gym-a'], multipart($jpeg, 2))[0] === 409, 'HTTP conflict');
    check($http('PUT', $path, $customerTokens['gym-a'], multipart('<svg/>', 3))[0] === 415, 'HTTP invalid file');
    check($http('PUT', $path, $customerTokens['gym-a'], multipart(str_repeat('x', 5242881), 3))[0] === 413, 'HTTP oversized file');
    check($http('PUT', $path, $customerTokens['gym-a'], multipart($png, 3, str_repeat('0', 64)))[0] === 422, 'HTTP wrong hash');
    $response = $http('GET', '/api/desktop/socios/member-a/imagen', $portalTokens['gym-a']);
    check($response[0] === 200 && $response[1] === $png, 'Portal authenticated binary');
    check(stripos($response[2], 'Cache-Control: private, no-store') !== false, 'Private cache headers');
    check($http('GET', '/api/desktop/socios/member-a/imagen', $portalTokens['gym-b'])[0] === 404, 'Portal cross gym');
    unlink($scratch . '/private/' . $third['imageId']);
    check($http('GET', '/api/desktop/socios/member-a/imagen', $portalTokens['gym-a'])[0] === 503, 'HTTP missing storage file is retryable');
    $response = $http('PUT', $path, $customerTokens['gym-a'], multipart($png, 2));
    check($response[0] === 200, 'HTTP repair succeeds');
    $third = json_decode($response[1], true);
    check($third['version'] === 3, 'HTTP repair preserves content version');
    $response = $http('GET', '/api/desktop/users', $portalTokens['gym-a']);
    $payload = json_decode($response[1], true);
    check($response[0] === 200 && $payload['members']['data'][0]['imagen'] === $third, 'Portal listing metadata: ' . $response[1]);
    check(strpos($response[1], $scratch) === false, 'No filesystem path in JSON');
    $db->update('UsersDesktop', ['Removed' => 1], 'Id = ?', ['member-a']);
    check($http('GET', '/api/desktop/socios/member-a/imagen', $portalTokens['gym-a'])[0] === 404, 'Removed member hidden');
    // Concurrent initial uploads lock the parent row and return the same reference.
    check($users->push('gym-a', [array_replace($record, ['Id' => 'member-race'])])[0]['success'], 'Concurrent member seed');
    $worker = $scratch . '/worker.php';
    file_put_contents($worker, '<?php $_ENV["APP_MODE"]="DEV"; $_SERVER["DOCUMENT_ROOT"]=' . var_export($root . '/public', true) . '; require ' . var_export($root . '/config/bootstrap.php', true) . '; $bytes=base64_decode($argv[1]); $service=new App\\Services\\MemberImageService(new Database(), new App\\Services\\MemberImageStorage(getenv("MEMBER_IMAGES_DIRECTORY"))); try { echo json_encode($service->put("gym-a", "member-race", ["archivo"=>$bytes,"hash"=>hash("sha256",$bytes),"baseVersion"=>(int)$argv[2]])); } catch(App\\Exceptions\\ApiException $e) { echo json_encode(["status"=>$e->getStatusCode()]); }');
    $race = function(array $bytes, int $version) use ($worker): array {
        $workers = [];
        foreach ($bytes as $imageBytes) {
            $process = proc_open([PHP_BINARY, $worker, base64_encode($imageBytes), (string) $version], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $channels);
            $workers[] = [$process, $channels];
        }
        $results = [];
        foreach ($workers as [$process, $channels]) {
            fclose($channels[0]);
            $result = stream_get_contents($channels[1]); fclose($channels[1]);
            $error = stream_get_contents($channels[2]); fclose($channels[2]);
            check(proc_close($process) === 0, 'Worker failed: ' . $error);
            $results[] = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        }
        return $results;
    };
    $results = $race([$png, $png], 0);
    check($results[0] === $results[1] && $results[0]['version'] === 1, 'Concurrent identical creation');
    // Different content using one base version: exactly one winner.
    $otherImage = imagecreatetruecolor(3, 3); ob_start(); imagepng($otherImage); $other = ob_get_clean(); imagedestroy($otherImage);
    $results = $race([$jpeg, $other], 1);
    check(count(array_filter($results, fn($row) => ($row['status'] ?? 0) === 409)) === 1, 'Concurrent replacement conflict');
    check(count(array_filter($results, fn($row) => ($row['version'] ?? 0) === 2)) === 1, 'One concurrent winner');
    echo "$checks member image integration checks passed.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $admin->query("DROP DATABASE `$database`");
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scratch, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
    rmdir($scratch);
}

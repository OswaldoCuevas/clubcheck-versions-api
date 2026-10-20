<?php
// Solo datos temporales: nunca carga config/database.php ni utiliza el esquema de produccion.
require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../app/Services/UserPhotoService.php';
require_once __DIR__ . '/../app/Models/UsersDesktopModel.php';

use App\Services\UserPhotoService;
use App\Exceptions\ApiException;

/** Comprueba una regla del proceso de sincronizacion y aborta ante regresiones. */
function checkPhoto(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

/** Verifica el codigo HTTP de errores de negocio de la API de fotografias. */
function rejectsPhoto(callable $action, int $status): void
{
    try { $action(); } catch (ApiException $e) {
        checkPhoto($e->getStatusCode() === $status, 'Unexpected status: ' . $e->getStatusCode());
        return;
    }
    throw new RuntimeException('Expected rejection ' . $status);
}

/** Ejecuta cargas, descargas y reportes CGI con JWT real para validar fotos sin datos productivos. */
function photoCgi(string $schema, string $directory, string $method, string $token, string $body = '', string $type = '', string $query = 'userId=user-a'): array
{
    $env = array_merge(getenv(), ['REDIRECT_STATUS' => '1', 'REQUEST_METHOD' => $method,
        'SCRIPT_FILENAME' => __DIR__ . '/user-photos-cgi.php', 'QUERY_STRING' => $query,
        'CONTENT_TYPE' => $type, 'CONTENT_LENGTH' => (string) strlen($body),
        'HTTP_AUTHORIZATION' => $token === '' ? '' : 'Bearer ' . $token,
        'PHOTO_TEST_SCHEMA' => $schema, 'PHOTO_TEST_DIRECTORY' => $directory]);
    unset($env['REMOTE_ADDR']);
    $process = proc_open(['php-cgi', '-d', 'xdebug.mode=off', '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=7M'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__, $env);
    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    checkPhoto(proc_close($process) === 0, 'CGI failed: ' . $error);
    $parts = explode("\r\n\r\n", $output, 2);
    checkPhoto(count($parts) === 2, 'CGI response missing headers: ' . $output);
    preg_match('/Status: ([0-9]+)/', $parts[0], $status);
    return ['status' => isset($status[1]) ? (int) $status[1] : 200, 'headers' => $parts[0], 'body' => $parts[1]];
}

class PhotoTestDatabase extends Database
{
    public bool $failWrite = false;
    public bool $loseCommitResponse = false;

    /** Conecta al esquema efimero; permite un puerto MySQL aislado para pruebas sin servidor operativo. */
    public function __construct(string $name = '')
    {
        mysqli::__construct('localhost', 'root', '', $name, (int) (getenv('PHOTO_TEST_PORT') ?: 3306));
        $this->set_charset('utf8mb4');
    }

    /** Simula un fallo de persistencia al reemplazar una foto para verificar recuperacion. */
    public function update(string $table, array $data, string $where, array $params = []): bool
    {
        if ($this->failWrite) { throw new RuntimeException('Injected database failure'); }
        return parent::update($table, $data, $where, $params);
    }

    /** Simula respuesta perdida despues del commit para comprobar reintento sin perder archivos aceptados. */
    public function commitTransaction(): void
    {
        parent::commitTransaction();
        if ($this->loseCommitResponse) { throw new RuntimeException('Lost commit response'); }
    }
}

$directory = __DIR__ . '/.photo-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$schema = 'clubcheck_photo_test_' . bin2hex(random_bytes(8));
$admin = null;
$db = null;
try {
    $image = imagecreatetruecolor(600, 300);
    imagepng($image, $directory . '/input.png');
    imagedestroy($image);
    $file = ['tmp_name' => $directory . '/input.png', 'error' => UPLOAD_ERR_OK];
    $fields = ['version' => '1', 'sha256' => hash_file('sha256', $file['tmp_name'])];
    $validation = new UserPhotoService(null, $directory);
    $payload = $validation->validate($fields, $file);
    $thumbInfo = getimagesizefromstring($payload['thumbnail']);
    checkPhoto($thumbInfo[0] === 256 && $thumbInfo[1] === 128, 'Thumbnail ratio');
    rejectsPhoto(fn() => $validation->validate(['version' => '0', 'sha256' => $fields['sha256']], $file), 400);
    rejectsPhoto(fn() => $validation->validate(['version' => '9223372036854775808', 'sha256' => $fields['sha256']], $file), 400);
    rejectsPhoto(fn() => $validation->validate(['version' => '1', 'sha256' => str_repeat('0', 64)], $file), 400);
    rejectsPhoto(fn() => $validation->validate($fields, ['error' => UPLOAD_ERR_INI_SIZE]), 413);
    rejectsPhoto(fn() => $validation->validate($fields, []), 400);
    file_put_contents($directory . '/invalid', '<svg></svg>');
    rejectsPhoto(fn() => $validation->validate($fields, ['tmp_name' => $directory . '/invalid', 'error' => 0]), 415);
    file_put_contents($directory . '/large', str_repeat('x', UserPhotoService::MAX_BYTES + 1));
    rejectsPhoto(fn() => $validation->validate($fields, ['tmp_name' => $directory . '/large', 'error' => 0]), 413);
    $largeImage = imagecreatetruecolor(4097, 1);
    imagepng($largeImage, $directory . '/wide.png');
    imagedestroy($largeImage);
    rejectsPhoto(fn() => $validation->validate($fields, ['tmp_name' => $directory . '/wide.png', 'error' => 0]), 400);
    $jpeg = imagecreatetruecolor(5, 10);
    imagejpeg($jpeg, $directory . '/input.jpg');
    imagedestroy($jpeg);
    $jpg = $validation->validate(['version' => '2', 'sha256' => hash_file('sha256', $directory . '/input.jpg')], ['tmp_name' => $directory . '/input.jpg', 'error' => 0]);
    checkPhoto($jpg['mime'] === 'image/jpeg', 'Real JPEG accepted');
    echo "Image validation passed (PNG/JPEG, dimensions, hash, size, fields).\n";

    $admin = new PhotoTestDatabase();
    $admin->query("CREATE DATABASE $schema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db = new PhotoTestDatabase($schema);
    $db->query('CREATE TABLE Customers (Id VARCHAR(64) PRIMARY KEY, Token VARCHAR(64), TokenJwt TEXT, IsActive INT DEFAULT 1, LastSeen DATETIME) ENGINE=InnoDB');
    $initial = file_get_contents(__DIR__ . '/../database/schema.sql');
    preg_match('/CREATE TABLE `UsersDesktop` \(.*?ENGINE=InnoDB[^;]*;/s', $initial, $match);
    $db->query($match[0]);
    $db->query("INSERT INTO Customers (Id) VALUES ('gym-a'), ('gym-b')");
    $db->query("INSERT INTO UsersDesktop (Id, CustomerApiId, Fullname, PhoneNumber, BirthDate, Sync) VALUES ('user-a','gym-a','Temporary member','000','2000-01-01',1),('user-b','gym-b','Other member','000','2000-01-01',1)");
    $migration = file_get_contents(__DIR__ . '/../database/migrations/016_create_user_photos_desktop.sql');
    $db->query($migration);
    $db->query($migration);
    checkPhoto((int) $db->fetchOne('SELECT COUNT(*) AS n FROM UsersDesktop')['n'] === 2, 'Repeated migration preserves users');
    $service = new UserPhotoService($db, $directory . '/storage');
    checkPhoto($service->attach('gym-a', [['Id' => 'user-a']])[0]['photo'] === null, 'Optional photo');
    rejectsPhoto(fn() => $service->upload('gym-b', 'user-a', $payload), 404);
    rejectsPhoto(fn() => $service->thumbnail('gym-a', 'user-b'), 404);
    $first = $service->upload('gym-a', 'user-a', $payload);
    checkPhoto($first['userId'] === 'user-a' && $first['photo']['version'] === 1, 'Upload confirmation');
    $count = count(glob($directory . '/storage/*'));
    checkPhoto($service->upload('gym-a', 'user-a', $payload) === $first, 'Duplicate confirmation');
    checkPhoto(count(glob($directory . '/storage/*')) === $count, 'Duplicate has no files');
    rejectsPhoto(fn() => $service->upload('gym-a', 'user-a', array_replace($payload, ['sha256' => str_repeat('f', 64)])), 409);
    $second = $service->upload('gym-a', 'user-a', $jpg);
    rejectsPhoto(fn() => $service->upload('gym-a', 'user-a', $payload), 409);
    rejectsPhoto(fn() => $service->thumbnail('gym-b', 'user-a'), 404);
    checkPhoto($service->thumbnail('gym-a', 'user-a') === $jpg['thumbnail'], 'Replacement thumbnail');
    $db->failWrite = true;
    $before = count(glob($directory . '/storage/*'));
    try {
        $service->upload('gym-a', 'user-a', array_replace($payload, ['version' => 3]));
        throw new LogicException('Failure not injected');
    } catch (RuntimeException $e) {
        checkPhoto($e->getMessage() === 'Injected database failure', 'Expected storage rollback');
    }
    $db->failWrite = false;
    checkPhoto(count(glob($directory . '/storage/*')) === $before, 'Failed upload removes new files');
    checkPhoto($service->thumbnail('gym-a', 'user-a') === $jpg['thumbnail'], 'Previous photo preserved');
    file_put_contents($directory . '/blocked-storage', 'Not a directory');
    try {
        (new UserPhotoService($db, $directory . '/blocked-storage'))->upload('gym-a', 'user-a', array_replace($payload, ['version' => 3]));
        throw new LogicException('Storage failure not detected');
    } catch (RuntimeException $e) {
        checkPhoto($e->getMessage() === 'Almacenamiento no disponible', 'Expected disk failure');
    }
    checkPhoto($service->thumbnail('gym-a', 'user-a') === $jpg['thumbnail'], 'Disk failure preserves previous photo');
    $model = new class($db) extends Models\UsersDesktopModel {
        /** Inyecta BD temporal para verificar compatibilidad del push/pull de socios. */
        public function __construct($db) { $this->db = $db; }
    };
    checkPhoto($model->push('gym-a', [['Id' => 'user-a', 'Fullname' => 'Updated', 'photo' => null]])[0]['success'], 'Legacy push');
    checkPhoto($model->pull('gym-a')[0]['photo']['version'] === 2, 'Pull preserves server photo');
    checkPhoto(!array_key_exists('photo', $model->pullWithoutPhotos('gym-a')[0]), 'Admin pull skips photo metadata');
    checkPhoto(!$model->push('gym-b', [['Id' => 'user-a', 'Fullname' => 'Forbidden']])[0]['success'], 'Cross gym push rejected');
    checkPhoto($db->fetchOne("SELECT CustomerApiId FROM UsersDesktop WHERE Id='user-a'")['CustomerApiId'] === 'gym-a', 'Ownership preserved');
    checkPhoto($service->attach('gym-a', [['UserId' => 'user-a'], ['UserId' => 'user-b']], 'UserId')[1]['photo'] === null, 'Attendance metadata isolation');

    require_once __DIR__ . '/../app/Services/JwtService.php';
    $jwt = new App\Services\JwtService('temporary-photo-test-secret');
    $customerToken = $jwt->createToken(['typ' => 'customer', 'cid' => 'gym-a', 'mkt' => 'temporary-machine']);
    $otherToken = $jwt->createToken(['typ' => 'customer', 'cid' => 'gym-b', 'mkt' => 'temporary-machine']);
    $db->update('Customers', ['Token' => 'temporary-machine', 'TokenJwt' => $customerToken], 'Id = ?', ['gym-a']);
    $db->update('Customers', ['Token' => 'temporary-machine', 'TokenJwt' => $otherToken], 'Id = ?', ['gym-b']);
    $portalToken = $jwt->createToken(['customerId' => 'gym-a', 'name' => 'Temporary gym']);
    $otherPortal = $jwt->createToken(['customerId' => 'gym-b', 'name' => 'Other gym']);
    $multipart = "--photo-test\r\nContent-Disposition: form-data; name=\"version\"\r\n\r\n3\r\n"
        . "--photo-test\r\nContent-Disposition: form-data; name=\"sha256\"\r\n\r\n" . $payload['sha256'] . "\r\n"
        . "--photo-test\r\nContent-Disposition: form-data; name=\"image\"; filename=\"photo.png\"\r\nContent-Type: image/png\r\n\r\n" . $payload['bytes'] . "\r\n--photo-test--\r\n";
    $type = 'multipart/form-data; boundary=photo-test';
    checkPhoto(photoCgi($schema, $directory . '/storage', 'POST', '', $multipart, $type)['status'] === 401, 'Upload missing JWT');
    checkPhoto(photoCgi($schema, $directory . '/storage', 'GET', 'invalid')['status'] === 401, 'Read invalid JWT');
    checkPhoto(photoCgi($schema, $directory . '/storage', 'POST', $otherToken, $multipart, $type)['status'] === 404, 'Upload tenant isolation');
    $uploaded = photoCgi($schema, $directory . '/storage', 'POST', $customerToken, $multipart, $type);
    checkPhoto($uploaded['status'] === 200 && json_decode($uploaded['body'], true)['photo']['version'] === 3, 'Multipart controller upload');
    $duplicate = photoCgi($schema, $directory . '/storage', 'POST', $customerToken, $multipart, $type);
    checkPhoto($duplicate['status'] === 200 && $duplicate['body'] === $uploaded['body'], 'API idempotent confirmation');
    checkPhoto(photoCgi($schema, $directory . '/storage', 'GET', $otherPortal)['status'] === 404, 'Read tenant isolation');
    $download = photoCgi($schema, $directory . '/storage', 'GET', $portalToken);
    checkPhoto($download['status'] === 200 && $download['body'] === $payload['thumbnail'], 'Authenticated thumbnail');
    checkPhoto(str_contains($download['headers'], 'private, no-store'), 'Protected cache headers');
    require_once __DIR__ . '/memberships-photos.php';
    checkMembershipPhotos($db, $service, $schema, $directory . '/storage', $portalToken, $otherPortal);
    require_once __DIR__ . '/desktop-user-profile.php';
    checkUserProfile($db, $service, $schema, $directory . '/storage', $portalToken, $otherPortal);
    require_once __DIR__ . '/membership-sales.php';
    checkMembershipSales($db, $service, $schema, $directory . '/storage', $portalToken, $otherPortal);
    checkPhoto(photoCgi($schema, $directory . '/storage', 'POST', $customerToken, '{}', 'application/json')['status'] === 400, 'Multipart required');
    $oldMultipart = str_replace("\r\n\r\n3\r\n", "\r\n\r\n1\r\n", $multipart);
    checkPhoto(photoCgi($schema, $directory . '/storage', 'POST', $customerToken, $oldMultipart, $type)['status'] === 409, 'API old version');
    $invalidMultipart = str_replace($payload['bytes'], '<svg></svg>', $multipart);
    checkPhoto(photoCgi($schema, $directory . '/storage', 'POST', $customerToken, $invalidMultipart, $type)['status'] === 415, 'API invalid format');
    $oversizedMultipart = str_replace($payload['bytes'], str_repeat('x', UserPhotoService::MAX_BYTES + 1), $multipart);
    checkPhoto(photoCgi($schema, $directory . '/storage', 'POST', $customerToken, $oversizedMultipart, $type)['status'] === 413, 'API size limit');
    $badHashMultipart = str_replace($payload['sha256'], str_repeat('0', 64), $multipart);
    checkPhoto(photoCgi($schema, $directory . '/storage', 'POST', $customerToken, $badHashMultipart, $type)['status'] === 400, 'API hash validation');
    echo "CGI API: real multipart, JWT middleware, 401/404, upload, duplicate and protected download passed.\n";

    foreach (glob($directory . '/storage/*') as $path) { touch($path, time() - 172800); }
    checkPhoto($service->collectUnused()['candidates'] === 4, 'Only previous versions qualify for cleanup');
    checkPhoto($service->collectUnused(true)['deleted'] === 4, 'Obsolete files removed');
    checkPhoto($service->thumbnail('gym-a', 'user-a') === $payload['thumbnail'], 'Cleanup preserves current photo');
    echo "Recovery cleanup dry run and deletion preserve referenced files.\n";
    $db->loseCommitResponse = true;
    try {
        $service->upload('gym-a', 'user-a', array_replace($jpg, ['version' => 4]));
        throw new LogicException('Lost response not injected');
    } catch (RuntimeException $e) {
        checkPhoto($e->getMessage() === 'Lost commit response', 'Expected uncertain commit');
    }
    $db->loseCommitResponse = false;
    checkPhoto($service->upload('gym-a', 'user-a', array_replace($jpg, ['version' => 4]))['photo']['version'] === 4, 'Retry confirms uncertain commit');
    checkPhoto($service->thumbnail('gym-a', 'user-a') === $jpg['thumbnail'], 'Uncertain commit retains accepted files');

    $db->query("UPDATE UsersDesktop SET Removed=1 WHERE Id='user-a'");
    rejectsPhoto(fn() => $service->thumbnail('gym-a', 'user-a'), 404);
    rejectsPhoto(fn() => $service->upload('gym-a', 'user-a', $jpg), 404);
    echo "Temporary MySQL migration, isolation, idempotency, conflicts, disk/DB failures, uncertain commit, legacy push/pull and removal passed.\n";
} finally {
    if ($db) { $db->close(); }
    if ($admin) { $admin->query("DROP DATABASE IF EXISTS $schema"); $admin->close(); }
    // La limpieza solo toca archivos creados dentro del directorio efimero de esta prueba.
    foreach (glob($directory . '/storage/*') ?: [] as $path) { unlink($path); }
    if (is_dir($directory . '/storage')) { rmdir($directory . '/storage'); }
    foreach (glob($directory . '/*') ?: [] as $path) { unlink($path); }
    rmdir($directory);
}

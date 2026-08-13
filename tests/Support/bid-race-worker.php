<?php

declare(strict_types=1);

use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\User;
use App\Services\BidService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$payload = json_decode(base64_decode($_SERVER['argv'][1], true), true, flags: JSON_THROW_ON_ERROR);
$database = $payload['connection'];
$environment = [
    'APP_ENV'          => 'testing',
    'CACHE_STORE'      => 'array',
    'SESSION_DRIVER'   => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'DB_CONNECTION'    => $database['driver'],
    'DB_URL'           => $database['url'] ?? '',
    'DB_HOST'          => $database['host'] ?? '',
    'DB_PORT'          => (string) ($database['port'] ?? ''),
    'DB_DATABASE'      => $database['database'] ?? '',
    'DB_USERNAME'      => $database['username'] ?? '',
    'DB_PASSWORD'      => $database['password'] ?? '',
];

foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['phpvms.kvp_storage_path' => $payload['kvp']]);

touch($payload['ready']);
while (!file_exists($payload['go'])) {
    usleep(1000);
}

$attempt = $payload['attempt'];

try {
    app(BidService::class)->addBid(
        Flight::query()->findOrFail($attempt['flight']),
        User::query()->findOrFail($attempt['user']),
        $attempt['aircraft'] === null
            ? null
            : Aircraft::query()->findOrFail($attempt['aircraft']),
    );
    $result = ['status' => 'created', 'type' => null];
} catch (Throwable $throwable) {
    $result = ['status' => 'rejected', 'type' => $throwable::class];
}

file_put_contents($payload['result'], json_encode($result, JSON_THROW_ON_ERROR));

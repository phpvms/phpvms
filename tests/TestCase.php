<?php

namespace Tests;

use App\Contracts\Factory;
use App\Contracts\Unit;
use App\Database\seeds\ShieldSeeder;
use App\Exceptions\Handler;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\DatabaseService;
use App\Services\Installer\SeederService;
use App\Services\ModuleService;
use Carbon\Carbon;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Nwidart\Modules\Facades\Module;
use ReflectionClass;

/**
 * Test cases should extend this class.
 */
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use CreatesApplication;
    use TestData;

    /**
     * The base URL to use while testing the application.
     */
    public static string $prefix = '/api';

    protected $app;

    protected string $baseUrl = 'http://localhost';

    protected array $connectionsToTransact = ['test'];

    /** @var User */
    protected $user;

    protected static array $auth_headers = [
        'x-api-key' => 'testadminapikey',
    ];

    private Client $client;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Don't throttle requests when running the tests
        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        Artisan::call('database:create', ['--reset' => true]);
        Artisan::call('migrate', ['--env' => 'testing', '--force' => true]);
        $this->seed(ShieldSeeder::class);

        app(SeederService::class)->syncAllSeeds();

        /** @var ModuleService $moduleSvc */
        $moduleSvc = app(ModuleService::class);
        $modules = Module::all();

        // Call migrate on all modules
        /** @var \Nwidart\Modules\Module[] $modules */
        foreach ($modules as $module) {
            $moduleSvc->addModule($module->getName());
        }

        Notification::fake();
        // $this->disableExceptionHandling();

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            return 'App\\Database\\Factories\\'.class_basename($modelName).'Factory';
        });
    }

    /**
     * https://stackoverflow.com/a/41945739
     * https://gist.github.com/adamwathan/c9752f61102dc056d157.
     */
    protected function disableExceptionHandling(): void
    {
        $this->app->instance(ExceptionHandler::class, new class() extends Handler {
            /** @noinspection PhpMissingParentConstructorInspection */
            public function __construct()
            {
            }

            public function report(\Throwable $e)
            {
                // no-op
            }

            public function render($request, \Throwable $e)
            {
                throw $e;
            }
        });
    }

    protected function addMocks(?callable $mocks): void
    {
        $handler = HandlerStack::create($mocks);
        $client = new Client(['handler' => $handler]);
        $this->client->httpClient = $client;
    }

    public function headers(?User $user = null, array $headers = []): array
    {
        if ($user !== null) {
            $headers['x-api-key'] = $user->api_key;

            return $headers;
        }

        if ($this->user !== null) {
            $headers['x-api-key'] = $this->user->api_key;
        }

        return $headers;
    }

    /**
     * Import data from a YML file.
     */
    public function addData(string $file): void
    {
        $svc = app(DatabaseService::class);
        $file_path = base_path('tests/data/'.$file.'.yml');

        try {
            $svc->seed_from_yaml_file($file_path);
        } catch (Exception $e) {
        }
    }

    /**
     * Make sure an object has the list of keys.
     */
    public function assertHasKeys(array $obj, array $keys = []): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $obj);
        }
    }

    /**
     * Read a file from the data directory.
     *
     *
     * @return false|string
     */
    public function readDataFile(string $filename): bool|string
    {
        $paths = [
            'data/'.$filename,
            'tests/data/'.$filename,
        ];

        foreach ($paths as $p) {
            if (file_exists($p)) {
                return file_get_contents($p);
            }
        }

        return false;
    }

    /**
     * Return a mock Guzzle Client with a response loaded from $mockFile.
     */
    public function mockGuzzleClient(array|string $files): void
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        $responses = [];
        foreach ($files as $file) {
            $responses[] = new Response(200, [
                'Content-Type' => 'application/json; charset=utf-8',
            ], $this->readDataFile($file));
        }

        $mock = new MockHandler($responses);

        $handler = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handler]);
        app()->instance(Client::class, $guzzleClient);
    }

    /**
     * @param array|string $files The filename or files to respond with
     */
    public function mockXmlResponse(array|string $files): void
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        $responses = [];
        foreach ($files as $file) {
            $responses[] = new Response(200, [
                'Content-Type' => 'text/xml',
            ], $this->readDataFile($file));
        }

        $mock = new MockHandler($responses);

        $handler = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handler]);
        app()->instance(Client::class, $guzzleClient);
    }

    /**
     * @param array|string $files The filename or files to respond with
     */
    public function mockPlainTextResponse(array|string $files): void
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        $responses = [];
        foreach ($files as $file) {
            $responses[] = new Response(200, [
                'Content-Type' => 'text/plain',
            ], trim($this->readDataFile($file)));
        }

        $mock = new MockHandler($responses);

        $handler = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handler]);
        app()->instance(Client::class, $guzzleClient);
    }

    /**
     * Update a setting.
     */
    public function updateSetting(string $key, string $value): void
    {
        $settingsRepo = app(SettingRepository::class);
        $settingsRepo->store($key, $value);
    }

    /**
     * So we can test private/protected methods
     * http://bit.ly/1mr5hMq.
     *
     *
     * @throws \ReflectionException
     */
    public function invokeMethod(&$object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Transform any data that's passed in. E.g, make sure that any mutator
     * classes (e.g, units) are not passed in as the mutator class.
     */
    protected function transformData(array &$data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $this->transformData($value);
            }

            if (is_subclass_of($value, Unit::class)) {
                $data[$key] = $value->__toString();
            }

            if ($value instanceof DateTimeImmutable) {
                $data[$key] = $value->format(DATE_ATOM);
            } elseif ($value instanceof Carbon) {
                $data[$key] = $value->toIso8601ZuluString();
            } elseif ($value instanceof Unit) {
                $data[$key] = (float) $value->internal(2);
            }
        }

        return $data;
    }

    /**
     * Override the GET call to inject the user API key.
     *
     * @param string $uri
     * @param null   $user
     */
    public function get($uri, array $headers = [], $user = null): TestResponse
    {
        $req = parent::get($uri, $this->headers($user, $headers));
        if ($req->isClientError() || $req->isServerError()) {
            Log::error('GET Error: '.$uri, $req->json());
        }

        return $req;
    }

    /**
     * Override the POST calls to inject the user API key.
     *
     * @param string $uri
     * @param null   $user
     */
    public function post($uri, array $data = [], array $headers = [], $user = null): TestResponse
    {
        $data = $this->transformData($data);
        $req = parent::post($uri, $data, $this->headers($user, $headers));
        if ($req->isClientError() || $req->isServerError()) {
            Log::error('POST Error: '.$uri, $req->json());
        }

        return $req;
    }

    /**
     * Override the PUT calls to inject the user API key.
     *
     * @param string $uri
     * @param null   $user
     */
    public function put($uri, array $data = [], array $headers = [], $user = null): TestResponse
    {
        $req = parent::put($uri, $this->transformData($data), $this->headers($user, $headers));
        if ($req->isClientError() || $req->isServerError()) {
            Log::error('PUT Error: '.$uri, $req->json());
        }

        return $req;
    }

    /**
     * Override the DELETE calls to inject the user API key.
     *
     * @param string $uri
     * @param null   $user
     */
    public function delete($uri, array $data = [], array $headers = [], $user = null): TestResponse
    {
        $req = parent::delete($uri, $this->transformData($data), $this->headers($user, $headers));
        if ($req->isClientError() || $req->isServerError()) {
            Log::error('DELETE Error: '.$uri, $req->json());
        }

        return $req;
    }
}

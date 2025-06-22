<?php

namespace App\Services;

use App\Contracts\Service;
use App\Repositories\KvpRepository;
use App\Services\LegacyImporter\AircraftImporter;
use App\Services\LegacyImporter\AirlineImporter;
use App\Services\LegacyImporter\AirportImporter;
use App\Services\LegacyImporter\BaseImporter;
use App\Services\LegacyImporter\ClearDatabase;
use App\Services\LegacyImporter\ExpenseImporter;
use App\Services\LegacyImporter\FinalizeImporter;
use App\Services\LegacyImporter\FlightImporter;
use App\Services\LegacyImporter\GroupImporter;
use App\Services\LegacyImporter\PirepCommentImporter;
use App\Services\LegacyImporter\PirepImporter;
use App\Services\LegacyImporter\RankImport;
use App\Services\LegacyImporter\SettingsImporter;
use App\Services\LegacyImporter\UserImport;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LegacyImporterService extends Service
{
    private string $CREDENTIALS_KEY = 'legacy.importer.db';

    /**
     * @var KvpRepository
     */
    private readonly mixed $kvpRepo;

    /**
     * The list of importers, in proper order
     */
    private array $importList = [
        ClearDatabase::class,
        RankImport::class,
        GroupImporter::class,
        AirlineImporter::class,
        AircraftImporter::class,
        AirportImporter::class,
        FlightImporter::class,
        UserImport::class,
        PirepImporter::class,
        PirepCommentImporter::class,
        ExpenseImporter::class,
        SettingsImporter::class,
        FinalizeImporter::class,
    ];

    public function __construct()
    {
        $this->kvpRepo = app(KvpRepository::class);
    }

    /**
     * Save the credentials from a request
     */
    public function saveCredentialsFromRequest(Request $request): void
    {
        $creds = [
            'host'         => $request->post('db_host'),
            'port'         => $request->post('db_port'),
            'name'         => $request->post('db_name'),
            'user'         => $request->post('db_user'),
            'pass'         => $request->post('db_pass'),
            'table_prefix' => $request->post('db_prefix'),
        ];

        $this->saveCredentials($creds);
    }

    /**
     * Save the given credentials
     */
    public function saveCredentials(array $creds): void
    {
        $creds = array_merge([
            'admin_email'  => '',
            'host'         => '',
            'port'         => '',
            'name'         => '',
            'user'         => '',
            'pass'         => 3306,
            'table_prefix' => 'phpvms_',
        ], $creds);

        $this->kvpRepo->save($this->CREDENTIALS_KEY, $creds);
    }

    /**
     * Get the saved credentials
     */
    public function getCredentials()
    {
        return $this->kvpRepo->get($this->CREDENTIALS_KEY);
    }

    /**
     * Create a manifest of the import. Creates an array with the importer name,
     * which then has a subarray of all of the different steps/stages it needs to run
     *
     * @return mixed[]
     */
    public function generateImportManifest(): array
    {
        $manifest = [];

        foreach ($this->importList as $importerKlass) {
            /** @var BaseImporter $importer */
            $importer = new $importerKlass();
            $manifest = array_merge($manifest, $importer->getManifest());
        }

        return $manifest;
    }

    /**
     * Run a given stage
     *
     * @param int $start
     *
     * @throws \Exception
     */
    public function run(string $importer, $start = 0): void
    {
        throw_unless(in_array($importer, $this->importList, true), new Exception('Unknown importer "'.$importer.'"'));

        /** @var $importerInst BaseImporter */
        $importerInst = new $importer();

        try {
            $importerInst->run($start);
        } catch (Exception $e) {
            Log::error('Error running importer: '.$e->getMessage());
        }
    }
}

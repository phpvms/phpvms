<?php

namespace Tests;

use App\Exceptions\FileTypeNotAllowed;
use App\Exceptions\ModuleInstallationError;
use App\Models\File as FileModel;
use App\Services\FileService;
use App\Services\ModuleService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Admin uploads: module archives must not write outside their staging folder and
 * the files feature must not accept server-executable extensions
 */
final class UploadGuardsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->addData('base');
    }

    private function makeZip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpvms_zip_').'.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    public function test_module_archive_with_traversal_entries_is_refused(): void
    {
        $escaped = storage_path('app/tmp/modules/escaped.txt');
        File::delete($escaped);

        $zip = $this->makeZip([
            'module.json'    => json_encode(['name' => 'Evil']),
            '../escaped.txt' => 'outside',
        ]);

        try {
            app(ModuleService::class)->installModule(new UploadedFile($zip, 'evil.zip', 'application/zip', null, true));
            $this->fail('A zip with a parent-traversal entry must be refused');
        } catch (ModuleInstallationError $e) {
            $this->assertFileDoesNotExist($escaped);
            $this->assertDirectoryDoesNotExist(base_path('modules/Evil'));
        } finally {
            File::delete($zip);
        }
    }

    public function test_module_name_cannot_point_outside_the_modules_folder(): void
    {
        $zip = $this->makeZip([
            'module.json' => json_encode(['name' => '../../Escaped']),
        ]);

        try {
            app(ModuleService::class)->installModule(new UploadedFile($zip, 'evil.zip', 'application/zip', null, true));
            $this->assertDirectoryDoesNotExist(base_path('../Escaped'));
            $this->assertDirectoryDoesNotExist(base_path('modules/../../Escaped'));
            $this->assertEmpty(File::directories(storage_path('app/tmp/modules')));
        } finally {
            File::delete($zip);
        }
    }

    public function test_file_uploads_reject_executable_extensions(): void
    {
        Storage::fake(config('filesystems.public_files'));
        $svc = app(FileService::class);

        foreach (['shell.php', 'shell.phtml', 'shell.PHP', 'page.html', 'noext'] as $name) {
            try {
                $svc->saveFile(UploadedFile::fake()->create($name, 1), 'files', ['name' => $name]);
                $this->fail($name.' must be refused');
            } catch (FileTypeNotAllowed $e) {
                $this->assertEquals(0, FileModel::count());
            }
        }

        $saved = $svc->saveFile(UploadedFile::fake()->create('manual.PDF', 1), 'files', ['name' => 'manual']);
        $this->assertStringEndsWith('.pdf', $saved->path);
    }
}

<?php

namespace App\Console\Commands;

use App\Contracts\Command;
use App\Models\Enums\NavaidType;
use App\Models\Navdata;

class NavdataImport extends Command
{
    protected $signature = 'phpvms:navdata';

    protected $description = '';

    /**
     * @throws \League\Geotools\Exception\InvalidArgumentException
     */
    public function handle(): void
    {
        $this->info('Emptying the current navdata...');
        Navdata::query()->truncate();

        $this->info('Looking for nav files...');
        $this->read_wp_nav_aid();
        $this->read_wp_nav_fix();
    }

    /**
     * Read and parse in the navaid file
     *
     * @throws \League\Geotools\Exception\InvalidArgumentException
     */
    public function read_wp_nav_aid(): void
    {
        /*
         * ....,....1....,....2....,....3....,....4....,....5....,....6..
         * CORPUS CHRISTI          CRP  VORD 27.903764 -97.444881115.50H
         * CORPUS CHRISTI          ICRP ILSD 27.759597 -97.495508110.30T
         * ROCKPORT                RKP  NDB  28.090569 -97.045544391.00N
         * NNNNNNNNNNNNNNNNNNNNNNNNIIII TTTT dd.dddddd ddd.ddddddfff.ffC NNNN
         * COL 1-24 Facility Name IIII
         * COL 25-28 ID TTTT
         * COL 30-33 Type  ILS  Insturment Landing System (Localizer)
         *                 ILSD ILS/DME
         *                 NDB  Nondirectional Beacon
         *                 NDBM NDB/Locator Middle Marker (LMM)
         *                 NDBO NDB/Locator Outer Marker (LOM)
         *                 MARI Unknown - seems to be same as MHW class NDB
         *                 VOR  VHF Omnidirectional Radio
         *                 VORD VOR/DME (no separate code for VORTAC)
         * dd.dddddd  COL 34-43 Latitude (-Lat for South)
         * ddd.dddddd COL 44-54 Longitude ( -Lon for West)
         * fff.ff     COL 55-60 Frequency (MHz for ILS/VOR KHz for NDB) See Note Below C
         * Col 61 Class         H High Altitude/Long Range
         *                      N NDB
         *                      T Terminal/Short RangeNote:
         *                          If NDB frequency is above 999.99 KHz then the
         *                          frequecy field still starts in col 55 and C is col 62,
         *  for example:....,....1....,....2....,....3....,....4....,....5....,....6..
         * EREBUNI                 Y    NDBM 40.104053  44.4505831180.00N
         * Where the frequency above is 1180.00 KHz (1.180 MHz)
         */

        $file_path = storage_path('/navdata/WPNAVAID.txt');
        if (!file_exists($file_path)) {
            $this->error('WPNAVAID.txt not found in storage/navdata');

            return;
        }

        $this->info('Importing navaids (WPNAVAID.txt) ...');
        $generator = $this->readFile($file_path);

        $imported = 0;

        foreach ($generator as $line) {
            $navaid = [
                'id'    => trim(substr((string) $line, 24, 4)), // ident column
                'name'  => trim(substr((string) $line, 0, 24)),
                'type'  => trim(substr((string) $line, 29, 4)),
                'lat'   => trim(substr((string) $line, 33, 9)),
                'lon'   => trim(substr((string) $line, 43, 11)),
                'freq'  => trim(substr((string) $line, 54, 6)),
                'class' => trim((string) $line[60]),
            ];

            // Map to the Navaid enum
            $navaid['type'] = match ($navaid['type']) {
                'ILS'    => NavaidType::LOC,
                'ILSDME' => NavaidType::LOC_DME,
                'NDB', 'NDBM', 'NDBO', 'MARI' => NavaidType::NDB,
                'VOR'   => NavaidType::VOR,
                'VORD'  => NavaidType::VOR_DME,
                default => NavaidType::UNKNOWN,
            };

            /*if($navaid['id'] === 'LCH' || $navaid['id'] === 'RSG') {
                print_r($navaid);
            }*/

            Navdata::updateOrCreate([
                'id' => $navaid['id'], 'name' => $navaid['name'],
            ], $navaid);

            $imported++;
            if ($imported % 100 === 0) {
                $this->info('Imported '.$imported.' entries...');
            }
        }

        $this->info('Imported a total of '.$imported.' nav aids');
    }

    public function read_wp_nav_fix(): void
    {
        /*
         * ....,....1....,....2....,...3....,....4....,....
         * 5.8750W                   8750W-87.000000 -50.000000
         * GAREP                   GAREP 37.619689 128.073419
         * CIHAD                   CIHAD 37.619719 -86.013228
         * FOLAB                   FOLAB 37.619931 -87.359411
         * KEKAD                   KEKAD 37.620000  67.518333
         * NIKDE                   NIKDE 37.620567-122.563328
         * ZUMAS                   ZUMAS 37.620575-113.167747
         * NNNNN                   NNNNN dd.dddddd  dd.dddddd
         * Col 1-5 & 25-30 Fix Name
         * dd.dddddd  Col 32-40 Latitude degrees (-Lat for South, sign Col 31)
         * ddd.dddddd  Col 41-51 Longitude degrees (-Lon for West, decimal always Col 45)
         * Note: The duplicate name fields may be the result how the FAA
         * provides data, where there are many more fixes defined than provide
         * in the airac data. For example, most terminal data is not included.
         * This data includes airway crossing, radar service boundaries, etc.
         */

        $file_path = storage_path('/navdata/WPNAVFIX.txt');
        if (!file_exists($file_path)) {
            $this->error('WPNAVFIX.txt not found in storage/navdata');

            return;
        }

        $this->info('Importing navaids (WPNAVFIX.txt) ...');
        $generator = $this->readFile($file_path);

        $imported = 0;
        foreach ($generator as $line) {
            $navfix = [
                'id'   => trim(substr((string) $line, 0, 4)), // ident column
                'name' => trim(substr((string) $line, 24, 6)),
                'type' => NavaidType::FIX,
                'lat'  => trim(substr((string) $line, 30, 10)),
                'lon'  => trim(substr((string) $line, 40, 11)),
            ];

            Navdata::updateOrCreate([
                'id' => $navfix['id'], 'name' => $navfix['name'],
            ], $navfix);

            $imported++;
            if ($imported % 100 === 0) {
                $this->info('Imported '.$imported.' entries...');
            }
        }

        $this->info('Imported a total of '.$imported.' nav fixes');
    }
}

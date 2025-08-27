<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class GeojsonSplitter extends Component
{
    use WithFileUploads;

    public $geojsonFile;
    public $message = '';
    public $downloadLink = '';

    public function splitGeojson()
    {
        $this->message = '';
        $this->downloadLink = '';

        if (!$this->geojsonFile) {
            $this->message = 'No file selected.';
            return;
        }

        $path = $this->geojsonFile->getRealPath();
        $geojson = json_decode(file_get_contents($path), true);

        if (!isset($geojson['features'])) {
            $this->message = 'The file does not contain valid features.';
            return;
        }

        $originalName = pathinfo($this->geojsonFile->getClientOriginalName(), PATHINFO_FILENAME);

        // Group features by strata code
        $strata = [];
        foreach ($geojson['features'] as $feature) {
            $code = $feature['properties']['strata'] ?? 'unknown';
            $strata[$code][] = $feature;
        }

        // Prepare temp folder
        $tempFolder = storage_path('app/temp_geojson_' . Str::random(8));
        mkdir($tempFolder);

        $filePaths = [];

        // Generate individual GeoJSON files
        foreach ($strata as $code => $features) {
            $newGeojson = [
                'type' => 'FeatureCollection',
                'features' => $features
            ];

            $filename = "{$originalName}_{$code}.geojson";
            $filePath = $tempFolder . '/' . $filename;

            file_put_contents($filePath, json_encode($newGeojson, JSON_PRETTY_PRINT));
            $filePaths[] = $filePath;
        }

        // Create ZIP
        $zipFilename = "{$originalName}_" . Str::random(8) . ".zip";
        $zipPath = storage_path('app/public/geojson_zips/' . $zipFilename);

        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0777, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            foreach ($filePaths as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        }

        // Clean up temp folder
        foreach ($filePaths as $file) {
            unlink($file);
        }
        rmdir($tempFolder);

        $this->downloadLink = url('/storage/geojson_zips/' . $zipFilename);
        $this->message = 'All GeoJSON files have been successfully split and zipped!';
    }

    public function render()
    {
        return view('livewire.geojson-splitter');
    }
}

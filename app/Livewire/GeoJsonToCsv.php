<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeoJsonToCsv extends Component
{
    use WithFileUploads;

    public $geoJsonFile;
    public $message = '';
    public $downloadLink = '';

    protected $rules = [
        'geoJsonFile' => 'required|file|mimes:json,geojson,txt',
    ];

    public function updatedGeoJsonFile()
    {
        $this->validateOnly('geoJsonFile');
        $this->message = 'Selected file: ' . $this->geoJsonFile->getClientOriginalName();
    }

    public function convert()
    {
        $this->validate();

        $path = $this->geoJsonFile->getRealPath();
        $jsonContent = file_get_contents($path);
        $data = json_decode($jsonContent, true);

        if (!$data || !isset($data['features']) || !is_array($data['features'])) {
            $this->message = 'Invalid GeoJSON file.';
            return;
        }

        $allKeys = [];
        $rows = [];

        foreach ($data['features'] as $feature) {
            $row = [];

            // Properties
            if (isset($feature['properties']) && is_array($feature['properties'])) {
                foreach ($feature['properties'] as $key => $value) {
                    $row[$key] = $value;
                    if (!in_array($key, $allKeys)) $allKeys[] = $key;
                }
            }

            // Geometry coordinates
            if (isset($feature['geometry']['coordinates'])) {
                $row['coordinates'] = json_encode($feature['geometry']['coordinates']);
                if (!in_array('coordinates', $allKeys)) $allKeys[] = 'coordinates';
            }

            $rows[] = $row;
        }
        $csvHandle = fopen('php://temp', 'r+');
        fputcsv($csvHandle, $allKeys);

        foreach ($rows as $row) {
            $line = [];
            foreach ($allKeys as $key) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($csvHandle, $line);
        }

        rewind($csvHandle);
        $csvContent = stream_get_contents($csvHandle);
        fclose($csvHandle);

        $folder = 'geojson_csv';
        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder, 0755, true);
        }

        $filenameCsv = $folder . '/' . pathinfo($this->geoJsonFile->getClientOriginalName(), PATHINFO_FILENAME)
            . '_' . Str::random(6) . '.csv';

        Storage::disk('public')->put($filenameCsv, $csvContent);

        $this->downloadLink = Storage::url($filenameCsv);
        $this->message = 'Conversion completed!';
    }

    public function render()
    {
        return view('livewire.geo-json-to-csv');
    }
}

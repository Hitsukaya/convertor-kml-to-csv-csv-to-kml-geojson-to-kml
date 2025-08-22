<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CsvToGeojson extends Component
{
    use WithFileUploads;

    public $csvFile;
    public $message = '';
    public $downloadLink = '';

    protected $rules = [
        'csvFile' => 'required|file|mimes:csv,txt',
    ];

    public function updatedCsvFile()
    {
        $this->validateOnly('csvFile');

        if ($this->csvFile) {
            $this->message = 'Selected file: ' . $this->csvFile->getClientOriginalName();
            $this->downloadLink = '';
        }
    }

    public function convert()
    {
        $this->validate();

        if (!$this->csvFile) {
            $this->message = 'No CSV file selected.';
            $this->downloadLink = '';
            return;
        }

        $path = $this->csvFile->getRealPath();

        // Folosim fopen pentru fișiere mai mari
        if (!file_exists($path) || !is_readable($path)) {
            $this->message = 'Cannot read the CSV file.';
            $this->downloadLink = '';
            return;
        }

        $rows = array_map('str_getcsv', file($path));

        if (count($rows) < 2) {
            $this->message = 'CSV file is empty or invalid.';
            $this->downloadLink = '';
            return;
        }

        $header = array_map('trim', $rows[0]);
        $features = [];

        foreach (array_slice($rows, 1) as $row) {
            $row = array_map('trim', $row);

            if (count($row) !== count($header)) {
                continue; // skip rows cu număr incorect de coloane
            }

            $item = array_combine($header, $row);

            if (!isset($item['lon'], $item['lat'])) {
                continue;
            }

            if (!is_numeric($item['lon']) || !is_numeric($item['lat'])) {
                continue;
            }

            $properties = $item;
            unset($properties['lon'], $properties['lat']);

            $features[] = [
                'type' => 'Feature',
                'properties' => $properties,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float) $item['lon'],
                        (float) $item['lat'],
                    ],
                ],
            ];
        }

        if (empty($features)) {
            $this->message = 'No valid coordinates found in CSV.';
            $this->downloadLink = '';
            return;
        }

        $geojson = [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];

        $folder = 'csv_geojson';
        Storage::disk('public')->ensureDirectoryExists($folder);

        $filename = $folder . '/' . pathinfo($this->csvFile->getClientOriginalName(), PATHINFO_FILENAME)
            . '_' . Str::random(6) . '.geojson';

        Storage::disk('public')->put($filename, json_encode($geojson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->downloadLink = Storage::disk('public')->url($filename);
        $this->message = 'CSV converted to GeoJSON successfully!';
    }

    public function render()
    {
        return view('livewire.csv-to-geojson');
    }
}

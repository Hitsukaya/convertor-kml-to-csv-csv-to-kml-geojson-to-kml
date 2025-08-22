<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KmlToCsv extends Component
{
    use WithFileUploads;

    public $kmlFile;
    public $message = '';
    public $downloadLink = '';

    protected $rules = [
        'kmlFile' => 'required|file|mimes:kml,xml',
    ];

    public function updatedKmlFile()
    {
        $this->validateOnly('kmlFile');
        $this->message = 'Selected file: ' . $this->kmlFile->getClientOriginalName();
    }

    public function convert()
    {
        $this->validate();

        $path = $this->kmlFile->getRealPath();
        $xml = simplexml_load_file($path);

        if (!$xml) {
            $this->message = 'Error loading KML file.';
            return;
        }

        $namespaces = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('k', $namespaces[''] ?? 'http://www.opengis.net/kml/2.2');

        $placemarks = $xml->xpath('//k:Placemark') ?: [];

        if (count($placemarks) === 0) {
            $this->message = 'No placemarks found in KML file.';
            return;
        }

        $allKeys = [];
        $rows = [];

        foreach ($placemarks as $placemark) {
            $row = [];

            $row['name'] = (string) $placemark->name ?? '';
            $row['description'] = (string) ($placemark->description ?? '');

            // ExtendedData
            if (isset($placemark->ExtendedData->SchemaData)) {
                foreach ($placemark->ExtendedData->SchemaData as $schemaData) {
                    foreach ($schemaData->SimpleData as $simpleData) {
                        $key = (string) $simpleData->attributes()->name;
                        $row[$key] = (string) $simpleData;
                        if (!in_array($key, $allKeys)) $allKeys[] = $key;
                    }
                }
            }

            // Coordonate Polygon
            $row['coordinates'] = $this->extractCoordinates($placemark);
            if (!in_array('coordinates', $allKeys)) $allKeys[] = 'coordinates';

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

        $folder = 'kml_csv';

        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder, 0755, true);
        }

        $filenameCsv = $folder . '/kml_csv_' . Str::random(8) . '.csv';

        Storage::disk('public')->put($filenameCsv, $csvContent);

        $this->downloadLink = Storage::url($filenameCsv);
        $this->message = 'Conversion completed!';


    }

    protected function extractCoordinates($placemark)
    {
        $coords = '';

        if (isset($placemark->MultiGeometry)) {
            foreach ($placemark->MultiGeometry->Polygon as $polygon) {
                if (isset($polygon->outerBoundaryIs->LinearRing->coordinates)) {
                    $coords .= trim((string) $polygon->outerBoundaryIs->LinearRing->coordinates) . ' ';
                }
            }
        }

        return trim($coords);
    }

    public function render()
    {
        return view('livewire.kml-to-csv');
    }
}

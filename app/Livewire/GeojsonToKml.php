<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeojsonToKml extends Component
{
    use WithFileUploads;

    public $geojsonFile;
    public $message = '';
    public $downloadLink = '';

    protected $rules = [
        'geojsonFile' => 'required|file|mimes:json,geojson,txt',
    ];

    public function updatedGeojsonFile()
    {
        $this->validateOnly('geojsonFile');
        $this->message = 'Selected file: ' . $this->geojsonFile->getClientOriginalName();
    }

    public function convert()
    {
        $this->validate();

        $path = $this->geojsonFile->getRealPath();
        $jsonData = json_decode(file_get_contents($path), true);

        if (!$jsonData || !isset($jsonData['features'])) {
            $this->message = 'Invalid GeoJSON file.';
            return;
        }

        $allCoords = [];
        foreach ($jsonData['features'] as $feature) {
            $geom = $feature['geometry'];
            if ($geom['type'] === 'Polygon') {
                foreach ($geom['coordinates'][0] as $c) {
                    $allCoords[] = $c;
                }
            } elseif ($geom['type'] === 'Point') {
                $allCoords[] = $geom['coordinates'];
            } elseif ($geom['type'] === 'LineString') {
                foreach ($geom['coordinates'] as $c) {
                    $allCoords[] = $c;
                }
            }
        }

        // calcul centru
        $centerLon = $centerLat = 0;
        if (!empty($allCoords)) {
            $lonArray = array_map(fn($c) => floatval($c[0]), $allCoords);
            $latArray = array_map(fn($c) => floatval($c[1]), $allCoords);
            $centerLon = array_sum($lonArray) / count($lonArray);
            $centerLat = array_sum($latArray) / count($latArray);
        }

        $kml = new \SimpleXMLElement('<kml xmlns="http://www.opengis.net/kml/2.2"><Document></Document></kml>');

        // LookAt
        $lookAt = $kml->Document->addChild('LookAt');
        $lookAt->addChild('longitude', $centerLon);
        $lookAt->addChild('latitude', $centerLat);
        $lookAt->addChild('altitude', 0);
        $lookAt->addChild('range', 4000);
        $lookAt->addChild('tilt', 0);
        $lookAt->addChild('heading', 0);
        $lookAt->addChild('altitudeMode', 'relativeToGround');

        $folder = $kml->Document->addChild('Folder');
        $folder->addChild('name', $jsonData['name'] ?? 'GeoJSON Features');

        foreach ($jsonData['features'] as $feature) {
            $placemark = $folder->addChild('Placemark');
            $placemark->addChild('name', htmlspecialchars($feature['properties']['field_name'] ?? 'No Name'));

            $geom = $feature['geometry'];
            switch ($geom['type']) {
                case 'Point':
                    $point = $placemark->addChild('Point');
                    $coords = $geom['coordinates'];
                    $point->addChild('coordinates', "{$coords[0]},{$coords[1]},0");
                    break;

                case 'LineString':
                    $line = $placemark->addChild('LineString');
                    $coordsText = array_map(fn($c) => "{$c[0]},{$c[1]},0", $geom['coordinates']);
                    $line->addChild('coordinates', implode(' ', $coordsText));
                    break;

                case 'Polygon':
                    $polygon = $placemark->addChild('Polygon');
                    $outer = $polygon->addChild('outerBoundaryIs')->addChild('LinearRing');
                    $coordsText = array_map(fn($c) => "{$c[0]},{$c[1]},0", $geom['coordinates'][0]);
                    $outer->addChild('coordinates', implode(' ', $coordsText));
                    break;
            }
        }

        $folderPath = 'geo_kml';
        if (!Storage::disk('public')->exists($folderPath)) {
            Storage::disk('public')->makeDirectory($folderPath, 0755, true);
        }

        $filenameKml = $folderPath . '/geo_kml_' . Str::random(8) . '.kml';
        Storage::disk('public')->put($filenameKml, $kml->asXML());

        $this->downloadLink = url('storage/' . $filenameKml);
        $this->message = 'Conversion completed!';
    }

    public function render()
    {
        return view('livewire.geojson-to-kml');
    }
}

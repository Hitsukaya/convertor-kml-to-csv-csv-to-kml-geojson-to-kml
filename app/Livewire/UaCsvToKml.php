<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UaCsvToKml extends Component
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
        $this->message = 'Selected file: ' . $this->csvFile->getClientOriginalName();
        $this->downloadLink = '';
    }

    public function convert()
    {
        $this->validate();

        $path = $this->csvFile->getRealPath();
        $file = fopen($path, 'r');
        $header = fgetcsv($file);

        if (!$header) {
            $this->message = 'CSV file is empty or invalid.';
            return;
        }

        $rows = [];
        $allCoords = [];
        while (($data = fgetcsv($file)) !== false) {
            $row = array_combine($header, $data);
            $rows[] = $row;

            if (!empty($row['coordinates'])) {
                foreach (explode(' ', trim($row['coordinates'])) as $c) {
                    $parts = explode(',', $c);
                    if (count($parts) >= 2) $allCoords[] = [$parts[0], $parts[1]];
                }
            }
        }
        fclose($file);

        $centerLon = $centerLat = 0;
        if (!empty($allCoords)) {
            $lonSum = array_sum(array_column($allCoords, 0));
            $latSum = array_sum(array_column($allCoords, 1));
            $count = count($allCoords);
            $centerLon = $lonSum / $count;
            $centerLat = $latSum / $count;
        }

        $schemaName = $rows[0]['FARM_ID'] ?? 'CSV_Schema';
        $orderedKeys = $header;

        $kml = new \SimpleXMLElement('<kml xmlns="http://www.opengis.net/kml/2.2"
                                      xmlns:gx="http://www.google.com/kml/ext/2.2"
                                      xmlns:kml="http://www.opengis.net/kml/2.2"
                                      xmlns:atom="http://www.w3.org/2005/Atom">
                                      <Document id="root_doc"></Document></kml>');

        // UA-KML default styles
        $style1 = $kml->Document->addChild('gx:CascadingStyle', '', 'gx');
        $style1->addAttribute('kml:id', '__managed_style_24FBF9339235BEA6CF8F');
        $style2 = $kml->Document->addChild('gx:CascadingStyle', '', 'gx');
        $style2->addAttribute('kml:id', '__managed_style_15CB765DCF35BEA6CF8F');

        $styleMap = $kml->Document->addChild('StyleMap');
        $styleMap->addAttribute('id', '__managed_style_0FCA6A2F7F35BEA6CF8F');
        $pair1 = $styleMap->addChild('Pair');
        $pair1->addChild('key', 'normal');
        $pair1->addChild('styleUrl', '#__managed_style_15CB765DCF35BEA6CF8F');
        $pair2 = $styleMap->addChild('Pair');
        $pair2->addChild('key', 'highlight');
        $pair2->addChild('styleUrl', '#__managed_style_24FBF9339235BEA6CF8F');

        // LookAt element pentru vizualizare
        $lookAt = $kml->Document->addChild('LookAt');
        $lookAt->addChild('longitude', $centerLon);
        $lookAt->addChild('latitude', $centerLat);
        $lookAt->addChild('altitude', 0);
        $lookAt->addChild('range', 4000);
        $lookAt->addChild('tilt', 0);
        $lookAt->addChild('heading', 0);
        $lookAt->addChild('altitudeMode', 'relativeToGround');

        // Schema
        $schema = $kml->Document->addChild('Schema');
        $schema->addAttribute('name', $schemaName);
        $schema->addAttribute('id', $schemaName);
        foreach ($orderedKeys as $key) {
            if ($key !== 'coordinates') {
                $field = $schema->addChild('SimpleField');
                $field->addAttribute('name', $key);
                $field->addAttribute('type', 'string');
            }
        }

        $folder = $kml->Document->addChild('Folder');
        $folder->addChild('name', $schemaName);

        foreach ($rows as $row) {
            $placemark = $folder->addChild('Placemark');
            $placemark->addChild('name', $row['name'] ?? '');
            $placemark->addChild('styleUrl', '#__managed_style_0FCA6A2F7F35BEA6CF8F');

            $extData = $placemark->addChild('ExtendedData');
            $schemaData = $extData->addChild('SchemaData');
            $schemaData->addAttribute('schemaUrl', "#$schemaName");

            foreach ($orderedKeys as $key) {
                if ($key !== 'coordinates') {
                    $sd = $schemaData->addChild('SimpleData', htmlspecialchars($row[$key] ?? ''));
                    $sd->addAttribute('name', $key);
                }
            }

            if (!empty($row['coordinates'])) {
                $coordsArray = explode(' ', trim($row['coordinates']));
                $fixedCoords = [];
                foreach ($coordsArray as $c) {
                    $parts = explode(',', $c);
                    if (count($parts) >= 2) {
                        $fixedCoords[] = "{$parts[0]},{$parts[1]},0";
                    }
                }
                if (!empty($fixedCoords)) $fixedCoords[] = $fixedCoords[0];

                $multiGeometry = $placemark->addChild('MultiGeometry');
                $polygon = $multiGeometry->addChild('Polygon');
                $outer = $polygon->addChild('outerBoundaryIs');
                $linear = $outer->addChild('LinearRing');
                $linear->addChild('coordinates', implode(' ', $fixedCoords));
            }
        }

        $folderPath = 'ua_csv_kml';
        if (!Storage::disk('public')->exists($folderPath)) {
            Storage::disk('public')->makeDirectory($folderPath, 0755, true);
        }

        $filenameKml = $folderPath . '/ua_csv_kml_' . Str::random(8) . '.kml';
        Storage::disk('public')->put($filenameKml, $kml->asXML());

        $this->downloadLink = asset('storage/' . $filenameKml);
        $this->message = 'CSV converted to UA-KML successfully!';
    }

    public function render()
    {
        return view('livewire.ua-csv-to-kml');
    }
}

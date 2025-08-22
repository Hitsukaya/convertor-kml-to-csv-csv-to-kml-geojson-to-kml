<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CsvToKml extends Component
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

        $kml = new \SimpleXMLElement('<kml xmlns="http://www.opengis.net/kml/2.2"><Document id="root_doc"></Document></kml>');

        $lookAt = $kml->Document->addChild('LookAt');
        $lookAt->addChild('longitude', $centerLon);
        $lookAt->addChild('latitude', $centerLat);
        $lookAt->addChild('altitude', 0);
        $lookAt->addChild('range', 4000); 
        $lookAt->addChild('tilt', 0);
        $lookAt->addChild('heading', 0);
        $lookAt->addChild('altitudeMode', 'relativeToGround');

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

            $style = $placemark->addChild('Style');
            $linestyle = $style->addChild('LineStyle');
            $linestyle->addChild('color', 'ff0000ff');
            $polystyle = $style->addChild('PolyStyle');
            $polystyle->addChild('fill', '0');

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

        $folder = 'csv_kml';

        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder, 0755, true);
        }

        $filenameKml = $folder . '/csv_kml_' . Str::random(8) . '.kml';

        Storage::disk('public')->put($filenameKml, $kml->asXML());

        $this->downloadLink = url('storage/' . $filenameKml);
        $this->message = 'Conversion completed!';

    }

    public function render()
    {
        return view('livewire.csv-to-kml');
    }
}

<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class UaKmlToCsv extends Component
{
    use WithFileUploads;

    public $kmlFile;
    public $message = '';
    public $downloadLink = '';

    protected $rules = [
        'kmlFile' => 'required|file|mimes:kml,xml,txt',
    ];

    public function updatedKmlFile()
    {
        $this->validateOnly('kmlFile');
        $this->message = 'Selected file: ' . $this->kmlFile->getClientOriginalName();
        $this->downloadLink = '';
    }

    public function convert()
    {
        $this->validate();

        $path = $this->kmlFile->getRealPath();
        $xml = simplexml_load_file($path);
        $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');

        $rows = [];
        $rows[] = ['name', 'type', 'coordinates']; // header CSV

        foreach ($xml->xpath('//kml:Placemark') as $placemark) {
            $name = (string) $placemark->name;

            if (isset($placemark->Point)) {
                $coords = trim((string) $placemark->Point->coordinates);
                $type = 'Point';
            } elseif (isset($placemark->Polygon)) {
                $coords = trim((string) $placemark->Polygon->outerBoundaryIs->LinearRing->coordinates);
                $type = 'Polygon';
                $coords = preg_replace('/\s+/', ' ', $coords);
            } else {
                continue;
            }

            $rows[] = [$name, $type, $coords];
        }

        if (count($rows) <= 1) {
            $this->message = 'No placemarks found in UA-KML file.';
            return;
        }

        $folder = 'ua_kml_csv';
        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        $filename = $folder . '/' . pathinfo($this->kmlFile->getClientOriginalName(), PATHINFO_FILENAME)
            . '_' . uniqid() . '.csv';

        $fp = fopen(storage_path('app/public/' . $filename), 'w');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        $this->downloadLink = asset('storage/' . $filename);
        $this->message = 'UA-KML converted to CSV successfully!';
    }

    public function render()
    {
        return view('livewire.ua-kml-to-csv');
    }
}

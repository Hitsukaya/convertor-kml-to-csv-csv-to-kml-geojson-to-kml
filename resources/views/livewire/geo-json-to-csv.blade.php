<div class="max-w-xl mx-auto p-4">
    <h2 class="text-xl font-bold mb-4 text-center">Convert GeoJSON to CSV</h2>

    @if($message)
        <div class="text-green-600 mb-2">{!! $message !!}</div>
    @endif

    <label for="geoJsonFile" class="block mb-1 font-semibold">Upload GeoJSON File</label>
    <input type="file" id="geoJsonFile" wire:model="geoJsonFile" class="border p-2 w-full rounded-2xl mb-2" accept=".geojson,.json,.txt">

    @if ($geoJsonFile)
        <div class="text-blue-600 mt-2">
            File is ready to convert: {{ $geoJsonFile->getClientOriginalName() }}
        </div>

        <button wire:click="convert" class="px-4 py-2 bg-blue-600 text-white rounded mt-2 hover:bg-blue-700 transition">
            Convert GeoJSON to CSV
        </button>
    @endif

    @if ($downloadLink)
        <div class="mt-4">
            <a href="{{ $downloadLink }}" target="_blank" class="text-blue-600 underline hover:text-blue-800">
                Download CSV
            </a>
        </div>
    @endif
</div>

<div class="max-w-xl mx-auto p-4">
    <h2 class="text-xl font-bold mb-4 text-center">Convert GeoJSON TO KML</h2>

    @if($message)
        <div class="text-green-600 mb-2">{!! $message !!}</div>
    @endif

    <!-- Label pentru GeoJSON -->
    <label for="geojsonFile" class="block mb-1 font-semibold">Upload GeoJSON File</label>
    <input type="file" id="geojsonFile" wire:model="geojsonFile" class="border p-2 w-full rounded-2xl mb-2" accept=".json,.geojson,.txt">

    @if ($geojsonFile)
        <div class="text-blue-600 mt-2">
            File is ready to convert: {{ $geojsonFile->getClientOriginalName() }}
        </div>

        <button wire:click="convert" class="px-4 py-2 bg-blue-600 text-white rounded mt-2">
            Convert GeoJSON to KML
        </button>
    @endif

    @if ($downloadLink)
        <div class="mt-4">
            <a href="{{ $downloadLink }}" target="_blank" class="text-blue-600 underline">
                Download KML
            </a>
        </div>
    @endif
</div>

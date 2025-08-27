<div class="max-w-xl mx-auto p-4">
    <h2 class="text-xl font-bold mb-4 text-center">GeoJSON Splitter & ZIP</h2>

    @if($message)
        <div class="text-green-600 mb-2">{!! $message !!}</div>
    @endif

    <label for="geojsonFile" class="block mb-1 font-semibold">Upload GeoJSON File</label>
    <input type="file" id="geojsonFile" wire:model="geojsonFile" class="border p-2 w-full rounded-2xl mb-2" accept=".geojson,.json">

    @if ($geojsonFile)
        <div class="text-blue-600 mt-2">
            File ready to split: {{ $geojsonFile->getClientOriginalName() }}
        </div>

        <button wire:click="splitGeojson" class="px-4 py-2 bg-blue-600 text-white rounded mt-2">
            Split & Download ZIP
        </button>
    @endif

    @if($downloadLink)
        <div class="mt-4">
            <a href="{{ $downloadLink }}" target="_blank" class="text-blue-600 underline">
                Download ZIP with all GeoJSON files
            </a>
        </div>
    @endif
</div>

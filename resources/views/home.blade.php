<x-layouts.app>
    <div class="container mx-auto p-6">
        @livewire('kml-uploader')
    </div>

    <div class="container mx-auto p-6">
        @livewire('kml-to-csv')
    </div>

    <div class="container mx-auto p-6">
        @livewire('csv-to-kml')
    </div>
	
	<div class="container mx-auto p-6">
        @livewire('geo-json-to-csv')
    </div>
	
	<div class="container mx-auto p-6">
        @livewire('geojson-to-kml')
    </div>
	

	
	<div class="container mx-auto p-6">
        @livewire('ua-kml-to-csv')
    </div>
</x-layouts.app>

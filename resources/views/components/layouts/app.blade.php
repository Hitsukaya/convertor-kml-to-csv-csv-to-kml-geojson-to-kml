<x-layouts.main>

    <x-ui.app.header />
            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="px-4 py-2 mx-auto max-w-7xl sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

    <x-ui.app.footer />

</x-layouts.main>

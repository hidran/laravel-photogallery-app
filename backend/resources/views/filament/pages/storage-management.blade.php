<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        @foreach ($this->getFolderBreakdown() as $folder => $stats)
            <x-filament::section>
                <x-slot name="heading">{{ ucfirst($folder) }}</x-slot>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Size</span>
                        <span class="text-sm font-medium">{{ $stats['size'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Files</span>
                        <span class="text-sm font-medium">{{ number_format($stats['count']) }}</span>
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>

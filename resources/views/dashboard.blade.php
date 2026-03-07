<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <x-slot:header>
            {{ __('Dashboard') }}
        </x-slot:header>
        <heading class="text-2xl font-semibold tracking-tight">
            {{ __('Istic Hosting Dashboard') }}
        </heading>
        <img class="mt-6 rounded-lg object-cover" src="https://art.istic.net/server-images/firth.jpg" alt="Great Cumbrae Island, United Kingdom" aria-describedby="photo-credit" />
        <div aria-label="Photo credit" id="photo-credit" class="text-sm text-gray-500">Great Cumbrae Island, United Kingdom, Photo by <a href="https://unsplash.com/@sldoug?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText">Steve Douglas</a> on <a href="https://unsplash.com/photos/white-sail-boat-on-sea-during-daytime-eZc8zZRK4zw?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText">Unsplash</a></div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            @if (Auth::user()->hasPermission('sftp_access'))
            <a class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700" href="{{ route('sftp.password') }}" wire:navigate>
                <flux:icon name="server" class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                <p class="absolute inset-0 flex items-center justify-center text-lg font-medium">SFTP</p>
            </a>
            @endif
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
        </div>
    </div>
</x-layouts::app>

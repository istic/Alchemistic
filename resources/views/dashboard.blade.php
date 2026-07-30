<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <x-slot:header>
            {{ __('Dashboard') }}
        </x-slot:header>
        <heading class="text-2xl font-semibold tracking-tight">
            {{ __('Istic Hosting Dashboard') }}
        </heading>

        <div class="hero rounded-xl bg-gradient-to-b from-accent-content/50 to-accent-content/0 p-6 text-center bg-[url('https://art.istic.net/server-images/firth.jpg')] bg-cover bg-center">
            <p class="text-lg font-medium tracking-tight">
                {{ __('Welcome to the Istic Hosting Dashboard!') }}
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ __('Here you can manage your hosting services, view analytics, and access other features.') }}
            </p>
        </div>
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

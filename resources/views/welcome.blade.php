<x-layouts::app :title="__('Dashboard')">

    <x-slot:header>
        {{ __('Dashboard') }}
    </x-slot:header>
    <heading class="text-2xl font-semibold tracking-tight">
        {{ __('Istic Hosting Dashboard') }}
    </heading>
    <!-- Hero image, roughly 16:9 aspect ratio -->
    <img class="mt-6 rounded-lg object-cover" src="https://art.istic.net/server-images/firth.jpg" alt="Great Cumbrae Island, United Kingdom" aria-describedby="photo-credit" />

    <div aria-label="Photo credit" id="photo-credit" class="text-sm text-gray-500">Great Cumbrae Island, United Kingdom, Photo by <a href="https://unsplash.com/@sldoug?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText">Steve Douglas</a> on <a href="https://unsplash.com/photos/white-sail-boat-on-sea-during-daytime-eZc8zZRK4zw?utm_source=unsplash&utm_medium=referral&utm_content=creditCopyText">Unsplash</a></div>


    <p class="mt-4">Welcome to the Istic Hosting Dashboard. From here you can manage your hosting services, view analytics, and access support resources. Use the navigation menu to explore the various features and tools available to you.</p>

    <p class="mt-4">This server is Firth, hosted in Germany.</p>

    <p class="mt-4">You can't do much without <a href="{{ route('login') }}" class="text-blue-500 underline">logging in</a>, but you can't do that without an account. If you think you should have an account, try contacting <a href="mailto:support@istic.net" class="text-blue-500 underline">support@istic.net</a>.</p>
</x-layouts::app>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Your account's profile information and email address.") }}
        </p>
    </header>

    <div class="mt-6 space-y-6">
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input
                id="name"
                type="text"
                class="block w-full mt-1 bg-gray-100 cursor-not-allowed opacity-60 dark:bg-gray-700"
                :value="$user->name"
                disabled
                readonly
            />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input
                id="email"
                type="email"
                class="block w-full mt-1 bg-gray-100 cursor-not-allowed opacity-60 dark:bg-gray-700"
                :value="$user->email"
                disabled
                readonly
            />
        </div>
    </div>
</section>

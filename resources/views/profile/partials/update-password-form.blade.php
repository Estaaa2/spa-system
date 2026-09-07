<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('profile.password') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="block w-full mt-1" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <div class="relative">
                <x-text-input id="update_password_password" name="password" type="password" class="block w-full pr-10 mt-1" autocomplete="new-password" />
                <button type="button"
                        class="toggle-password absolute inset-y-0 right-0 flex items-center pr-3 mt-1 text-[#8B7355] hover:text-[#8B7355] transition-colors duration-200"
                        data-target="update_password_password"
                        aria-label="Show password"
                        tabindex="-1">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <div class="relative">
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="block w-full pr-10 mt-1" autocomplete="new-password" />
                <button type="button"
                        class="toggle-password absolute inset-y-0 right-0 flex items-center pr-3 mt-1 text-[#8B7355] hover:text-[#8B7355] transition-colors duration-200"
                        data-target="update_password_password_confirmation"
                        aria-label="Show password"
                        tabindex="-1">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>
    </form>

    <script>
        document.querySelectorAll('.toggle-password:not([data-bound])').forEach(function (btn) {
            btn.setAttribute('data-bound', 'true');
            btn.addEventListener('click', function () {
                const target = document.getElementById(btn.dataset.target);
                const icon = btn.querySelector('i');
                if (!target) return;

                if (target.type === 'password') {
                    target.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    btn.setAttribute('aria-label', 'Hide password');
                } else {
                    target.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    btn.setAttribute('aria-label', 'Show password');
                }
            });
        });
    </script>
</section>

{{-- Change password. Same route and fields as before; restyled to the staff canon. Variables: $mustChangePassword. --}}
<div id="password"
     class="overflow-hidden bg-white border border-gray-200 shadow-sm scroll-mt-6 rounded-2xl dark:bg-gray-800 dark:border-gray-700
            {{ $mustChangePassword ? 'ring-2 ring-amber-400 dark:ring-amber-600' : '' }}">
    <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Change Password</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Use a long password that you don't use anywhere else.</p>
    </div>

    <form method="POST" action="{{ route('profile.password') }}" class="px-4 py-6 space-y-4 sm:px-6" data-submit-once>
        @csrf
        @method('PUT')

        @foreach ([
            ['current_password', 'update_password_current_password', 'Current password', 'current-password'],
            ['password', 'update_password_password', 'New password', 'new-password'],
            ['password_confirmation', 'update_password_password_confirmation', 'Confirm new password', 'new-password'],
        ] as [$name, $id, $label, $autocomplete])
            <div>
                <label for="{{ $id }}" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
                <div class="relative sm:max-w-md">
                    <input id="{{ $id }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}" required
                           @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}_error" @endif
                           class="w-full min-h-[44px] pl-3 pr-12 py-2 text-sm border rounded-xl dark:bg-gray-700 dark:text-white {{ $errors->has($name) ? 'border-red-500 dark:border-red-500' : 'border-gray-300 dark:border-gray-600' }}">
                    <button type="button"
                            class="toggle-password absolute inset-y-0 right-0 inline-flex items-center justify-center min-w-[44px] text-gray-500 rounded-r-xl hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            data-target="{{ $id }}"
                            aria-label="Show password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                @if ($errors->has($name))
                    <p id="{{ $id }}_error" class="mt-1 text-xs text-red-600 dark:text-red-400" role="alert">{{ $errors->first($name) }}</p>
                @endif
            </div>
        @endforeach

        <div class="flex justify-end pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                <i class="fa-solid fa-key" aria-hidden="true"></i>
                <span data-busy-label="Saving…">Save new password</span>
            </button>
        </div>
    </form>
</div>

<script>
    document.querySelectorAll('.toggle-password:not([data-bound])').forEach(function (btn) {
        btn.setAttribute('data-bound', 'true');
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.dataset.target);
            const icon = btn.querySelector('i');
            if (!target) return;

            const show = target.type === 'password';
            target.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
</script>

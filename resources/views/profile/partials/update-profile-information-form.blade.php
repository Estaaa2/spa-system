{{-- Account information. Name and email are read-only; staff edit their phone number only (decision Q25). --}}
@php
    $managedBy = $user->staff
        ? 'your manager or spa owner'
        : 'the Levictas team';
@endphp

<div id="account" class="overflow-hidden bg-white border border-gray-200 shadow-sm scroll-mt-6 rounded-2xl dark:bg-gray-800 dark:border-gray-700">
    <div class="px-4 py-4 border-b border-gray-200 sm:px-6 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Account Information</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Your name and email are managed for you. To change them, ask {{ $managedBy }}.</p>
    </div>

    <div class="px-4 py-6 space-y-6 sm:px-6">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Full name</dt>
                <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</dd>
            </div>
            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40">
                <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Email</dt>
                <dd class="mt-1 text-sm font-medium text-gray-900 break-all dark:text-white">{{ $user->email }}</dd>
            </div>
            @if ($user->branch)
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-900/40 sm:col-span-2">
                    <dt class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Branch</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $user->branch->name }}</dd>
                </div>
            @endif
        </dl>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4" data-submit-once>
            @csrf
            @method('PATCH')

            <div>
                <label for="phone" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Mobile number <span class="font-normal text-gray-400">(optional)</span>
                </label>
                <input type="tel" id="phone" name="phone" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}"
                       autocomplete="tel" placeholder="09xxxxxxxxx"
                       value="{{ old('phone', $user->phone) }}"
                       aria-describedby="phoneHelp{{ $errors->account->has('phone') ? ' phoneError' : '' }}"
                       @if ($errors->account->has('phone')) aria-invalid="true" @endif
                       class="w-full sm:max-w-xs min-h-[44px] px-3 py-2 text-sm border rounded-xl dark:bg-gray-700 dark:text-white {{ $errors->account->has('phone') ? 'border-red-500 dark:border-red-500' : 'border-gray-300 dark:border-gray-600' }}">
                <p id="phoneHelp" class="mt-1 text-xs text-gray-500 dark:text-gray-400">11 digits, starting with 09. Your spa uses it to reach you about your schedule.</p>
                @if ($errors->account->has('phone'))
                    <p id="phoneError" class="mt-1 text-xs text-red-600 dark:text-red-400" role="alert">{{ $errors->account->first('phone') }}</p>
                @endif
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <span data-busy-label="Saving…">Save phone number</span>
                </button>
            </div>
        </form>
    </div>
</div>

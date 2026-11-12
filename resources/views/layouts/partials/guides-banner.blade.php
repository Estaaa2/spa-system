{{--
    "New here?" banner (help guides Phase 1, D4 / Q7–Q10 → A).
    Business users see it at the top of every page until they dismiss it or
    open Help & Guides (Q8 → A); the dismissal is stored on users.guides_dismissed_at.
    Hidden for owners mid-setup (GuideCatalog::showsBanner).

    × posts in the background and hides the bar; without JavaScript the form
    submits normally and the page reloads without it.
--}}
@if (\App\Support\Guides\GuideCatalog::showsBanner(auth()->user()))
    <div x-data="{ shown: true }" x-show="shown" class="px-4 pt-4 mx-auto sm:px-6 sm:pt-6 max-w-7xl print:hidden">
        <div role="region" aria-label="Help for new users"
             class="flex items-start gap-3 p-3 border border-blue-200 sm:items-center sm:p-4 rounded-2xl bg-blue-50 dark:border-blue-800 dark:bg-blue-900/10">
            <i class="mt-0.5 text-lg text-blue-700 sm:mt-0 fa-solid fa-circle-question shrink-0 dark:text-blue-300" aria-hidden="true"></i>

            {{-- Text and button stack on phones, sit in one row from sm up; × stays top-right. --}}
            <div class="flex flex-col flex-1 min-w-0 gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                <p class="text-sm text-blue-900 dark:text-blue-100">
                    New here? Learn how each page works in Help &amp; Guides.
                </p>

                <a href="{{ route('help.index') }}"
                   class="self-start sm:self-auto inline-flex items-center justify-center min-h-[44px] px-4 py-2 text-sm font-medium text-white transition-colors rounded-xl shrink-0 bg-[#7A6348] hover:bg-[#6F5430] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    Open guides
                </a>
            </div>

            <form method="POST" action="{{ route('help.banner.dismiss') }}" class="-my-1.5 -mr-1.5 shrink-0"
                  x-on:submit.prevent="
                      fetch($el.action, {
                          method: 'POST',
                          headers: { 'X-CSRF-TOKEN': $el.querySelector('[name=_token]').value, 'Accept': 'application/json' },
                      }).then(r => { if (r.ok) { shown = false } else { $el.submit() } })
                        .catch(() => $el.submit())
                  ">
                @csrf
                <button type="submit" aria-label="Dismiss this message"
                        class="inline-flex items-center justify-center text-blue-800 min-h-[44px] min-w-[44px] rounded-xl hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>
@endif

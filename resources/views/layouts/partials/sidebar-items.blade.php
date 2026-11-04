{{--
    Sidebar links, shared by the business sidebar (navigation.blade.php) and the
    admin sidebar (navigation-admin.blade.php) — sidebar & RBAC rework Phase 3.

    $nav           ['top' => links, 'zones' => [['key','label','items'], …]] from
                   App\Services\Navigation\Sidebar or AdminSidebar
    $labelledZone  the zone whose label the caller already printed above this
                   partial; every other zone gets a divider and its own label.

    Expects the parent Alpine component to provide isOpen() / toggleSection().
--}}
<div class="space-y-1">
    {{-- Top links: Dashboard, My Payslips and pinned links (business), Dashboard (admin). --}}
    @foreach ($nav['top'] as $link)
        <x-nav-top-link :link="$link" />
    @endforeach

    @foreach ($nav['zones'] as $zone)
        @if ($zone['key'] !== $labelledZone)
            </div>

            {{-- Hard break: on the business side nothing below here reacts to the branch switcher. --}}
            <hr class="my-4 border-gray-200 dark:border-gray-700">

            <p class="px-4 pb-1 text-[11px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">
                {{ $zone['label'] }}
            </p>

            <div class="space-y-1">
        @endif

        @foreach ($zone['items'] as $item)
            @if ($item['type'] === 'link')
                <x-nav-top-link :link="$item" />
            @else
                @php $sectionId = 'nav-section-' . $item['key']; @endphp
                <div class="mb-1">
                    <button @click="toggleSection(@js($item['key']))" type="button"
                        aria-controls="{{ $sectionId }}"
                        :aria-expanded="isOpen(@js($item['key'])) ? 'true' : 'false'"
                        class="flex items-center justify-between w-full min-h-[44px] gap-2 px-4 py-3 font-medium text-left text-gray-700 transition-colors rounded-lg hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <span class="flex items-center min-w-0 gap-2">
                            <i class="fa-solid {{ $item['icon'] }} w-4 shrink-0 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                            <span class="min-w-0">{{ $item['label'] }}</span>
                        </span>
                        <span class="flex items-center gap-2 shrink-0">
                            @if (($item['badgeTotal'] ?? 0) > 0)
                                {{-- Needs action inside this collapsed group (D5): amber, like the count pill. --}}
                                <span x-show="!isOpen(@js($item['key']))" x-cloak
                                    class="w-2 h-2 rounded-full bg-amber-500 dark:bg-amber-400"><span class="sr-only">, has items needing action</span></span>
                            @endif
                            @if ($item['active'])
                                {{-- "You are here": the current page is inside this collapsed group. Brand colour. --}}
                                <span x-show="!isOpen(@js($item['key']))" x-cloak aria-hidden="true"
                                    class="w-1.5 h-1.5 rounded-full bg-[#8B7355] dark:bg-[#C4A97D]"></span>
                            @endif
                            <i class="text-xs transition-transform duration-200 fa-solid fa-chevron-down motion-reduce:transition-none" aria-hidden="true"
                                :class="isOpen(@js($item['key'])) ? 'transform rotate-180' : ''"></i>
                        </span>
                    </button>

                    <div x-show="isOpen(@js($item['key']))" x-collapse id="{{ $sectionId }}" class="ml-4 space-y-1">
                        @foreach ($item['links'] as $link)
                            <x-nav-link :href="$link['url']" :active="$link['active']"
                                :chip="$link['chip']" :badge="$link['badge']" :badge-label="$link['badgeLabel']">
                                {{ $link['label'] }}
                            </x-nav-link>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    @endforeach
</div>

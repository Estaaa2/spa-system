{{-- Table of contents for legal/show. $tocGroups: sections (with 'number') grouped by 'group'. --}}
<div class="mt-3 space-y-4">
    @foreach ($tocGroups as $group => $sections)
        <div>
            @if ($group !== '')
                <p class="mb-1.5 text-[11px] font-semibold tracking-wider uppercase text-[#8B7355] dark:text-[#C4A97D]">{{ $group }}</p>
            @endif
            <ol class="space-y-1 text-sm">
                @foreach ($sections as $section)
                    <li>
                        <a href="#{{ $section['id'] }}"
                            class="flex gap-2 px-2 py-1 -mx-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-[#6F5430] dark:hover:text-[#C4A97D] hover:bg-[#F6EFE6] dark:hover:bg-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">
                            <span class="tabular-nums text-[#8B7355] dark:text-[#C4A97D]">{{ $section['number'] }}.</span>
                            <span>{{ $section['title'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    @endforeach
</div>

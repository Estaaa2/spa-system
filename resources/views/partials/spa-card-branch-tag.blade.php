{{--
    Branch tag on a spa card's photo (card Option A, Nov 15, 2026): "Imus Branch"
    at the bottom-left, so same-name cards of one spa can be told apart.
    $tag comes from PublicBranches::cardTag(): null for spas with one public
    branch, so nothing shows for them. The JS-built cards (search results,
    "Spas near you") use spaBranchTag() in welcome.js, which matches this markup.
--}}
@if (!empty($tag))
    <div
        class="absolute bottom-3 left-3 flex items-center gap-1.5 max-w-[calc(100%-1.5rem)] px-3 py-1.5 rounded-full bg-white/95 text-[#3C2F23] text-[13px] font-bold leading-none shadow-md ring-1 ring-black/5">
        <i class="fa-solid fa-location-dot text-[11px] text-[#8B7355]" aria-hidden="true"></i>
        <span class="truncate">{{ $tag }}</span>
    </div>
@endif

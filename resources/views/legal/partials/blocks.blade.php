{{--
    Renders a legal section's blocks (App\Support\Legal\LegalDocument):
    a string is a paragraph, ['list' => [...]] a bullet list. The text is
    trusted HTML from the repository with contact values already escaped,
    so it is echoed raw. Used by legal/show and the terms modals.
--}}
@foreach ($blocks as $block)
    @if (is_array($block))
        <ul class="pl-5 space-y-1.5 list-disc marker:text-[#8B7355] dark:marker:text-[#C4A97D]">
            @foreach ($block['list'] as $item)
                <li>{!! $item !!}</li>
            @endforeach
        </ul>
    @else
        <p>{!! $block !!}</p>
    @endif
@endforeach

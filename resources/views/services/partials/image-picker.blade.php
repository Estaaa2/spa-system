{{-- Two image tiles in one row. Included once per form with a unique $prefix.
     Uses $label, $hint, $btn and $maxImageMb from services/index.blade.php.
     Behaviour lives in setupImagePicker() in that file's script. --}}
<div data-image-picker="{{ $prefix }}">
    <p class="{{ $label }}">Images</p>

    <div class="flex flex-wrap items-center gap-3">
        @foreach([1, 2] as $slot)
            <button type="button" class="svc-img-tile" data-slot="{{ $slot }}"
                    aria-pressed="false" aria-label="Add image {{ $slot }}">
                <img src="" alt="" hidden>
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span class="svc-img-cover-badge" hidden>Cover</span>
            </button>
        @endforeach
    </div>

    <input type="file" name="image" accept="image/*" class="hidden" data-file="1" aria-label="Image 1 file">
    <input type="file" name="image_2" accept="image/*" class="hidden" data-file="2" aria-label="Image 2 file">
    <input type="hidden" name="remove_image" value="0" data-remove="1">
    <input type="hidden" name="remove_image_2" value="0" data-remove="2">
    <input type="hidden" name="cover" value="1" data-cover>

    <div class="hidden mt-2" data-actions>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-action="cover" class="{{ $btn['outline'] }}">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
                <span>Set as cover</span>
            </button>
            <button type="button" data-action="replace" class="{{ $btn['secondary'] }}">
                <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                <span>Replace</span>
            </button>
            <button type="button" data-action="remove" class="{{ $btn['secondary'] }}">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                <span>Remove</span>
            </button>
        </div>
    </div>

    <p class="{{ $hint }}">Up to 2 images. JPG, PNG or WEBP, max {{ $maxImageMb }}MB each. Click a photo for options.</p>
</div>

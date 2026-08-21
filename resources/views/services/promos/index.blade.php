@extends('layouts.app')

@section('title', 'Promos & Discounts')
@section('content')

<div class="max-w-5xl p-6 mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-[#3C2F23] dark:text-white">Promos & Discounts</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Run limited-time discounts on specific treatments or packages.</p>
        </div>
        <button type="button" onclick="openPromoModal()"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] shadow-sm hover:opacity-90 transition">
            <i class="text-xs fa-solid fa-plus"></i>
            New Promo
        </button>
    </div>

    @if(session('success'))
        <div class="p-3 text-sm text-green-700 bg-green-50 rounded-xl ring-1 ring-green-200 dark:bg-green-900/10 dark:text-green-400 dark:ring-green-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- ── Promo list ─────────────────────────────────────────────────── --}}
    <div class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @if($promos->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                <i class="mb-3 text-3xl fa-solid fa-tag"></i>
                <p class="text-sm">No promos yet.</p>
                <button type="button" onclick="openPromoModal()" class="mt-3 text-sm font-semibold text-[#8B7355] hover:text-[#6F5430]">
                    Create your first promo →
                </button>
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach($promos as $promo)
                @php
                    $isExpired = $promo->end_date->isPast();
                    $isFuture  = $promo->start_date->isFuture();
                @endphp
                <div class="flex items-center justify-between gap-4 px-6 py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $promo->name }}</p>
                            <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full
                                {{ !$promo->is_active ? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                                    : ($isExpired ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                                    : ($isFuture ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'
                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400')) }}">
                                {{ !$promo->is_active ? 'Inactive' : ($isExpired ? 'Expired' : ($isFuture ? 'Upcoming' : 'Active')) }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $promo->discount_type === 'percent' ? $promo->discount_value . '% off' : '₱' . number_format($promo->discount_value, 2) . ' off' }}
                            &nbsp;•&nbsp;
                            {{ $promo->start_date->format('M d, Y') }} – {{ $promo->end_date->format('M d, Y') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            @php
                                $names = $promo->treatments->pluck('name')->merge($promo->packages->pluck('name'));
                            @endphp
                            Applies to: {{ $names->isNotEmpty() ? $names->implode(', ') : '—' }}
                        </p>
                    </div>

                    <div class="flex items-center flex-shrink-0 gap-2">
                        <button type="button"
                            onclick='openPromoModal(@json($promo->load(["treatments:id", "packages:id"])))'
                            class="flex items-center justify-center text-gray-500 transition w-9 h-9 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400">
                            <i class="text-sm fa-solid fa-pen"></i>
                        </button>
                        <form method="POST" action="{{ route('promos.destroy', $promo) }}" onsubmit="return confirm('Delete this promo? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="flex items-center justify-center text-red-500 transition w-9 h-9 rounded-xl hover:bg-red-50 dark:hover:bg-red-900/20 dark:text-red-400">
                                <i class="text-sm fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>

</div>

{{-- ══════════════════════ PROMO MODAL (create + edit, shared) ══════════════════════ --}}
<div id="promoModal" class="fixed inset-0 z-[130] hidden">
    <div class="absolute inset-0 bg-black/55 backdrop-blur-[2px]" onclick="closePromoModal()"></div>
    <div class="relative mx-auto w-[92%] max-w-xl mt-10 sm:mt-16">
        <div class="overflow-hidden bg-white dark:bg-gray-800 shadow-2xl rounded-3xl ring-1 ring-black/10 dark:ring-white/10 max-h-[85vh] flex flex-col">

            <div class="flex items-center justify-between flex-shrink-0 px-6 py-4 border-b border-black/5 dark:border-white/10">
                <h3 id="promoModalTitle" class="text-lg font-semibold text-[#3C2F23] dark:text-white">New Promo</h3>
                <button type="button" onclick="closePromoModal()"
                    class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                    <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="promoForm" method="POST" class="flex flex-col flex-1 min-h-0">
                @csrf
                <input type="hidden" name="_method" id="promoFormMethod" value="POST">

                <div class="flex-1 p-6 space-y-4 overflow-y-auto">

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Promo Name</label>
                        <input type="text" name="name" id="promoName" required placeholder="e.g. Summer Sale"
                            class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Discount Type</label>
                            <select name="discount_type" id="promoDiscountType" required
                                class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                                <option value="percent">% Off</option>
                                <option value="fixed">₱ Off</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Discount Value</label>
                            <input type="number" name="discount_value" id="promoDiscountValue" step="0.01" min="0" required placeholder="20"
                                class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">Start Date</label>
                            <input type="date" name="start_date" id="promoStartDate" required
                                class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">End Date</label>
                            <input type="date" name="end_date" id="promoEndDate" required
                                class="w-full mt-1 rounded-xl border-black/10 dark:border-white/10 dark:bg-gray-700 dark:text-white ring-1 ring-black/5 dark:ring-white/10 focus:ring-2 focus:ring-[#8B7355]/40 text-sm">
                        </div>
                    </div>

                    <div id="promoActiveWrapper" class="hidden">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" id="promoIsActive" value="1" checked
                                class="w-4 h-4 rounded accent-[#8B7355]">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Promo is active</span>
                        </label>
                    </div>

                    <hr class="border-[#E8DDD0] dark:border-gray-700">

                    <div>
                        <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Apply to Treatments</p>
                        <div class="grid grid-cols-1 gap-1.5 max-h-40 overflow-y-auto pr-1">
                            @forelse($treatments as $t)
                                <label class="flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span class="text-gray-700 dark:text-gray-200">{{ $t->name }}</span>
                                    <span class="flex items-center flex-shrink-0 gap-2">
                                        <span class="text-xs text-gray-400">₱{{ number_format($t->price, 2) }}</span>
                                        <input type="checkbox" name="treatment_ids[]" value="{{ $t->id }}" class="promo-treatment-checkbox w-4 h-4 rounded accent-[#8B7355]">
                                    </span>
                                </label>
                            @empty
                                <p class="text-xs italic text-gray-400">No treatments available.</p>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Apply to Packages</p>
                        <div class="grid grid-cols-1 gap-1.5 max-h-40 overflow-y-auto pr-1">
                            @forelse($packages as $p)
                                <label class="flex items-center justify-between gap-2 px-3 py-2 text-sm rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <span class="text-gray-700 dark:text-gray-200">{{ $p->name }}</span>
                                    <span class="flex items-center flex-shrink-0 gap-2">
                                        <span class="text-xs text-gray-400">₱{{ number_format($p->price, 2) }}</span>
                                        <input type="checkbox" name="package_ids[]" value="{{ $p->id }}" class="promo-package-checkbox w-4 h-4 rounded accent-[#8B7355]">
                                    </span>
                                </label>
                            @empty
                                <p class="text-xs italic text-gray-400">No packages available.</p>
                            @endforelse
                        </div>
                    </div>

                    <div id="promoError" class="hidden p-3 text-sm text-red-600 rounded-xl bg-red-50 ring-1 ring-red-200 dark:bg-red-900/20 dark:ring-red-800 dark:text-red-400"></div>

                </div>

                <div class="flex-shrink-0 px-6 py-4 border-t border-black/5 dark:border-white/10">
                    <button type="submit" id="promoSubmitBtn"
                        class="w-full py-3 text-sm font-semibold text-white rounded-xl bg-gradient-to-r from-[#8B7355] to-[#6F5430] shadow-md hover:opacity-90 transition">
                        Save Promo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const PROMO_STORE_URL = "{{ route('promos.store') }}";

    function closePromoModal() {
        document.getElementById('promoModal').classList.add('hidden');
    }

    function openPromoModal(promo = null) {
        const form        = document.getElementById('promoForm');
        const title       = document.getElementById('promoModalTitle');
        const methodInput = document.getElementById('promoFormMethod');
        const activeWrap  = document.getElementById('promoActiveWrapper');

        form.reset();
        document.querySelectorAll('.promo-treatment-checkbox, .promo-package-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('promoError').classList.add('hidden');

        if (promo) {
            title.textContent = 'Edit Promo';
            form.action = `/services/promos/${promo.id}`;
            methodInput.value = 'PUT';

            document.getElementById('promoName').value = promo.name;
            document.getElementById('promoDiscountType').value = promo.discount_type;
            document.getElementById('promoDiscountValue').value = promo.discount_value;
            document.getElementById('promoStartDate').value = promo.start_date;
            document.getElementById('promoEndDate').value = promo.end_date;
            document.getElementById('promoIsActive').checked = !!promo.is_active;
            activeWrap.classList.remove('hidden');

            (promo.treatments || []).forEach(t => {
                const cb = document.querySelector(`.promo-treatment-checkbox[value="${t.id}"]`);
                if (cb) cb.checked = true;
            });
            (promo.packages || []).forEach(p => {
                const cb = document.querySelector(`.promo-package-checkbox[value="${p.id}"]`);
                if (cb) cb.checked = true;
            });
        } else {
            title.textContent = 'New Promo';
            form.action = PROMO_STORE_URL;
            methodInput.value = 'POST';
            activeWrap.classList.add('hidden');
        }

        document.getElementById('promoModal').classList.remove('hidden');
    }

    document.getElementById('promoForm')?.addEventListener('submit', function (e) {
        const treatmentChecked = document.querySelectorAll('.promo-treatment-checkbox:checked').length;
        const packageChecked   = document.querySelectorAll('.promo-package-checkbox:checked').length;
        const errorEl = document.getElementById('promoError');

        if (treatmentChecked === 0 && packageChecked === 0) {
            e.preventDefault();
            errorEl.textContent = 'Select at least one treatment or package.';
            errorEl.classList.remove('hidden');
        }
    });

    window.openPromoModal  = openPromoModal;
    window.closePromoModal = closePromoModal;
</script>

@endsection

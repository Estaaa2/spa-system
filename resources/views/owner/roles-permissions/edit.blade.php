@extends('layouts.app')

@section('content')
<div class="p-6 mx-auto space-y-6 max-w-7xl">
    <x-page-header
        title="Edit Role — {{ $roleMeta['title'] }}"
        :subtitle="($appliesToAllBranches ?? false) ? 'Update what this role can access in every branch.' : 'Update what this role can access for the current branch.'"
    />

    {{-- Branch Context Banner --}}
    <div class="flex flex-col gap-2 px-4 py-3 text-sm text-blue-700 border border-blue-200 rounded-xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700 dark:text-blue-300 md:flex-row md:items-center">
        <div class="flex items-center gap-3">
            <i class="fa-solid fa-code-branch"></i>
            <span>
                @if($appliesToAllBranches ?? false)
                    <strong>{{ $roleMeta['title'] }}</strong> works across every branch, so these permissions apply to all branches.
                @else
                    Editing <strong>{{ $roleMeta['title'] }}</strong> permissions for branch:
                    <strong>{{ $branch->name }}</strong>
                    @if($branch->location)
                        — {{ $branch->location }}
                    @endif
                @endif
            </span>
        </div>

        @unless($appliesToAllBranches ?? false)
            <span class="text-xs text-blue-500 md:ml-auto dark:text-blue-400">
                Switch branches from the top navigation to edit another branch.
                @if($payrollAllBranches ?? false)
                    Payroll permissions apply to every branch.
                @endif
            </span>
        @endunless
    </div>

    {{-- Role Summary --}}
    <div class="grid gap-4 md:grid-cols-4">
        <div class="p-4 bg-white border rounded-2xl dark:bg-gray-800 dark:border-gray-700 md:col-span-2">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#8B7355]/10 text-[#8B7355]">
                    <i class="{{ $roleMeta['icon'] }}"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Role</p>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-gray-100">{{ $roleMeta['title'] }}</h2>
                </div>
            </div>

            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                {{ $roleMeta['description'] }}
            </p>
        </div>

        <div class="p-4 bg-white border rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Selected</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-gray-100" id="overall-selected">
                {{ $summary['selected'] }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Effective permissions</p>
        </div>

        <div class="p-4 bg-white border rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Overrides</p>
            <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-gray-100">
                {{ $summary['overridden'] }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Customized for this branch</p>
        </div>
    </div>

    {{-- Locked Suite Sections --}}
    @if($lockedSections->count())
        <div class="grid gap-4 md:grid-cols-2">
            @foreach($lockedSections as $lockedSection)
                <div class="p-4 border border-dashed rounded-2xl bg-gray-50 dark:bg-gray-900/20 dark:border-gray-700">
                    <div class="flex items-start gap-3">
                        <div class="flex items-center justify-center w-10 h-10 text-gray-500 bg-gray-200 rounded-xl dark:bg-gray-700 dark:text-gray-300">
                            <i class="fa-solid fa-lock"></i>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                {{ $lockedSection['title'] }} permissions are locked
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $lockedSection['message'] }}
                            </p>
                            <a href="{{ route('owner.subscription.index') }}"
                               class="inline-flex items-center gap-2 mt-3 text-sm font-medium text-[#8B7355] hover:underline">
                                <i class="fa-solid fa-crown text-yellow-500"></i>
                                Upgrade to enable
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @php
        // Phase 5 button tokens, from appointments.blade.php's $btn map (canonical).
        $btnBase = 'inline-flex items-center justify-center gap-1.5 min-h-[44px] min-w-[44px] px-4 py-2 text-sm '
                 . 'font-medium rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 '
                 . 'focus-visible:ring-[#8B7355] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-800';
        $btnPrimary   = $btnBase . ' bg-[#8B7355] text-white hover:bg-[#7A6348]';
        $btnSecondary = $btnBase . ' border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 '
                      . 'dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700';

        // After a refused save (conflict not yet confirmed), show what the owner had ticked.
        $restoring    = session()->hasOldInput();
        $oldPerms     = (array) old('permissions', []);
        $protected    = $pageRules['protected'] ?? [];
        $unavailable  = $pageRules['unavailable'] ?? [];   // Phase 5.1: permission => reason
        $roleTitle    = $roleMeta['title'];
        $rolePlural   = \Illuminate\Support\Str::plural(\Illuminate\Support\Str::lower($roleTitle));
    @endphp

    {{-- ── Presets (RP4) ── Fill the boxes from a starting point; nothing is saved until Save. --}}
    <section aria-labelledby="presetsTitle" class="bg-white border shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        <div class="px-4 py-4 border-b sm:px-6 dark:border-gray-700">
            <h2 id="presetsTitle" class="text-base font-semibold text-gray-900 dark:text-white">Start from a preset</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A preset ticks the boxes below for you. Review them, then press Save — nothing changes until you do.
            </p>
        </div>
        <div class="grid gap-3 p-4 sm:p-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($presets as $preset)
                <div class="flex flex-col justify-between gap-3 p-4 border border-gray-200 rounded-xl dark:border-gray-700">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $preset['label'] }}</h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $preset['description'] }}</p>
                    </div>
                    <button type="button" data-apply-preset="{{ $preset['key'] }}" class="{{ $btnSecondary }} self-start">
                        <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        Use this preset<span class="sr-only">: {{ $preset['label'] }}</span>
                    </button>
                </div>
            @endforeach
        </div>
        @if ($role->name !== 'therapist')
            {{-- RP3 / Q1: "does treatments" is a per-person switch on the Staff page, not a permission. --}}
            <p class="px-4 pb-4 text-sm text-gray-600 sm:px-6 dark:text-gray-300">
                <i class="mr-1 fa-solid fa-spa text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                @if ($treatmentStaffCount > 0)
                    {{ $treatmentStaffCount }} {{ $treatmentStaffCount === 1 ? \Illuminate\Support\Str::lower($roleTitle) : $rolePlural }} at this branch also {{ $treatmentStaffCount === 1 ? 'does' : 'do' }} treatments.
                @else
                    To let a {{ \Illuminate\Support\Str::lower($roleTitle) }} also give treatments, turn on "Also performs treatments" for that person.
                @endif
                That's set per person on the
                <a href="{{ route('staff.index') }}" class="inline-flex items-center min-h-[44px] -my-3 font-medium text-[#8B7355] hover:underline dark:text-[#C4A97D]">Staff page</a>,
                not here.
            </p>
        @endif
    </section>

    {{-- Dependency notes (D8.1): announced, so a box that ticks itself is never a surprise. --}}
    <div id="depNote" role="status" aria-live="polite"
         class="hidden px-4 py-3 text-sm border rounded-xl border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-200"></div>

    <form method="POST"
          action="{{ route('owner.roles-permissions.update', $role) }}"
          id="permission-form"
          class="bg-white border rounded-2xl dark:bg-gray-800 dark:border-gray-700">
        @csrf
        @method('PUT')
        <input type="hidden" name="confirm_conflicts" id="confirmConflictsInput" value="0">

        <div class="flex flex-col gap-3 px-6 py-4 border-b bg-gray-50/70 rounded-t-2xl dark:bg-gray-900/20 dark:border-gray-700 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-sm font-semibold tracking-wide text-gray-700 uppercase dark:text-gray-300">
                    Permission Sections
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Related permissions are grouped by feature so they are easier to manage.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button type="reset" class="{{ $btnSecondary }}">
                    Revert
                </button>
                <a href="{{ route('owner.roles-permissions.index') }}" class="{{ $btnSecondary }}">
                    Cancel
                </a>
                <button type="submit" class="{{ $btnPrimary }}">
                    Save Changes
                </button>
            </div>
        </div>

        <div class="p-6 space-y-4">
            @foreach($sections as $section)
                <details class="overflow-hidden border rounded-2xl group dark:border-gray-700" closed>
                    <summary class="flex items-center justify-between gap-4 px-5 py-4 cursor-pointer select-none bg-gray-50 dark:bg-gray-900/20">
                        <div class="flex items-start gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#8B7355]/10 text-[#8B7355]">
                                <i class="{{ $section['icon'] }}"></i>
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                        {{ $section['title'] }}
                                    </h3>
                                    <span class="px-2 py-1 text-xs font-medium text-gray-600 bg-white border rounded-full dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                                        <span data-section-selected="{{ $section['key'] }}">{{ $section['selected_count'] }}</span>/{{ $section['total_count'] }} selected
                                    </span>
                                </div>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $section['description'] }}
                                </p>
                            </div>
                        </div>

                        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 group-open:rotate-180"
                             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                        </svg>
                    </summary>

                    <div class="p-5 bg-white dark:bg-gray-800">
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <button type="button"
                                    data-select-section="{{ $section['key'] }}"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">
                                Select all
                            </button>

                            <button type="button"
                                    data-clear-section="{{ $section['key'] }}"
                                    class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">
                                Clear all
                            </button>
                        </div>

                        <div class="grid gap-3 lg:grid-cols-2">
                            @foreach($section['permissions'] as $permission)
                                @php
                                    $isProtected   = in_array($permission['name'], $protected, true);
                                    $lockedReason  = $unavailable[$permission['name']] ?? null;   // can't work for this role
                                    $isLocked      = $isProtected || $lockedReason !== null;
                                    $isChecked     = $isProtected || ($lockedReason === null && ($restoring ? in_array($permission['name'], $oldPerms, true) : $permission['checked']));
                                    $lockedNoteId  = 'locked-' . \Illuminate\Support\Str::slug($permission['name']);
                                @endphp
                                <label class="flex items-start gap-3 p-4 transition border rounded-xl dark:border-gray-700 {{ $isLocked ? 'bg-gray-50 dark:bg-gray-900/30' : 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/20' }}">
                                    @if ($isProtected)
                                        {{-- Always on for the owner (answer P9). Disabled boxes aren't submitted,
                                             so a hidden input carries the value; the server adds it anyway. --}}
                                        <input type="hidden" name="permissions[]" value="{{ $permission['name'] }}">
                                    @endif
                                    <input type="checkbox"
                                           @unless ($isLocked) name="permissions[]" @endunless
                                           value="{{ $permission['name'] }}"
                                           data-permission-checkbox
                                           data-section="{{ $section['key'] }}"
                                           @if ($isLocked) disabled aria-describedby="{{ $lockedNoteId }}" @endif
                                           {{ $isChecked ? 'checked' : '' }}
                                           class="mt-1 w-5 h-5 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600 disabled:opacity-60">

                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                                                {{ $permission['label'] }}
                                            </p>

                                            @php
                                                $sourceClasses = match($permission['source_type']) {
                                                    'enabled' => 'text-green-700 bg-green-100 dark:bg-green-900/30 dark:text-green-300',
                                                    'disabled' => 'text-red-700 bg-red-100 dark:bg-red-900/30 dark:text-red-300',
                                                    default => 'text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300',
                                                };
                                            @endphp

                                            <span class="px-2 py-1 text-[11px] font-medium rounded-full {{ $sourceClasses }}">
                                                {{ $permission['source'] }}
                                            </span>

                                            @if ($lockedReason)
                                                {{-- Phase 5.1: the app refuses this for the role, so it can't be granted. --}}
                                                <span id="{{ $lockedNoteId }}"
                                                      class="px-2 py-1 text-[11px] font-medium rounded-full text-gray-700 bg-gray-200 dark:bg-gray-700 dark:text-gray-200">
                                                    <i class="mr-0.5 fa-solid fa-lock" aria-hidden="true"></i> {{ $lockedReason }}<span class="sr-only">, not available for {{ $roleTitle }}</span>
                                                </span>
                                            @elseif ($isProtected)
                                                <span id="{{ $lockedNoteId }}"
                                                      class="px-2 py-1 text-[11px] font-medium rounded-full text-gray-700 bg-gray-200 dark:bg-gray-700 dark:text-gray-200">
                                                    <i class="mr-0.5 fa-solid fa-lock" aria-hidden="true"></i> Always on for the owner
                                                </span>
                                            @elseif (($payrollAllBranches ?? false) && \App\Services\Payroll\PayrollPermissions::isPayrollPermission($permission['name']))
                                                <span class="px-2 py-1 text-[11px] font-medium rounded-full text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                                                    All branches
                                                </span>
                                            @endif
                                        </div>

                                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                                            {{ $permission['description'] }}
                                        </p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </details>
            @endforeach
        </div>

        <div class="flex flex-col gap-3 px-6 py-4 border-t bg-gray-50 rounded-b-2xl dark:bg-gray-900/20 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-2">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    @if($appliesToAllBranches ?? false)
                        Saved changes apply to every branch.
                    @else
                        Saved changes here only affect <strong>{{ $branch->name }}</strong>{{ ($payrollAllBranches ?? false) ? ' (payroll permissions apply to every branch)' : '' }}.
                    @endif
                </p>
                @if ($pageRules['canApplyAll'])
                    {{-- D9 / Q2: off by default; the confirmation names how many branches change. --}}
                    <label class="inline-flex items-center gap-2 min-h-[44px] text-sm text-gray-700 cursor-pointer dark:text-gray-200">
                        <input type="hidden" name="apply_all_branches" value="0">
                        <input type="checkbox" name="apply_all_branches" value="1" id="applyAllBranches" @checked(old('apply_all_branches'))
                               class="w-5 h-5 border-gray-300 rounded text-[#8B7355] focus:ring-[#8B7355] dark:border-gray-600">
                        Also apply to all {{ $pageRules['branchCount'] }} branches
                    </label>
                @endif
            </div>

            <button type="submit" class="{{ $btnPrimary }}">
                Save Changes
            </button>
        </div>
    </form>

    {{-- ── Save confirmation (D8.3 conflicts, D9/Q2 all branches) ──────────
         Follows appointments.blade.php's modal contract: alertdialog, labelled
         and described, focus moves in, Tab is trapped, Escape closes, focus
         returns. Shown only when a save adds a conflict or applies to every
         branch; the server repeats the conflict check. --}}
    <div id="confirmSaveModal" class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50">
        <div class="flex items-start justify-center min-h-full p-4 sm:items-center">
            <div role="alertdialog" aria-modal="true" aria-labelledby="confirmSaveTitle" aria-describedby="confirmSaveBody"
                 class="w-full max-w-lg bg-white shadow-xl rounded-2xl dark:bg-gray-800">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 id="confirmSaveTitle" class="text-lg font-semibold text-gray-900 dark:text-white">Check before saving</h2>
                </div>
                <div id="confirmSaveBody" class="p-6 space-y-4 text-sm text-gray-600 dark:text-gray-300">
                    <div id="confirmConflicts" class="hidden space-y-3">
                        <p>These permissions together can be risky for <strong>{{ $roleTitle }}</strong>. You can still save them.</p>
                        <ul id="confirmConflictList" class="space-y-2"></ul>
                    </div>
                    <p id="confirmAllBranches" class="hidden px-4 py-3 border rounded-xl border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-200">
                        This replaces <strong>{{ $roleTitle }}</strong> permissions at all {{ $pageRules['branchCount'] }} branches, not just {{ $branch->name }}.
                    </p>
                </div>
                <div class="flex flex-col-reverse gap-2 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl dark:border-gray-700 dark:bg-gray-900/20 sm:flex-row sm:justify-end">
                    <button type="button" id="confirmSaveCancel" class="{{ $btnSecondary }}">Go back</button>
                    <button type="button" id="confirmSaveOk" class="{{ $btnPrimary }}">Save anyway</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
details[open] > summary svg {
    transform: rotate(180deg);
}
</style>

<script>
(function () {
    // One source: App\Services\Permissions\PermissionRules / PermissionPresets (JSON from the controller).
    const RULES   = @json($pageRules);
    const PRESETS = @json($presets);
    const SERVER_CONFLICTS = @json($pendingConflicts);

    const form    = document.getElementById('permission-form');
    const boxes   = Array.from(form.querySelectorAll('[data-permission-checkbox]'));
    const byName  = Object.fromEntries(boxes.map(cb => [cb.value, cb]));
    const label   = (n) => RULES.labels[n] || n;
    const note    = document.getElementById('depNote');

    // ── Dependencies (D8.1) ──────────────────────────────────────────────
    const DEPENDENTS = {};
    Object.entries(RULES.dependencies).forEach(([dep, bases]) => bases.forEach(b => (DEPENDENTS[b] ||= []).push(dep)));

    function closure(start, graph) {
        const out = [], queue = [...(graph[start] || [])];
        while (queue.length) {
            const n = queue.shift();
            if (out.includes(n)) continue;
            out.push(n);
            queue.push(...(graph[n] || []));
        }
        return out;
    }

    function showNote(text) {
        if (!text) { note.classList.add('hidden'); note.textContent = ''; return; }
        note.textContent = text;
        note.classList.remove('hidden');
    }

    function onToggle(cb) {
        const changed = [];
        if (cb.checked) {
            closure(cb.value, RULES.dependencies).forEach(base => {
                const box = byName[base];
                if (box && !box.checked && !box.disabled) { box.checked = true; changed.push(label(base)); }
            });
            showNote(changed.length ? `Also turned on, because "${label(cb.value)}" needs ${changed.length === 1 ? 'it' : 'them'}: ${changed.join(', ')}.` : '');
        } else {
            closure(cb.value, DEPENDENTS).forEach(dep => {
                const box = byName[dep];
                if (box && box.checked && !box.disabled) { box.checked = false; changed.push(label(dep)); }
            });
            showNote(changed.length ? `Also turned off, because ${changed.length === 1 ? 'it needs' : 'they need'} "${label(cb.value)}": ${changed.join(', ')}.` : '');
        }
        updateCounts();
    }

    // After bulk changes: add missing bases (select) or drop orphaned dependents (clear).
    function normalize(direction) {
        let changed = true;
        while (changed) {
            changed = false;
            boxes.forEach(cb => {
                if (cb.disabled) return;
                (RULES.dependencies[cb.value] || []).forEach(base => {
                    const b = byName[base];
                    if (!b || !cb.checked || b.checked) return;
                    if (direction === 'add') { b.checked = true; } else { cb.checked = false; }
                    changed = true;
                });
            });
        }
    }

    function updateCounts() {
        document.getElementById('overall-selected').textContent = boxes.filter(cb => cb.checked).length;
        [...new Set(boxes.map(cb => cb.dataset.section))].forEach(section => {
            const counter = document.querySelector(`[data-section-selected="${section}"]`);
            if (counter) counter.textContent = boxes.filter(cb => cb.dataset.section === section && cb.checked).length;
        });
    }

    boxes.forEach(cb => cb.addEventListener('change', () => onToggle(cb)));

    document.querySelectorAll('[data-select-section]').forEach(btn => btn.addEventListener('click', function () {
        boxes.filter(cb => cb.dataset.section === this.dataset.selectSection && !cb.disabled).forEach(cb => cb.checked = true);
        normalize('add');
        showNote('');
        updateCounts();
    }));

    document.querySelectorAll('[data-clear-section]').forEach(btn => btn.addEventListener('click', function () {
        boxes.filter(cb => cb.dataset.section === this.dataset.clearSection && !cb.disabled).forEach(cb => cb.checked = false);
        normalize('remove');
        showNote('');
        updateCounts();
    }));

    // ── Presets (RP4) ────────────────────────────────────────────────────
    document.querySelectorAll('[data-apply-preset]').forEach(btn => btn.addEventListener('click', function () {
        const preset = PRESETS.find(p => p.key === this.dataset.applyPreset);
        if (!preset) return;
        boxes.forEach(cb => { if (!cb.disabled) cb.checked = preset.permissions.includes(cb.value); });
        normalize('add');
        updateCounts();
        showNote(`"${preset.label}" applied. Review the boxes below, then press Save — nothing has changed yet.`);
    }));

    form.addEventListener('reset', () => setTimeout(() => { showNote(''); updateCounts(); }, 0));

    // ── Conflicts (D8.3): same rules as PermissionRules::newConflicts ────
    function conflictsFor(role, perms) {
        const set = new Set(perms), found = [];
        RULES.conflictPairs.forEach((pair, i) => {
            if (pair.roles && !pair.roles.includes(role)) return;
            if (pair.permissions.every(p => set.has(p))) found.push({ key: 'pair:' + i, permissions: pair.permissions, reason: pair.reason });
        });
        RULES.roleLimits.forEach((limit, i) => {
            const outside = limit.allowed_roles ? !limit.allowed_roles.includes(role) : (limit.denied_roles || []).includes(role);
            const held = limit.permissions.filter(p => set.has(p));
            if (outside && held.length) found.push({ key: 'role:' + i, permissions: held, reason: limit.reason });
        });
        return found;
    }

    function newConflicts() {
        const after = boxes.filter(cb => cb.checked).map(cb => cb.value);
        const had = conflictsFor(RULES.role, RULES.before).map(c => c.key);
        return conflictsFor(RULES.role, after)
            .filter(c => !had.includes(c.key))
            .map(c => ({ reason: c.reason, labels: c.permissions.map(label) }));
    }

    // ── Confirmation modal ───────────────────────────────────────────────
    const modal   = document.getElementById('confirmSaveModal');
    const okBtn   = document.getElementById('confirmSaveOk');
    const backBtn = document.getElementById('confirmSaveCancel');
    const confirmInput = document.getElementById('confirmConflictsInput');
    const applyAll = document.getElementById('applyAllBranches');
    let returnFocus = null;

    function openModal(conflicts, allBranches) {
        const list = document.getElementById('confirmConflictList');
        list.innerHTML = '';
        conflicts.forEach(c => {
            const li = document.createElement('li');
            li.className = 'px-4 py-3 border rounded-xl border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-900/10 dark:text-amber-200';
            const strong = document.createElement('strong');
            strong.textContent = c.labels.join(' + ');
            li.append(strong, document.createElement('br'), document.createTextNode(c.reason));
            list.appendChild(li);
        });
        document.getElementById('confirmConflicts').classList.toggle('hidden', conflicts.length === 0);
        document.getElementById('confirmAllBranches').classList.toggle('hidden', !allBranches);
        okBtn.textContent = allBranches && conflicts.length === 0 ? 'Save to all branches' : 'Save anyway';

        returnFocus = document.activeElement;
        modal.classList.remove('hidden');
        document.documentElement.style.overflow = 'hidden';
        backBtn.focus();
    }

    function closeModal() {
        if (modal.classList.contains('hidden')) return;
        modal.classList.add('hidden');
        document.documentElement.style.overflow = '';
        (returnFocus || form.querySelector('[type="submit"]'))?.focus();
    }

    backBtn.addEventListener('click', closeModal);
    okBtn.addEventListener('click', () => { confirmInput.value = '1'; closeModal(); form.submit(); });
    modal.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }
        if (e.key !== 'Tab') return;
        const nodes = [backBtn, okBtn];
        if (e.shiftKey && document.activeElement === nodes[0]) { e.preventDefault(); nodes[1].focus(); }
        else if (!e.shiftKey && document.activeElement === nodes[1]) { e.preventDefault(); nodes[0].focus(); }
    });

    form.addEventListener('submit', function (e) {
        if (confirmInput.value === '1') return;          // already confirmed
        const conflicts = newConflicts();
        const allBranches = !!(applyAll && applyAll.checked);
        if (conflicts.length || allBranches) {
            e.preventDefault();
            openModal(conflicts, allBranches);
        }
    });

    updateCounts();

    // The server refused a save with unconfirmed conflicts: ask now.
    if (SERVER_CONFLICTS.length) openModal(SERVER_CONFLICTS, !!(applyAll && applyAll.checked));
}());
</script>
@endsection
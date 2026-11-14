{{--
    Customer ↔ spa chat widget (Nov 2026): a floating button at the bottom
    right and a chat window with two views, the list of conversations and one
    conversation. Driven by resources/js/chat-widget.js; every element it
    builds is styled by the .lc-* rules below, because Tailwind doesn't scan
    JS files.

    $mode  'customer'  on the landing page: the spas you messaged. The button
                       stays hidden until you have a conversation; a spa's own
                       Message button (spa window) opens its thread directly.
           'spa'       on staff pages: the spa's shared inbox ("reply to
                       customer messages"). The button is always there.
--}}
@php
    $isSpa = $mode === 'spa';
    $endpoints = $isSpa
        ? [
            'list'   => route('spa-chat.conversations'),
            'unread' => route('spa-chat.unread'),
            'thread' => url('/spa-chat/conversations/__ID__'),
            'send'   => url('/spa-chat/conversations/__ID__'),
        ]
        : [
            'list'   => route('chat.conversations'),
            'thread' => url('/chat/spas/__ID__/messages'),
            'send'   => url('/chat/spas/__ID__/messages'),
        ];
    $listTitle = $isSpa ? 'Customer messages' : 'Messages';
@endphp

<div id="lcRoot" data-chat-widget data-mode="{{ $mode }}"
    data-endpoints='@json($endpoints)'
    data-max="{{ \App\Models\ChatMessage::MAX_LENGTH }}">

    {{-- Floating button --}}
    <button type="button" id="lcLauncher" aria-haspopup="dialog" aria-controls="lcPanel"
        aria-label="{{ $listTitle }}"
        class="lc-launcher {{ $isSpa ? '' : 'hidden' }} fixed z-[90] right-4 sm:right-6 flex items-center justify-center w-14 h-14 text-white rounded-full shadow-xl bg-gradient-to-br from-[#8B7355] to-[#6F5430] hover:shadow-2xl transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B7355] dark:focus-visible:ring-offset-gray-900">
        <i class="text-xl fa-solid fa-comments" aria-hidden="true"></i>
        <span id="lcLauncherBadge" class="hidden absolute -top-1 -right-1 min-w-[22px] h-[22px] px-1.5 text-xs font-bold leading-[22px] text-center text-white bg-red-600 rounded-full ring-2 ring-white dark:ring-gray-900"></span>
    </button>

    {{-- Chat window: a sheet on phones, a window at the bottom right from sm --}}
    <div id="lcPanel" role="dialog" aria-modal="false" aria-labelledby="lcTitle"
        class="hidden fixed z-[158] inset-x-0 bottom-0 sm:inset-x-auto sm:right-6 sm:bottom-24 flex flex-col w-full sm:w-[380px] h-[85vh] sm:h-[560px] sm:max-h-[calc(100vh-8rem)] bg-white dark:bg-gray-800 shadow-2xl rounded-t-2xl sm:rounded-2xl ring-1 ring-black/10 dark:ring-white/10 overflow-hidden">

        <div class="flex items-center gap-1 px-2 py-2 text-white shrink-0 bg-gradient-to-r from-[#8B7355] to-[#6F5430]">
            <button type="button" id="lcBack" aria-label="Back to all conversations"
                class="hidden items-center justify-center w-11 h-11 rounded-xl hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            </button>
            <div class="flex-1 min-w-0 px-2">
                <h2 id="lcTitle" class="text-base font-semibold truncate">{{ $listTitle }}</h2>
                <p id="lcSubtitle" class="hidden text-xs truncate text-white/85"></p>
            </div>
            <button type="button" id="lcClose" aria-label="Close messages"
                class="flex items-center justify-center w-11 h-11 rounded-xl hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                <i class="text-lg fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        {{-- View 1: conversations --}}
        <div id="lcListView" class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
            <ul id="lcList" class="divide-y divide-black/5 dark:divide-white/10" aria-label="{{ $listTitle }}"></ul>
            <div id="lcListEmpty" class="hidden px-6 py-16 text-center">
                <i class="text-3xl fa-regular fa-comments text-[#C4A97D]" aria-hidden="true"></i>
                <p class="mt-3 text-sm font-semibold text-[#3C2F23] dark:text-white">No messages yet</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $isSpa ? 'When a customer messages your spa, it shows here.' : 'Open a spa and tap Message to ask them anything.' }}
                </p>
            </div>
        </div>

        {{-- View 2: one conversation --}}
        <div id="lcThreadView" class="hidden flex-1 min-h-0 flex-col">
            <div id="lcMessages" class="flex-1 min-h-0 px-3 py-4 space-y-2 overflow-y-auto overscroll-contain bg-[#FAF7F2] dark:bg-gray-900/40"
                role="log" aria-live="polite" aria-label="Conversation"></div>

            <form id="lcForm" class="flex items-end gap-2 p-3 border-t shrink-0 border-black/5 dark:border-white/10" novalidate>
                <label for="lcInput" class="sr-only">Message</label>
                <textarea id="lcInput" rows="1" maxlength="{{ \App\Models\ChatMessage::MAX_LENGTH }}"
                    placeholder="Type a message…"
                    class="flex-1 max-h-32 min-h-[44px] py-2.5 text-sm resize-none rounded-xl border-black/15 text-[#3C2F23] placeholder:text-gray-400 dark:border-white/15 dark:bg-gray-700 dark:text-white focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/30"></textarea>
                <button type="submit" id="lcSend" aria-label="Send"
                    class="flex items-center justify-center w-11 h-11 text-white rounded-xl shrink-0 bg-[#8B7355] hover:bg-[#7A6348] disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#8B7355]">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                </button>
            </form>
            <p id="lcError" role="alert" class="hidden px-3 pb-3 -mt-1 text-xs text-red-700 dark:text-red-400"></p>
        </div>
    </div>
</div>

<style>
    /* Above the bottom edge and the phone's home bar. */
    .lc-launcher { bottom: calc(1rem + env(safe-area-inset-bottom, 0px)); }
    @media (min-width: 640px) { .lc-launcher { bottom: 1.5rem; } }
    #lcPanel { padding-bottom: env(safe-area-inset-bottom, 0px); }
    #lcThreadView:not(.hidden) { display: flex; }
    #lcBack:not(.hidden) { display: flex; }

    /* Rows and bubbles are built in chat-widget.js. */
    .lc-row { display: flex; align-items: center; gap: 12px; width: 100%; min-height: 64px; padding: 10px 16px; text-align: left; transition: background-color .15s ease; }
    .lc-row:hover { background: #F6EFE6; }
    .lc-row:focus-visible { outline: 2px solid #8B7355; outline-offset: -2px; }
    .lc-avatar { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 40px; height: 40px; border-radius: 9999px; background: #F6EFE6; color: #6F5430; font-weight: 600; }
    .lc-row-main { flex: 1; min-width: 0; }
    .lc-row-top { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
    .lc-row-name { font-size: 14px; font-weight: 600; color: #3C2F23; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lc-row-time { flex-shrink: 0; font-size: 12px; color: #6b7280; }
    .lc-row-sub { font-size: 12px; color: #8B7355; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lc-row-preview { font-size: 13px; color: #4b5563; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lc-row.is-unread .lc-row-preview { font-weight: 600; color: #1f2937; }
    .lc-count { flex-shrink: 0; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 9999px; background: #dc2626; color: #fff; font-size: 12px; font-weight: 700; line-height: 22px; text-align: center; }

    .lc-day { margin: 12px 0 4px; text-align: center; font-size: 11px; font-weight: 600; color: #6b7280; }
    .lc-msg { display: flex; flex-direction: column; max-width: 80%; }
    .lc-msg.is-mine { margin-left: auto; align-items: flex-end; }
    .lc-bubble { padding: 8px 12px; border-radius: 16px; font-size: 14px; line-height: 1.45; white-space: pre-wrap; overflow-wrap: anywhere; }
    .lc-msg.is-mine .lc-bubble { background: #8B7355; color: #fff; border-bottom-right-radius: 4px; }
    .lc-msg:not(.is-mine) .lc-bubble { background: #fff; color: #3C2F23; border: 1px solid #eadfce; border-bottom-left-radius: 4px; }
    .lc-meta { margin-top: 2px; padding: 0 4px; font-size: 11px; color: #6b7280; }
    .lc-msg.is-pending .lc-bubble { opacity: .6; }
    .lc-note { padding: 32px 16px; text-align: center; font-size: 13px; color: #6b7280; }

    @media (prefers-color-scheme: dark) {
        .lc-row:hover { background: #374151; }
        .lc-avatar { background: #374151; color: #C4A97D; }
        .lc-row-name { color: #f3f4f6; }
        .lc-row-time, .lc-day, .lc-meta, .lc-note { color: #9ca3af; }
        .lc-row-sub { color: #C4A97D; }
        .lc-row-preview { color: #d1d5db; }
        .lc-row.is-unread .lc-row-preview { color: #fff; }
        .lc-msg:not(.is-mine) .lc-bubble { background: #1f2937; color: #f3f4f6; border-color: #374151; }
    }
</style>

@extends('layouts.app')
@section('content')
<div class="p-6 mx-auto max-w-7xl">
    <x-page-header title="HR Dashboard" subtitle="Overview of hiring, attendance and payroll."/>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 mt-6 md:grid-cols-4">
        @foreach([
            ['label' => 'Open Jobs',          'value' => $stats['open_jobs'],          'icon' => 'fa-briefcase',    'color' => 'bg-blue-50 text-blue-700'],
            ['label' => 'Pending Applicants', 'value' => $stats['pending_applicants'], 'icon' => 'fa-file-lines',   'color' => 'bg-yellow-50 text-yellow-700'],
            ['label' => 'Pending Interviews', 'value' => $stats['pending_interviews'], 'icon' => 'fa-comments',     'color' => 'bg-purple-50 text-purple-700'],
            ['label' => 'Active Staff',       'value' => $stats['total_staff'],        'icon' => 'fa-users',        'color' => 'bg-green-50 text-green-700'],
        ] as $stat)
        <div class="p-5 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg {{ $stat['color'] }}">
                    <i class="fa-solid {{ $stat['icon'] }}"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ $stat['value'] }}</p>
                    <p class="text-xs text-gray-500">{{ $stat['label'] }}</p>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="grid gap-6 mt-6 md:grid-cols-2">

        {{-- Recent Applications --}}
        <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800">
            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-white">Recent Applications</h3>
            @forelse($recentApplications as $app)
            <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                <div>
                    <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $app->full_name }}</p>
                    <p class="text-xs text-gray-500">{{ $app->jobPosting->title ?? 'N/A' }}</p>
                </div>
                <span class="px-2 py-1 text-xs font-semibold rounded-full
                    {{ $app->status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                      ($app->status === 'hired' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600') }}">
                    {{ ucfirst($app->status) }}
                </span>
            </div>
            @empty
            <p class="text-sm text-gray-400">No recent applications.</p>
            @endforelse
        </div>

        {{-- Upcoming Interviews --}}
        <div class="p-6 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800">
            <h3 class="mb-4 text-sm font-semibold text-gray-700 dark:text-white">Upcoming Interviews</h3>
            @forelse($upcomingInterviews as $interview)
            <div class="flex items-center justify-between py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                <div>
                    <p class="text-sm font-medium text-gray-800 dark:text-white">{{ $interview->applicant->full_name }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $interview->interview_date->format('M d, Y') }} at {{ $interview->interview_time }}
                    </p>
                </div>
                <span class="px-2 py-1 text-xs font-semibold text-purple-700 bg-purple-100 rounded-full">
                    Pending
                </span>
            </div>
            @empty
            <p class="text-sm text-gray-400">No upcoming interviews.</p>
            @endforelse
        </div>

    </div>
</div>
@endsection

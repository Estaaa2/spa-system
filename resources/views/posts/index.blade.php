@extends('layouts.app')

@section('content')
<div class="container px-4 py-6 mx-auto">
    <h1 class="mb-4 text-2xl font-bold">Posts</h1>

    <!-- Flash Message -->
    @if(session('success'))
    <div class="px-4 py-3 mb-4 text-green-700 bg-green-100 border border-green-400 rounded">
        {{ session('success') }}
    </div>
    @endif

    @can('create posts')
    <a href="{{ route('posts.create') }}" class="inline-block px-4 py-2 mb-4 text-white bg-blue-500 rounded hover:bg-blue-600">
        Create New Post
    </a>
    @endcan

    <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Title</th>
                    <th class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($posts as $post)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">{{ $post->title }}</td>
                    <td class="px-6 py-4 space-x-2 whitespace-nowrap">
                        @can('edit posts')
                        <a href="{{ route('posts.edit', $post->id) }}" class="text-blue-500 hover:text-blue-700">Edit</a>
                        @endcan

                        @can('delete posts')
                        <form action="{{ route('posts.destroy', $post->id) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700"
                                onclick="return confirm('Are you sure you want to delete this post?')">Delete</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $posts->links() }}
    </div>
</div>
@endsection

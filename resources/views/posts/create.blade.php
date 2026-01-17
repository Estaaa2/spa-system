<x-app-layout>
    <h1>Create Post</h1>

    <form method="POST" action="{{ route('posts.store') }}">
        @csrf

        <input type="text" name="title" placeholder="Post title">

        <button type="submit">
            Save
        </button>
    </form>

    @can('create posts')
    <a href="{{ route('posts.create') }}">
        Create Post
    </a>
@endcan

</x-app-layout>

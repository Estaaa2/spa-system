@can('edit posts')
    <a href="{{ route('posts.edit', $post) }}" class="btn btn-sm btn-warning">Edit</a>
@endcan

@role('admin')
    <form action="{{ route('posts.destroy', $post) }}" method="POST" class="d-inline">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this post?')">Delete</button>
    </form>
@endrole

<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;


class PostController extends Controller
{
    public function create()
    {
        return view('posts.create');
    }



    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        Post::create($data);


        Post::create([
            'title' => $request->title,
        ]);

        return redirect()->route('posts.index')
            ->with('success', 'Post created successfully');
    }

    public function index()
    {
        $posts = Post::latest()->get();
        $posts = Post::paginate(10); // or Post::simplePaginate(10)

        return view('posts.index', compact('posts'));
    }

    public function edit($id)
    {
        try {
            $post = Post::findOrFail($id);
            return view('posts.edit', compact('post'));
        } catch (\Exception $e) {
            dd($e->getMessage()); // Show the actual error
        }
    }

    public function update(Request $request, Post $post)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $post->update($data);

        return redirect()->route('posts.index')->with('success', 'Post updated successfully!');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()->route('posts.index')->with('success', 'Post deleted successfully!');
    }
}

<?php

namespace Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Blog\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{

    public function index()
    {
        $posts = BlogPost::published()
        ->with('category')
        ->latest('published_at')
        ->paginate(9);

        return view('blog::index', compact('posts'));
    }


    public function show($slug)
    {

        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();


        $post->increment('view_count');


        $relatedPosts = BlogPost::published()
            ->where('category_id', $post->category_id)
            ->where('id', '!=', $post->id)
            ->take(3)
            ->get();

        return view('blog::show', compact('post', 'relatedPosts'));
    }
}

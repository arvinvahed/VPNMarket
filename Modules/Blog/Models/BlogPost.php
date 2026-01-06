<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'title', 'slug', 'content', 'image',
        'seo_title', 'seo_description', 'seo_keywords',
        'is_published', 'published_at', 'view_count'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];


    protected static function boot()
    {
        parent::boot();
        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title, '-', null);
            }
            if (!$post->user_id) {
                $post->user_id = auth()->id();
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }


    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where('published_at', '<=', now());
    }
}

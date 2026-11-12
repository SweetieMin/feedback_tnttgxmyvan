<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\AdminImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Danh sách danh mục cho quản trị viên quản lý.
     */
    public function index(): Response
    {
        return Inertia::render('admin/categories', [
            'categories' => Category::query()
                ->select(['id', 'name', 'slug', 'is_active', 'avatar_path'])
                ->withCount('feedbacks')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'is_active' => $category->is_active,
                    'feedbacks_count' => $category->feedbacks_count,
                    'avatar' => $category->avatar_path ? Storage::disk('public')->url($category->avatar_path) : null,
                ]),
        ]);
    }

    /**
     * Tạo danh mục mới, luôn bắt đầu ở trạng thái đang hoạt động.
     */
    public function store(StoreCategoryRequest $request, AdminImage $images): RedirectResponse
    {
        $name = $request->string('name')->toString();

        $images->save(new Category, 'category-images', $request->file('avatar'), function (Category $category) use ($name) {
            $category->fill([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'is_active' => true,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã tạo danh mục.')]);

        return to_route('admin.categories.index');
    }

    /**
     * Sửa tên hoặc bật/tắt hiển thị danh mục (A12: không xoá cứng).
     */
    public function update(UpdateCategoryRequest $request, Category $category, AdminImage $images): RedirectResponse
    {
        $images->save($category, 'category-images', $request->file('avatar'),
            fn (Category $target) => $target->fill($request->safe()->except('avatar')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã cập nhật danh mục.')]);

        return to_route('admin.categories.index');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

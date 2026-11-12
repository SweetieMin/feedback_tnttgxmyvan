<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
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
                ->withCount('feedbacks')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'is_active']),
        ]);
    }

    /**
     * Tạo danh mục mới, luôn bắt đầu ở trạng thái đang hoạt động.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $name = $request->string('name')->toString();

        Category::create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'is_active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã tạo danh mục.')]);

        return to_route('admin.categories.index');
    }

    /**
     * Sửa tên hoặc bật/tắt hiển thị danh mục (A12: không xoá cứng).
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

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

<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_every_category_with_its_feedback_count(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $used = Category::factory()->create(['name' => 'A dùng nhiều']);
        Category::factory()->inactive()->create(['name' => 'B đã ẩn']);
        Feedback::factory()->count(2)->create(['category_id' => $used->id]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/categories')
                ->has('categories', 2)
                ->where('categories.0.name', 'A dùng nhiều')
                ->where('categories.0.feedbacks_count', 2)
                ->where('categories.1.is_active', false),
            );
    }

    public function test_renaming_to_an_empty_name_shows_a_vietnamese_error(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.update', $category), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Vui lòng nhập tên danh mục.']);
    }

    public function test_employee_and_handler_cannot_view_categories_index(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);

        $this->actingAs($employee)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($handler)->get(route('admin.categories.index'))->assertForbidden();
    }

    public function test_admin_can_create_a_category_with_auto_generated_slug(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), ['name' => 'Môi trường làm việc'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::where('name', 'Môi trường làm việc')->firstOrFail();

        $this->assertNotEmpty($category->slug);
        $this->assertTrue($category->is_active);
    }

    public function test_category_name_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Category::factory()->create(['name' => 'Quy trình nội bộ']);

        $this->actingAs($admin)
            ->from(route('admin.categories.index'))
            ->post(route('admin.categories.store'), ['name' => 'Quy trình nội bộ'])
            ->assertSessionHasErrors('name');
    }

    public function test_slug_collision_gets_a_unique_suffix(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        // "A B" và "A_B" là hai tên khác nhau (không trùng theo unique index
        // của cột name) nhưng Str::slug() rút gọn cả hai về cùng "a-b".
        $this->actingAs($admin)
            ->post(route('admin.categories.store'), ['name' => 'A B'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route('admin.categories.store'), ['name' => 'A_B'])
            ->assertSessionHasNoErrors();

        $slugs = Category::whereIn('name', ['A B', 'A_B'])->pluck('slug')->all();

        $this->assertCount(2, $slugs);
        $this->assertCount(2, array_unique($slugs));
    }

    public function test_non_admin_cannot_create_category(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);

        $this->actingAs($handler)
            ->post(route('admin.categories.store'), ['name' => 'Không được phép'])
            ->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Không được phép']);
    }

    public function test_admin_can_rename_and_deactivate_a_category(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $category = Category::factory()->create(['name' => 'Cũ', 'is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.categories.update', $category), [
                'name' => 'Mới',
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.categories.index'));

        $category->refresh();

        $this->assertSame('Mới', $category->name);
        $this->assertFalse($category->is_active);
    }

    public function test_non_admin_cannot_update_category(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $category = Category::factory()->create();

        $this->actingAs($employee)
            ->patch(route('admin.categories.update', $category), ['name' => 'Đổi tên'])
            ->assertForbidden();
    }
}

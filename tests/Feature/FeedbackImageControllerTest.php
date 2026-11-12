<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Rules\ValidTiptapDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeedbackImageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_image_url_is_accepted_in_a_feedback(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $url = $this->actingAs($user)
            ->post(route('feedback.images.store'), ['image' => UploadedFile::fake()->image('anh.png', 200, 120)])
            ->assertCreated()
            ->json('url');

        $this->assertMatchesRegularExpression(ValidTiptapDocument::IMAGE_SRC_PATTERN, $url);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));

        $this->actingAs($user)
            ->post(route('feedback.store'), [
                'title' => 'Có ảnh minh hoạ',
                'category_id' => Category::factory()->create()->id,
                'content' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Xem ảnh:']]],
                    ['type' => 'image', 'attrs' => ['src' => $url, 'alt' => 'anh', 'title' => 'anh']],
                ]],
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_rejects_non_image_svg_and_oversized_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        foreach ([
            UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
            UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
            UploadedFile::fake()->image('huge.jpg')->size(6000),
        ] as $file) {
            $this->actingAs($user)
                ->postJson(route('feedback.images.store'), ['image' => $file])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('image');
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_guest_cannot_upload(): void
    {
        $this->postJson(route('feedback.images.store'), [
            'image' => UploadedFile::fake()->image('anh.png'),
        ])->assertUnauthorized();
    }
}

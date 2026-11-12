<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidTiptapDocument;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidTiptapDocumentTest extends TestCase
{
    public function test_accepts_a_valid_feedback_document(): void
    {
        $validator = Validator::make(
            ['content' => $this->paragraphDoc('Nội dung góp ý hợp lệ.')],
            ['content' => [new ValidTiptapDocument('feedback')]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_rejects_disallowed_node_type(): void
    {
        $doc = [
            'type' => 'doc',
            'content' => [
                ['type' => 'table', 'content' => []],
            ],
        ];

        $validator = Validator::make(
            ['content' => $doc],
            ['content' => [new ValidTiptapDocument('feedback')]],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_rejects_javascript_link_scheme(): void
    {
        $doc = $this->linkDoc('javascript:alert(1)');

        $validator = Validator::make(
            ['content' => $doc],
            ['content' => [new ValidTiptapDocument('feedback')]],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_accepts_safe_https_link(): void
    {
        $doc = $this->linkDoc('https://example.com');

        $validator = Validator::make(
            ['content' => $doc],
            ['content' => [new ValidTiptapDocument('feedback')]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_rejects_oversized_document(): void
    {
        $doc = $this->paragraphDoc(str_repeat('a', 6 * 1024));

        $validator = Validator::make(
            ['content' => $doc],
            ['content' => [new ValidTiptapDocument('message')]],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_message_profile_accepts_heading(): void
    {
        $doc = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 1],
                    'content' => [['type' => 'text', 'text' => 'Tiêu đề']],
                ],
            ],
        ];

        $validator = Validator::make(
            ['content' => $doc],
            ['content' => [new ValidTiptapDocument('message')]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_feedback_profile_accepts_an_uploaded_image(): void
    {
        $doc = $this->imageDoc([
            'src' => '/storage/feedback-images/'.str_repeat('a1B2', 10).'.png',
            'alt' => 'anh-chup-man-hinh',
            'title' => 'anh-chup-man-hinh',
            'width' => 320,
            'height' => null,
        ]);

        $this->assertTrue($this->validate($doc, 'feedback'));
        $this->assertTrue($this->validate($doc, 'message'));
    }

    public function test_image_must_come_from_the_upload_folder(): void
    {
        $name = str_repeat('a1B2', 10);

        foreach ([
            'https://tracker.example.com/pixel.png',
            'data:image/png;base64,iVBORw0KGgo=',
            'javascript:alert(1)',
            '/storage/feedback-images/../../.env',
            "/storage/other/{$name}.png",
            "/storage/feedback-images/{$name}.svg",
        ] as $src) {
            $this->assertFalse($this->validate($this->imageDoc(['src' => $src]), 'feedback'), $src);
        }
    }

    public function test_image_rejects_unknown_attributes(): void
    {
        $doc = $this->imageDoc([
            'src' => '/storage/feedback-images/'.str_repeat('a1B2', 10).'.png',
            'onerror' => 'alert(1)',
        ]);

        $this->assertFalse($this->validate($doc, 'feedback'));
    }

    public function test_image_rejects_an_unsupported_alignment(): void
    {
        $doc = $this->imageDoc([
            'src' => '/storage/feedback-images/'.str_repeat('a', 40).'.png',
            'data-align' => 'justify',
        ]);

        $this->assertFalse($this->validate($doc, 'feedback'));
        $this->assertFalse($this->validate($doc, 'message'));
    }

    /**
     * @param  array<string, mixed>  $doc
     */
    private function validate(array $doc, string $profile): bool
    {
        return Validator::make(
            ['content' => $doc],
            ['content' => [new ValidTiptapDocument($profile)]],
        )->passes();
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function imageDoc(array $attrs): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => $attrs]]];
    }

    /**
     * @return array<string, mixed>
     */
    private function paragraphDoc(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $text],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linkDoc(string $href): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'click',
                            'marks' => [
                                ['type' => 'link', 'attrs' => ['href' => $href]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * Kiểm tra một tài liệu Tiptap (JSON) theo whitelist node/mark/URL mô tả ở
 * mục 2.3 tài liệu thiết kế: docs/design/feedback-mvp-technical-design.md
 */
class ValidTiptapDocument implements ValidationRule
{
    private const int MAX_DEPTH = 20;

    private const array LINK_PROTOCOLS = ['http', 'https', 'mailto'];

    /** @var list<string> */
    private const array IMAGE_ATTRS = ['src', 'alt', 'title', 'width', 'height', 'data-align'];

    /**
     * Khớp đúng đường dẫn mà FeedbackImageController trả về: tên file do
     * Laravel sinh (ngẫu nhiên, chữ-số) trong /storage/feedback-images.
     */
    public const string IMAGE_SRC_PATTERN = '#^/storage/feedback-images/[A-Za-z0-9]{20,64}\.(?:jpe?g|png|gif|webp)$#';

    private const array NODES = ['doc', 'paragraph', 'heading', 'bulletList', 'orderedList', 'listItem', 'blockquote', 'codeBlock', 'hardBreak', 'text', 'image'];

    private const array MARKS = ['bold', 'italic', 'underline', 'strike', 'code', 'link'];

    private const array MAX_BYTES = ['feedback' => 20 * 1024, 'message' => 5 * 1024];

    public function __construct(private readonly string $profile)
    {
        if (! isset(self::MAX_BYTES[$profile])) {
            throw new InvalidArgumentException("Unknown Tiptap validation profile: {$profile}");
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('Nội dung :attribute phải là một tài liệu Tiptap hợp lệ.');

            return;
        }

        $encoded = json_encode($value);

        if ($encoded === false || strlen($encoded) > self::MAX_BYTES[$this->profile]) {
            $fail('Nội dung :attribute vượt quá giới hạn kích thước cho phép.');

            return;
        }

        $error = $this->validateNode($value, 0);

        if ($error !== null) {
            $fail($error);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function validateNode(array $node, int $depth): ?string
    {
        if ($depth > self::MAX_DEPTH) {
            return 'Nội dung lồng quá sâu, vượt mức cho phép.';
        }

        $type = $node['type'] ?? null;

        if (! is_string($type) || ! in_array($type, self::NODES, true)) {
            return 'Nội dung chứa định dạng không được phép.';
        }

        if ($type === 'heading' && ! in_array($node['attrs']['level'] ?? null, [1, 2, 3], true)) {
            return 'Cấp độ heading không hợp lệ (chỉ cho phép 1-3).';
        }

        if ($type === 'image' && ! $this->isAllowedImage($node['attrs'] ?? null)) {
            return 'Ảnh không hợp lệ: chỉ chấp nhận ảnh được tải lên qua trình soạn thảo.';
        }

        foreach ((array) ($node['marks'] ?? []) as $mark) {
            $markType = is_array($mark) ? ($mark['type'] ?? null) : null;

            if (! is_string($markType) || ! in_array($markType, self::MARKS, true)) {
                return 'Nội dung chứa định dạng (mark) không được phép.';
            }

            if ($markType === 'link' && ! $this->isAllowedLink($mark['attrs']['href'] ?? null)) {
                return 'Đường dẫn liên kết không hợp lệ hoặc không an toàn.';
            }
        }

        foreach ((array) ($node['content'] ?? []) as $child) {
            if (! is_array($child)) {
                return 'Cấu trúc nội dung không hợp lệ.';
            }

            if (($error = $this->validateNode($child, $depth + 1)) !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * Chỉ nhận ảnh nằm trong thư mục upload của hệ thống — chặn ảnh từ host
     * ngoài (pixel theo dõi), `data:`/`javascript:` và mọi attr lạ.
     */
    private function isAllowedImage(mixed $attrs): bool
    {
        if (! is_array($attrs) || array_diff(array_keys($attrs), self::IMAGE_ATTRS) !== []) {
            return false;
        }

        $src = $attrs['src'] ?? null;

        if (! is_string($src) || preg_match(self::IMAGE_SRC_PATTERN, $src) !== 1) {
            return false;
        }

        foreach (['alt', 'title'] as $key) {
            $text = $attrs[$key] ?? null;

            if ($text !== null && (! is_string($text) || mb_strlen($text) > 255)) {
                return false;
            }
        }

        foreach (['width', 'height'] as $key) {
            $size = $attrs[$key] ?? null;

            if ($size !== null && (! is_int($size) || $size < 1 || $size > 4000)) {
                return false;
            }
        }

        return in_array($attrs['data-align'] ?? null, [null, 'left', 'center', 'right'], true);
    }

    private function isAllowedLink(mixed $href): bool
    {
        if (! is_string($href) || $href === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        return in_array($scheme, self::LINK_PROTOCOLS, true);
    }
}

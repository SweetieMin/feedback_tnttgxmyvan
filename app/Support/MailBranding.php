<?php

namespace App\Support;

/**
 * Thông tin thương hiệu cố định cho layout email (resources/views/emails/layout.blade.php).
 *
 * Khác với bản tnttgxmyvan_ver2 (nhiều theme preset qua SiteSettings), project này
 * chỉ dùng một màu thương hiệu cố định trùng với --primary trong resources/css/app.css.
 */
class MailBranding
{
    protected const PRIMARY = '#991b1f';

    protected const SOFT = '#fdf2f2';

    protected const BORDER = '#f3d4d5';

    protected const DEEP = '#5c1012';

    /**
     * @return array{
     *     name: string,
     *     logo_url: string,
     *     site_url: string,
     *     site_host: string,
     *     support_email: string,
     *     year: string,
     *     colors: array{soft: string, border: string, primary: string, button: string, deep: string}
     * }
     */
    public function toArray(): array
    {
        $siteUrl = rtrim((string) config('app.url'), '/');

        return [
            'name' => (string) config('app.name'),
            'logo_url' => asset('images/tntt-my-van.png'),
            'site_url' => $siteUrl,
            'site_host' => (string) (parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl),
            'support_email' => (string) config('mail.from.address'),
            'year' => now()->format('Y'),
            'colors' => [
                'soft' => self::SOFT,
                'border' => self::BORDER,
                'primary' => self::PRIMARY,
                'button' => self::PRIMARY,
                'deep' => self::DEEP,
            ],
        ];
    }
}

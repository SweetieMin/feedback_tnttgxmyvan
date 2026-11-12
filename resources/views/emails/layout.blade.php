{{--
    Layout dùng chung cho email hệ thống. Dựng bằng bảng + style inline để hiển thị
    đúng trên Gmail/Outlook (không hỗ trợ flex, biến CSS, SVG). Nhận $brand từ
    App\Support\MailBranding::toArray(); email con điền @section('preheader') và @section('content').
--}}
@php($c = $brand['colors'])
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>@yield('title', $brand['name'])</title>
    <style>
        @media only screen and (max-width: 620px) {
            .mail-body { padding: 26px 22px 22px !important; }
            .mail-outer { padding: 20px 10px 24px !important; }
            .mail-button { display: block !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; -webkit-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">@yield('preheader')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f6f8;">
        <tr>
            <td align="center" class="mail-outer" style="padding:32px 16px 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; font-family:-apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                    <tr>
                        <td align="center" style="padding:0 0 20px;">
                            <a href="{{ $brand['site_url'] }}" style="text-decoration:none;">
                                <img src="{{ $brand['logo_url'] }}" width="72" height="72" alt="{{ $brand['name'] }}" style="display:block; width:72px; height:72px; border:0; margin:0 auto; border-radius:50%;">
                            </a>
                            <div style="font-size:18px; font-weight:700; color:{{ $c['deep'] }}; margin-top:10px;">{{ $brand['name'] }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#ffffff; border-radius:16px; border-top:6px solid {{ $c['primary'] }};">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="mail-body" style="padding:32px 40px 28px; font-size:15px; line-height:1.6; color:#334155;">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 8px 0; font-size:12px; line-height:1.7; color:#94a3b8;">
                            <div style="margin-bottom:8px; font-size:13px; font-weight:600;">
                                <a href="{{ $brand['site_url'] }}" style="color:{{ $c['primary'] }}; text-decoration:none;">{{ $brand['site_host'] }}</a>
                            </div>
                            Đây là email tự động, vui lòng không trả lời.<br>
                            @if ($brand['support_email'] !== '')
                                Cần hỗ trợ? Liên hệ
                                <a href="mailto:{{ $brand['support_email'] }}" style="color:{{ $c['primary'] }}; text-decoration:none;">{{ $brand['support_email'] }}</a><br>
                            @endif
                            &copy; {{ $brand['year'] }} {{ $brand['name'] }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

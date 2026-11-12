@extends('emails.layout')

@php($c = $brand['colors'])

@section('title', 'Đặt lại mật khẩu')
@section('preheader', "Liên kết có hiệu lực trong {$minutes} phút.")

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding:0 0 16px;">
                <div style="width:56px; height:56px; line-height:56px; border-radius:28px; background-color:{{ $c['soft'] }}; border:1px solid {{ $c['border'] }}; font-size:26px; text-align:center;">&#128274;</div>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:0 0 22px; font-size:22px; font-weight:700; color:#0f172a;">Đặt lại mật khẩu</td>
        </tr>
    </table>

    <p style="margin:0 0 14px;">Xin chào <strong>{{ $userName }}</strong>,</p>
    <p style="margin:0 0 14px;">
        Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn trên hệ thống {{ $brand['name'] }}.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 22px;">
        <tr>
            <td align="center">
                <a href="{{ $url }}" class="mail-button" target="_blank" rel="noopener"
                    style="display:inline-block; background-color:{{ $c['button'] }}; color:#ffffff; text-decoration:none; font-weight:600; font-size:16px; padding:14px 34px; border-radius:10px;">Đặt lại mật khẩu</a>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px;">
        <tr>
            <td style="background-color:{{ $c['soft'] }}; border:1px solid {{ $c['border'] }}; border-radius:10px; padding:12px 14px; font-size:14px; line-height:1.55; color:{{ $c['deep'] }};">
                &#9201;&nbsp; Liên kết có hiệu lực trong <strong>{{ $minutes }} phút</strong> (đến <strong>{{ $expiresAt }}</strong>) và chỉ dùng được <strong>một lần</strong>.
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;">
        <tr>
            <td style="background-color:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:12px 14px; font-size:14px; line-height:1.55; color:#92400e;">
                &#128737;&nbsp; <strong>Không phải bạn yêu cầu?</strong>
                Hãy bỏ qua email này, mật khẩu của bạn sẽ không thay đổi. Không chuyển tiếp email này hay chia sẻ liên kết cho bất kỳ ai.
            </td>
        </tr>
    </table>

    <p style="margin:18px 0 0;">Trân trọng,<br><strong>{{ $brand['name'] }}</strong></p>

    <div style="height:1px; background-color:#e2e8f0; margin:22px 0 16px; line-height:1px; font-size:1px;">&nbsp;</div>

    <p style="margin:0; font-size:13px; line-height:1.55; color:#64748b;">
        Nếu nút <strong>Đặt lại mật khẩu</strong> không hoạt động, hãy sao chép liên kết bên dưới và dán vào trình duyệt:
    </p>
    <div style="margin-top:8px; padding:10px 12px; background-color:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; font-family:Menlo, Consolas, monospace; font-size:12px; line-height:1.5; word-break:break-all;">
        <a href="{{ $url }}" target="_blank" rel="noopener" style="color:{{ $c['primary'] }}; text-decoration:none;">{{ $url }}</a>
    </div>
@endsection

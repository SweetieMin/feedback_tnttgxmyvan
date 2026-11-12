Đặt lại mật khẩu - {{ $brand['name'] }}

Xin chào {{ $userName }},

Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn trên hệ thống {{ $brand['name'] }}.

Đặt lại mật khẩu: {{ $url }}

Liên kết có hiệu lực trong {{ $minutes }} phút (đến {{ $expiresAt }}) và chỉ dùng được một lần.

Không phải bạn yêu cầu? Hãy bỏ qua email này, mật khẩu của bạn sẽ không thay đổi. Không chuyển tiếp email này hay chia sẻ liên kết cho bất kỳ ai.

Trân trọng,
{{ $brand['name'] }}

--
Đây là email tự động, vui lòng không trả lời.
@if ($brand['support_email'] !== '')
Cần hỗ trợ? Liên hệ {{ $brand['support_email'] }}
@endif
{{ $brand['site_url'] }}

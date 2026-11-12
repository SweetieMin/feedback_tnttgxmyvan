Xác thực email - {{ $brand['name'] }}

Xin chào {{ $userName }},

Vui lòng mở liên kết bên dưới để xác thực địa chỉ email cho tài khoản của bạn trên hệ thống {{ $brand['name'] }}.

Xác thực email: {{ $url }}

Không phải bạn tạo tài khoản này? Hãy bỏ qua email này, không cần thực hiện thêm thao tác nào.

Trân trọng,
{{ $brand['name'] }}

--
Đây là email tự động, vui lòng không trả lời.
@if ($brand['support_email'] !== '')
Cần hỗ trợ? Liên hệ {{ $brand['support_email'] }}
@endif
{{ $brand['site_url'] }}

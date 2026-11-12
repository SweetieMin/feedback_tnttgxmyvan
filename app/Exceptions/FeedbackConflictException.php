<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Xung đột nghiệp vụ khi đổi trạng thái/người phụ trách góp ý: trạng thái đã
 * thay đổi bởi người khác, hoặc chuyển tiếp không hợp lệ (mục 3.1, 3.3).
 */
class FeedbackConflictException extends RuntimeException
{
    public static function alreadyAssigned(): self
    {
        return new self('Góp ý vừa được người khác nhận xử lý, vui lòng tải lại trang.');
    }

    public static function invalidTransition(): self
    {
        return new self('Trạng thái hiện tại không cho phép chuyển đổi này, vui lòng tải lại trang.');
    }

    public static function notReopenable(): self
    {
        return new self('Góp ý hiện không ở trạng thái có thể mở lại, vui lòng tải lại trang.');
    }

    public static function alreadyClosedOrResolved(): self
    {
        return new self('Góp ý đã đóng hoặc đã giải quyết, không thể đổi người phụ trách.');
    }

    public static function missingPublicReply(): self
    {
        return new self('Cần có ít nhất một trả lời công khai trước khi đánh dấu đã giải quyết.');
    }
}

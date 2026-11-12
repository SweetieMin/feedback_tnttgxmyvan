<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\FeedbackStatus;
use App\Enums\MessageVisibility;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\FeedbackActivity;
use App\Models\FeedbackMessage;
use App\Models\FeedbackNotification;
use App\Models\User;
use App\Services\FeedbackStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dữ liệu demo cho đủ 3 vai trò và mọi trạng thái, trải đều ~8 tuần để
 * dashboard có xu hướng thật. Mọi chuyển trạng thái đi qua
 * FeedbackStatusService tại thời điểm giả lập (Carbon::setTestNow), nên lịch
 * sử, thông báo và các mốc first_response_at/resolved_at/closed_at luôn khớp
 * nhau như khi người dùng thao tác thật. Mật khẩu mọi tài khoản: "password".
 */
class FeedbackDemoSeeder extends Seeder
{
    private FeedbackStatusService $status;

    private CarbonImmutable $now;

    private User $admin;

    private User $handlerA;

    private User $handlerB;

    /** @var array<string, User> */
    private array $employees = [];

    /** @var array<string, Category> */
    private array $categories = [];

    public function run(FeedbackStatusService $status): void
    {
        $this->status = $status;
        $this->now = CarbonImmutable::now()->startOfHour();

        try {
            $this->at(60);
            $this->seedUsers();
            $this->seedCategories();
            $this->seedFeedbacks();
        } finally {
            Carbon::setTestNow();
        }

        // Thông báo cũ hơn một tuần coi như đã đọc, để chuông chỉ báo việc mới.
        FeedbackNotification::query()
            ->where('created_at', '<', $this->now->subDays(7))
            ->update(['read_at' => $this->now->subDays(7)]);
    }

    private function seedUsers(): void
    {
        $this->admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@tnttgxmyvan.org',
            'role' => UserRole::Admin,
        ]);

        $this->handlerA = User::factory()->create([
            'name' => 'Nguyễn Khắc Huấn',
            'email' => 'nguyenkhachuan1997@gmail.com',
            'role' => UserRole::Handler,
        ]);

        $this->handlerB = User::factory()->create([
            'name' => 'Nguyễn Minh Quân',
            'email' => 'quan.nguyen@gmail.com',
            'role' => UserRole::Handler,
        ]);

        foreach ([
            'mai' => ['Trần Thị Mai', 'mai.tran@tnttgxmyvan.org'],
            'binh' => ['Lê Văn Bình', 'binh.le@tnttgxmyvan.org'],
            'hoa' => ['Phạm Thu Hoa', 'hoa.pham@tnttgxmyvan.org'],
        ] as $key => [$name, $email]) {
            $this->employees[$key] = User::factory()->create(['name' => $name, 'email' => $email]);
        }
    }

    private function seedCategories(): void
    {
        foreach (['Môi trường làm việc', 'Lương & phúc lợi', 'Quy trình nội bộ', 'Công nghệ & công cụ'] as $name) {
            $this->categories[$name] = Category::factory()->create(['name' => $name, 'slug' => Str::slug($name)]);
        }

        Category::factory()->inactive()->create([
            'name' => 'Danh mục đã ngừng dùng',
            'slug' => 'danh-muc-da-ngung-dung',
        ]);
    }

    private function seedFeedbacks(): void
    {
        [$mai, $binh, $hoa] = [$this->employees['mai'], $this->employees['binh'], $this->employees['hoa']];
        [$a, $b] = [$this->handlerA, $this->handlerB];

        // Đã giải quyết, rải ở các tuần khác nhau.
        $f = $this->submit($mai, 'Công nghệ & công cụ', 52, 'Máy in tầng 2 thường xuyên kẹt giấy',
            'Máy in tầng 2 kẹt giấy gần như mỗi ngày, nhất là khi in hai mặt.', 'Nhiều người phải xuống tầng 1 để in tài liệu họp.');
        $this->selfAssign($f, $a, 51, 20);
        $this->changeStatus($f, $a, FeedbackStatus::InProgress, 51);
        $this->reply($f, $a, 'Đã liên hệ nhà cung cấp thay bộ kéo giấy, dự kiến xong trong tuần.', 50);
        $this->changeStatus($f, $a, FeedbackStatus::Resolved, 49);

        $f = $this->submit($binh, 'Công nghệ & công cụ', 38, 'Wifi khu B chập chờn buổi chiều',
            'Từ khoảng 14h, wifi khu B thường mất kết nối vài phút một lần.');
        $this->selfAssign($f, $b, 37, 18);
        $this->changeStatus($f, $b, FeedbackStatus::InProgress, 37, 10);
        $this->reply($f, $b, 'Router khu B đang quá tải, cần thêm một access point.', 36, internal: true);
        $this->reply($f, $b, 'Đã lắp thêm access point cho khu B, anh/chị kiểm tra giúp nhé.', 35);
        $this->changeStatus($f, $b, FeedbackStatus::Resolved, 33);

        // Giải quyết, mở lại rồi giải quyết lại ở tuần khác (tiêu chí 6.2).
        $f = $this->submit($hoa, 'Công nghệ & công cụ', 24, 'Phần mềm chấm công hay bị lỗi đăng nhập',
            'Sáng nào cũng phải đăng nhập lại 2–3 lần mới chấm công được.');
        $this->selfAssign($f, $a, 23, 20);
        $this->changeStatus($f, $a, FeedbackStatus::InProgress, 23, 18);
        $this->reply($f, $a, 'Đã cập nhật phiên bản mới của phần mềm chấm công.', 22);
        $this->changeStatus($f, $a, FeedbackStatus::Resolved, 20);
        $this->reopen($f, $hoa, 'Hôm nay vẫn bị lỗi đăng nhập như cũ.', 13);
        $this->reply($f, $a, 'Lỗi do phiên đăng nhập hết hạn quá sớm, đã chỉnh lại cấu hình.', 12);
        $this->changeStatus($f, $a, FeedbackStatus::Resolved, 10);

        $f = $this->submit($mai, 'Môi trường làm việc', 9, 'Nước uống ở pantry hết thường xuyên',
            'Bình nước ở pantry thường hết từ giữa buổi chiều.');
        $this->selfAssign($f, $b, 8, 22);
        $this->changeStatus($f, $b, FeedbackStatus::InProgress, 8, 20);
        $this->reply($f, $b, 'Đã tăng số bình giao mỗi ngày từ 4 lên 6.', 8);
        $this->changeStatus($f, $b, FeedbackStatus::Resolved, 6);

        // Đã giải quyết nhưng người gửi mở lại — đang xử lý tiếp.
        $f = $this->submit($binh, 'Lương & phúc lợi', 30, 'Phụ cấp ăn trưa chưa điều chỉnh theo giá',
            'Mức phụ cấp ăn trưa giữ nguyên hai năm nay trong khi giá cơm đã tăng.');
        $this->selfAssign($f, $a, 29, 20);
        $this->changeStatus($f, $a, FeedbackStatus::InProgress, 28);
        $this->reply($f, $a, 'Phòng nhân sự đã duyệt tăng phụ cấp từ tháng sau.', 27);
        $this->changeStatus($f, $a, FeedbackStatus::Resolved, 26);
        $this->reopen($f, $binh, 'Bảng lương tháng này vẫn chưa thấy áp dụng mức mới.', 4);

        // Đang xử lý, có đổi người phụ trách.
        $f = $this->submit($hoa, 'Quy trình nội bộ', 15, 'Quy trình xin nghỉ phép còn nhiều bước',
            'Xin nghỉ một ngày cần ba cấp ký duyệt, nhiều khi quá ngày nghỉ mới xong.');
        $this->selfAssign($f, $a, 14, 20);
        $this->reassign($f, $b, 12);
        $this->changeStatus($f, $b, FeedbackStatus::InProgress, 11);
        $this->reply($f, $b, 'Đã gửi đề xuất rút gọn còn một cấp duyệt cho nghỉ dưới 2 ngày.', 10, internal: true);
        $this->reply($f, $b, 'Đề xuất rút gọn quy trình đang chờ ban giám đốc duyệt.', 3);

        $f = $this->submit($mai, 'Quy trình nội bộ', 7, 'Thủ tục thanh toán công tác phí chậm',
            'Hồ sơ công tác phí nộp từ đầu tháng vẫn chưa được thanh toán.');
        $this->selfAssign($f, $b, 6, 20);
        $this->changeStatus($f, $b, FeedbackStatus::InProgress, 5);

        // Đang xem xét.
        $f = $this->submit($binh, 'Quy trình nội bộ', 5, 'Đề nghị tổ chức đào tạo kỹ năng Excel',
            'Nhiều bạn mới vào chưa dùng thành thạo Excel, ảnh hưởng tiến độ báo cáo.');
        $this->selfAssign($f, $a, 4, 20);

        $f = $this->submit($hoa, 'Môi trường làm việc', 3, 'Nhà xe thiếu mái che khi trời mưa',
            'Khu để xe máy phía sau không có mái che, xe ướt hết khi trời mưa.');
        $this->selfAssign($f, $a, 2, 20);
        $this->reply($f, $hoa, 'Bổ sung: khu này có khoảng 40 xe mỗi ngày.', 1);

        // Mới, chưa có người phụ trách.
        $this->submit($mai, 'Môi trường làm việc', 11, 'Máy lạnh phòng họp tầng 3 không mát',
            'Máy lạnh phòng họp tầng 3 chạy cả buổi vẫn nóng, khó họp lâu.');
        $this->submit($binh, 'Môi trường làm việc', 2, 'Đề xuất thêm một ngày làm việc từ xa mỗi tuần',
            'Phần lớn công việc của nhóm có thể làm từ xa, đề xuất thử nghiệm một ngày mỗi tuần.');
        $this->submitAt($hoa, 'Công nghệ & công cụ', 0, 3, 'Ứng dụng nội bộ không chạy trên điện thoại',
            ['Mở ứng dụng nội bộ trên điện thoại chỉ thấy trang trắng.']);

        // Đã đóng.
        $f = $this->submit($mai, 'Môi trường làm việc', 20, 'Cần thêm ghế ở khu vực tiếp khách',
            'Khu tiếp khách chỉ có 4 ghế, khách đến đông phải đứng chờ.');
        $this->changeStatus($f, $this->admin, FeedbackStatus::Closed, 19, reason: 'Trùng với góp ý về cơ sở vật chất đã xử lý trước đó.');

        $f = $this->submit($binh, 'Môi trường làm việc', 17, 'Đề xuất góc thư viện sách chung',
            'Đề xuất dành một góc nhỏ để mọi người chia sẻ sách đọc.');
        $this->selfAssign($f, $b, 16, 20);
        $this->reply($f, $b, 'Cảm ơn đề xuất, nội dung này thuộc phạm vi của Công đoàn.', 16);
        $this->changeStatus($f, $b, FeedbackStatus::Closed, 15, reason: 'Ngoài phạm vi xử lý của bộ phận, đã chuyển sang Công đoàn.');
    }

    private function at(int $daysAgo, int $hoursAgo = 0): void
    {
        Carbon::setTestNow($this->now->subDays($daysAgo)->subHours($hoursAgo));
    }

    /**
     * @return array<string, mixed>
     */
    private function doc(string ...$paragraphs): array
    {
        return [
            'type' => 'doc',
            'content' => array_map(fn (string $text) => [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ], array_values($paragraphs)),
        ];
    }

    private function submit(User $owner, string $category, int $daysAgo, string $title, string ...$paragraphs): Feedback
    {
        return $this->submitAt($owner, $category, $daysAgo, 0, $title, array_values($paragraphs));
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function submitAt(User $owner, string $category, int $daysAgo, int $hoursAgo, string $title, array $paragraphs): Feedback
    {
        $this->at($daysAgo, $hoursAgo);
        $content = $this->doc(...$paragraphs);

        $feedback = Feedback::create([
            'user_id' => $owner->id,
            'category_id' => $this->categories[$category]->id,
            'title' => $title,
            'content' => $content,
            'excerpt' => Feedback::excerptFromContent($content),
            'status' => FeedbackStatus::New,
        ]);

        FeedbackActivity::create([
            'feedback_id' => $feedback->id,
            'actor_id' => $owner->id,
            'type' => ActivityType::StatusChange,
            'from_value' => null,
            'to_value' => FeedbackStatus::New->value,
        ]);

        return $feedback;
    }

    private function selfAssign(Feedback $feedback, User $handler, int $daysAgo, int $hoursAgo = 0): void
    {
        $this->at($daysAgo, $hoursAgo);
        $this->status->selfAssign($feedback, $handler);
    }

    private function changeStatus(Feedback $feedback, User $actor, FeedbackStatus $to, int $daysAgo, int $hoursAgo = 0, ?string $reason = null): void
    {
        $this->at($daysAgo, $hoursAgo);
        $this->status->changeStatus($feedback, $actor, $to, $reason);
    }

    private function reopen(Feedback $feedback, User $actor, string $reason, int $daysAgo): void
    {
        $this->at($daysAgo);
        $this->status->reopen($feedback, $actor, $reason);
    }

    private function reassign(Feedback $feedback, User $newHandler, int $daysAgo): void
    {
        $this->at($daysAgo);
        $this->status->reassign($feedback, $this->admin, $newHandler);
    }

    /**
     * Cùng hiệu ứng với FeedbackMessageController@store: trả lời công khai của
     * người khác chủ góp ý đặt first_response_at (lần đầu) và báo người gửi.
     */
    private function reply(Feedback $feedback, User $author, string $text, int $daysAgo, bool $internal = false): void
    {
        $this->at($daysAgo);
        $feedback->refresh();

        FeedbackMessage::create([
            'feedback_id' => $feedback->id,
            'author_id' => $author->id,
            'body' => $this->doc($text),
            'visibility' => $internal ? MessageVisibility::Internal : MessageVisibility::Public,
        ]);

        if ($internal || $author->id === $feedback->user_id) {
            return;
        }

        if ($feedback->first_response_at === null) {
            $feedback->forceFill(['first_response_at' => now()])->save();
        }

        FeedbackNotification::create([
            'user_id' => $feedback->user_id,
            'feedback_id' => $feedback->id,
            'type' => 'new_reply',
            'data' => ['title' => $feedback->title],
        ]);
    }
}

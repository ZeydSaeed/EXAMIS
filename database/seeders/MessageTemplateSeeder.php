<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Starting messages. Runs only on an empty table, so anything administrators
 * change or delete stays that way.
 */
class MessageTemplateSeeder extends Seeder
{
    private const HEADER = <<<'TEXT'
        عزيزي الطالب {اسم_الطالب}
        رقم الطالب: {رقم_الطالب}
        الفرع: {الفرع}
        الاختصاص: {الاختصاص}
        TEXT;

    public function run(): void
    {
        if (MessageTemplate::query()->exists()) {
            return;
        }

        $templates = [
            ['الطلب قيد التدقيق', ApplicationStatus::UnderReview, 'نودّ إعلامك بأن طلبك للمشاركة في الامتحانات الخارجية قد ورد إلى اللجنة المختصة، وهو الآن قيد التدقيق والمراجعة. وستُبلَّغ بنتيجة التدقيق حال الانتهاء منه.'],
            ['قبول الطلب', ApplicationStatus::Accepted, 'يسرنا إعلامك بقبول طلبك للامتحانات الخارجية.'],
            ['رفض الطلب', ApplicationStatus::Rejected, "نأسف لإعلامك برفض طلبك للامتحانات الخارجية.\nالسبب: {فراغ}"],
            ['وجود نقص بالمعاملة', ApplicationStatus::RequiresCorrection, "توجد نواقص في معاملتك، يرجى استكمالها:\n1. {فراغ}\n2. {فراغ}\n3. {فراغ}"],
            ['القبول النهائي', ApplicationStatus::FeesPaid, "يسرّنا إعلامك بأنه قد تمّ تأكيد استيفاء الرسوم المقررة، وبذلك صدر قبولك قبولاً نهائياً في الامتحانات الخارجية، وأصبحت مؤهلاً لأداء الامتحانات التمهيدية.\nمع تمنياتنا لك بالتوفيق والنجاح."],
        ];

        foreach ($templates as [$title, $status, $body]) {
            MessageTemplate::query()->create(['title' => $title, 'status' => $status, 'body' => self::HEADER."\n\n".$body]);
        }
    }
}

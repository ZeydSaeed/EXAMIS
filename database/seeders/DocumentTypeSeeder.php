<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Document types from the authority's list of required documents
 * (idempotent). Which ones a period requires is configured per exam period
 * by administrators. `school_certificate` is no longer on the list; it is
 * kept for applications that already reference it.
 */
class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $images = ['image/jpeg', 'image/png'];
        $scans = ['image/jpeg', 'image/png', 'application/pdf'];
        $onePage = 'ارفع صورة ملونة واضحة للوجهين في صفحة واحدة.';
        $onePageEn = 'Upload a clear colour copy of both sides on one page.';

        $types = [
            [
                'code' => 'participation_request',
                'name_ar' => 'طلب الاشتراك في الامتحان الخارجي',
                'name_en' => 'External examination participation request',
                'description_ar' => 'صورة واضحة لطلب الاشتراك الموقّع.',
                'description_en' => 'A clear copy of the signed participation request.',
                'allowed_mime_types' => $scans,
                'max_size_kb' => 5120,
                'is_photo' => false,
            ],
            [
                'code' => 'national_id',
                'name_ar' => 'هوية الأحوال المدنية أو البطاقة الموحدة للطالب',
                'name_en' => "Student's civil ID or unified national card",
                'description_ar' => $onePage,
                'description_en' => $onePageEn,
                'allowed_mime_types' => $scans,
                'max_size_kb' => 5120,
                'is_photo' => false,
            ],
            [
                'code' => 'father_id',
                'name_ar' => 'هوية الأحوال المدنية أو البطاقة الموحدة لوالد الطالب',
                'name_en' => "Father's civil ID or unified national card",
                'description_ar' => $onePage.' في حال وفاة الأب ارفع شهادة الوفاة أو صورة القيد.',
                'description_en' => $onePageEn.' If the father has died, upload the death certificate or a civil registry record.',
                'allowed_mime_types' => $scans,
                'max_size_kb' => 5120,
                'is_photo' => false,
            ],
            [
                'code' => 'mother_id',
                'name_ar' => 'هوية الأحوال المدنية أو البطاقة الموحدة لأم الطالب',
                'name_en' => "Mother's civil ID or unified national card",
                'description_ar' => $onePage.' في حال وفاة الأم ارفع شهادة الوفاة أو صورة القيد.',
                'description_en' => $onePageEn.' If the mother has died, upload the death certificate or a civil registry record.',
                'allowed_mime_types' => $scans,
                'max_size_kb' => 5120,
                'is_photo' => false,
            ],
            [
                'code' => 'residence_card',
                'name_ar' => 'بطاقة السكن (كربلاء حصراً)',
                'name_en' => 'Residence card (Karbala only)',
                'description_ar' => $onePage.' للنازحين الذين ليس لديهم بطاقة سكن في كربلاء: كتاب من دائرة الهجرة والمهجرين مع تأييد سكن من المختار موقّع من شاهدين مع أرقام هواتفهم ومختوم من القائمقامية.',
                'description_en' => $onePageEn.' Displaced students without a Karbala residence card: a letter from the Migration and Displacement office with a residence confirmation from the mukhtar, signed by two witnesses with their phone numbers and stamped by the district office.',
                'allowed_mime_types' => $scans,
                'max_size_kb' => 5120,
                'is_photo' => false,
            ],
            [
                'code' => 'personal_photo',
                'name_ar' => 'الصورة الشخصية',
                'name_en' => 'Personal photo',
                'description_ar' => 'صورة أمامية بخلفية بيضاء قياس 3.5×4.5، بدون غطاء رأس للذكور.',
                'description_en' => 'Front-facing photo on a white background, 3.5×4.5 cm, no head cover for males.',
                'allowed_mime_types' => $images,
                'max_size_kb' => 2048,
                'is_photo' => true,
            ],
            [
                'code' => 'school_certificate',
                'name_ar' => 'وثيقة المدرسة أو الشهادة السابقة',
                'name_en' => 'School document or previous certificate',
                'description_ar' => 'وثيقة مصدقة تثبت المرحلة الدراسية السابقة.',
                'description_en' => 'A certified document proving the previous stage of study.',
                'allowed_mime_types' => $scans,
                'max_size_kb' => 5120,
                'is_photo' => false,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::query()->updateOrCreate(['code' => $type['code']], $type);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Baseline document types (idempotent). Which ones a period requires is
 * configured per exam period by administrators.
 */
class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'personal_photo',
                'name_ar' => 'الصورة الشخصية',
                'name_en' => 'Personal photo',
                'description_ar' => 'صورة حديثة بخلفية بيضاء، واضحة الملامح، بصيغة JPG أو PNG.',
                'description_en' => 'A recent photo on a white background, JPG or PNG.',
                'allowed_mime_types' => ['image/jpeg', 'image/png'],
                'max_size_kb' => 2048,
                'is_photo' => true,
            ],
            [
                'code' => 'national_id',
                'name_ar' => 'البطاقة الوطنية أو هوية الأحوال المدنية',
                'name_en' => 'National ID card',
                'description_ar' => 'صورة واضحة للوجهين في ملف PDF واحد أو صورة.',
                'description_en' => 'A clear copy of both sides as one PDF or image.',
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'],
                'max_size_kb' => 5120,
                'is_photo' => false,
            ],
            [
                'code' => 'school_certificate',
                'name_ar' => 'وثيقة المدرسة أو الشهادة السابقة',
                'name_en' => 'School document or previous certificate',
                'description_ar' => 'وثيقة مصدقة تثبت المرحلة الدراسية السابقة.',
                'description_en' => 'A certified document proving the previous stage of study.',
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'],
                'max_size_kb' => 5120,
                'is_photo' => false,
            ],
            [
                'code' => 'residence_card',
                'name_ar' => 'بطاقة السكن',
                'name_en' => 'Residence card',
                'description_ar' => 'اختيارية ما لم تطلبها التعليمات.',
                'description_en' => 'Optional unless required by the period instructions.',
                'allowed_mime_types' => ['image/jpeg', 'image/png', 'application/pdf'],
                'max_size_kb' => 5120,
                'is_photo' => false,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::query()->updateOrCreate(['code' => $type['code']], $type);
        }
    }
}

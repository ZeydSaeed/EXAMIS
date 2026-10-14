<?php

namespace Database\Seeders;

use App\Enums\ReferenceType;
use App\Models\ReferenceItem;
use App\Services\ReferenceData;
use Illuminate\Database\Seeder;

/**
 * Baseline lookup lists. SAMPLE DATA: the examination authority must confirm
 * the official governorate, branch and category lists before go-live.
 * Idempotent: safe to run on every deployment.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(ReferenceData $referenceData): void
    {
        $data = [
            ReferenceType::Governorate->value => [
                ['BGD', 'بغداد', 'Baghdad'], ['BSR', 'البصرة', 'Basra'], ['NIN', 'نينوى', 'Nineveh'],
                ['ERB', 'أربيل', 'Erbil'], ['NJF', 'النجف', 'Najaf'], ['KRB', 'كربلاء', 'Karbala'],
                ['BBL', 'بابل', 'Babil'], ['DIY', 'ديالى', 'Diyala'], ['ANB', 'الأنبار', 'Anbar'],
                ['DHQ', 'ذي قار', 'Dhi Qar'], ['QAD', 'القادسية', 'Al-Qadisiyah'], ['MUT', 'المثنى', 'Muthanna'],
                ['MYS', 'ميسان', 'Maysan'], ['WAS', 'واسط', 'Wasit'], ['SAL', 'صلاح الدين', 'Salah al-Din'],
                ['KRK', 'كركوك', 'Kirkuk'], ['SUL', 'السليمانية', 'Sulaymaniyah'], ['DUH', 'دهوك', 'Duhok'],
            ],
            ReferenceType::Branch->value => [
                ['SCI', 'العلمي', 'Scientific'], ['LIT', 'الأدبي', 'Literary'],
                ['APP', 'التطبيقي', 'Applied'], ['ISL', 'الإسلامي', 'Islamic'],
            ],
            ReferenceType::Category->value => [
                ['EXTERNAL', 'خارجي', 'External applicant'],
                ['REPEATER', 'معيد', 'Repeating applicant'],
                ['IMPROVEMENT', 'تحسين معدل', 'Grade improvement'],
            ],
        ];

        foreach ($data as $type => $items) {
            foreach ($items as $index => [$code, $nameAr, $nameEn]) {
                ReferenceItem::query()->updateOrCreate(
                    ['type' => $type, 'code' => $code],
                    ['name_ar' => $nameAr, 'name_en' => $nameEn, 'sort_order' => $index, 'is_active' => true],
                );
            }

            $referenceData->forget(ReferenceType::from($type));
        }
    }
}

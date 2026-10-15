<?php

namespace Database\Seeders;

use App\Enums\ReferenceType;
use App\Models\ReferenceItem;
use App\Services\ReferenceData;
use Illuminate\Database\Seeder;

/**
 * Baseline lookup lists. Branches and specializations follow the authority's
 * table of branches open for the vocational external examinations; the
 * Category type holds the specializations, each under its branch.
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
            // Branches open for the vocational external examinations.
            ReferenceType::Branch->value => [
                ['COM', 'التجاري', 'Commercial'],
                ['APA', 'الفنون التطبيقية', 'Applied arts'],
                ['AGR', 'الزراعي', 'Agricultural'],
                ['CIT', 'الحاسوب وتقنية المعلومات', 'Computing and IT'],
                ['IND', 'الصناعي', 'Industrial'],
            ],
            // Specializations (الاختصاص العام); the fourth value is the branch.
            ReferenceType::Category->value => [
                ['COM_ADM', 'الإدارة', 'Administration', 'COM'],
                ['COM_ACC', 'المحاسبة', 'Accounting', 'COM'],
                ['APA_HOM', 'فن التربية الأسرية', 'Home economics', 'APA'],
                ['APA_DEC', 'فن الديكور', 'Interior decoration', 'APA'],
                ['AGR_GEN', 'زراعي عام', 'General agriculture', 'AGR'],
                ['CIT_HW', 'تجميع وصيانة الحاسوب', 'Computer assembly and maintenance', 'CIT'],
                ['IND_ELC', 'كهرباء', 'Electrical', 'IND'],
                ['IND_ECT', 'الإلكترونيك والسيطرة', 'Electronics and control', 'IND'],
                ['IND_MEC', 'الميكانيك', 'Mechanics', 'IND'],
                ['IND_CON', 'البناء', 'Construction', 'IND'],
                ['IND_PET', 'صناعات بتروكيمياوية', 'Petrochemical industries', 'IND'],
                ['IND_HVAC', 'تكييف الهواء والتثليج', 'Air conditioning and refrigeration', 'IND'],
            ],
        ];

        foreach ($data as $type => $items) {
            foreach ($items as $index => $item) {
                [$code, $nameAr, $nameEn] = $item;

                ReferenceItem::query()->updateOrCreate(
                    ['type' => $type, 'code' => $code],
                    ['parent_code' => $item[3] ?? null, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'sort_order' => $index, 'is_active' => true],
                );
            }

            // Codes no longer offered stay for existing applications, hidden from new ones.
            ReferenceItem::query()
                ->where('type', $type)
                ->whereNotIn('code', array_column($items, 0))
                ->update(['is_active' => false]);

            $referenceData->forget(ReferenceType::from($type));
        }
    }
}

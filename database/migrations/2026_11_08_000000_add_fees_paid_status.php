<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fees paid (final acceptance) follows an acceptance. The status column is an
 * enum, so its CHECK constraint learns the new value, and the two statuses
 * that gained a message get a starting template unless one exists.
 */
return new class extends Migration
{
    private const OLD = "'draft', 'submitted', 'under_review', 'requires_correction', 'resubmitted', 'accepted', 'rejected', 'withdrawn'";

    private const HEADER = "عزيزي الطالب {اسم_الطالب}\nرقم الطالب: {رقم_الطالب}\nالفرع: {الفرع}\nالاختصاص: {الاختصاص}";

    public function up(): void
    {
        $this->allow(self::OLD.", 'fees_paid'");

        $templates = [
            'under_review' => ['الطلب قيد التدقيق', 'نودّ إعلامك بأن طلبك للمشاركة في الامتحانات الخارجية قد ورد إلى اللجنة المختصة، وهو الآن قيد التدقيق والمراجعة. وستُبلَّغ بنتيجة التدقيق حال الانتهاء منه.'],
            'fees_paid' => ['القبول النهائي', "يسرّنا إعلامك بأنه قد تمّ تأكيد استيفاء الرسوم المقررة، وبذلك صدر قبولك قبولاً نهائياً في الامتحانات الخارجية، وأصبحت مؤهلاً لأداء الامتحانات التمهيدية.\nمع تمنياتنا لك بالتوفيق والنجاح."],
        ];

        // A fresh install gets every message from MessageTemplateSeeder.
        if (! DB::table('message_templates')->exists()) {
            return;
        }

        foreach ($templates as $status => [$title, $body]) {
            if (DB::table('message_templates')->where('status', $status)->exists()) {
                continue;
            }

            DB::table('message_templates')->insert([
                'title' => $title,
                'status' => $status,
                'body' => self::HEADER."\n\n".$body,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Applications already at fees paid go back to accepted first.
     */
    public function down(): void
    {
        DB::table('applications')->where('status', 'fees_paid')->update(['status' => 'accepted']);
        DB::table('message_templates')->where('status', 'fees_paid')->delete();

        $this->allow(self::OLD);
    }

    private function allow(string $values): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $names = DB::table('pg_constraint')
                ->where('conrelid', DB::raw("'applications'::regclass"))
                ->where('contype', 'c')
                ->whereRaw("pg_get_constraintdef(oid) LIKE '%withdrawn%'")
                ->pluck('conname');

            foreach ($names as $name) {
                DB::statement("ALTER TABLE applications DROP CONSTRAINT \"{$name}\"");
            }

            DB::statement("ALTER TABLE applications ADD CONSTRAINT applications_status_check CHECK (status IN ({$values}))");

            return;
        }

        if ($driver === 'sqlite') {
            // SQLite cannot alter a CHECK constraint; the stored table SQL is
            // rewritten in place, which keeps the data, indexes and keys.
            $sql = (string) DB::table('sqlite_master')->where('type', 'table')->where('name', 'applications')->value('sql');
            $rewritten = (string) preg_replace('/("status" varchar check \("status" in \()[^)]*\)/', '$1'.$values.')', $sql);

            DB::statement('PRAGMA writable_schema = ON');
            DB::table('sqlite_master')->where('type', 'table')->where('name', 'applications')->update(['sql' => $rewritten]);
            $version = (int) DB::selectOne('PRAGMA schema_version')->schema_version;
            DB::statement('PRAGMA schema_version = '.($version + 1));
            DB::statement('PRAGMA writable_schema = OFF');
        }
    }
};

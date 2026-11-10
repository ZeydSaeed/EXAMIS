<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The form asks for each part of the student's and the mother's name in its
 * own field, and no longer asks for the surname. `full_name_ar` and
 * `mother_name_ar` stay, composed from the parts on every save (see
 * Application::booted), so staff search, lists and reports keep working.
 * Existing names are split into the new parts; the surname column is dropped
 * (restore from the backup taken before this migration if needed).
 * Drafts take the account's mobile number, which the form now shows
 * read-only.
 */
return new class extends Migration
{
    private const NAME_PREFIXES = ['عبد', 'أبو', 'ابو'];

    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('student_name', 40)->nullable()->after('full_name_ar');
            $table->string('father_name', 40)->nullable()->after('student_name');
            $table->string('grandfather_name', 40)->nullable()->after('father_name');
            $table->string('great_grandfather_name', 40)->nullable()->after('grandfather_name');
            $table->string('mother_first_name', 40)->nullable()->after('mother_name_ar');
            $table->string('mother_father_name', 40)->nullable()->after('mother_first_name');
        });

        DB::table('applications')
            ->where(fn ($query) => $query->whereNotNull('full_name_ar')->orWhereNotNull('mother_name_ar'))
            ->orderBy('id')
            ->each(function (object $row): void {
                $name = $this->parts((string) $row->full_name_ar, 4);
                $mother = $this->parts((string) $row->mother_name_ar, 2);

                DB::table('applications')->where('id', $row->id)->update([
                    'student_name' => $name[0],
                    'father_name' => $name[1],
                    'grandfather_name' => $name[2],
                    'great_grandfather_name' => $name[3],
                    'mother_first_name' => $mother[0],
                    'mother_father_name' => $mother[1],
                ]);
            });

        DB::table('applications')
            ->whereIn('status', ['draft', 'requires_correction'])
            ->orderBy('id')
            ->each(function (object $row): void {
                $phone = DB::table('users')->where('id', $row->user_id)->value('phone');

                if ($phone !== null) {
                    DB::table('applications')->where('id', $row->id)->update(['phone' => $phone]);
                }
            });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('surname');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('surname', 60)->nullable()->after('full_name_ar');
            $table->dropColumn([
                'student_name', 'father_name', 'grandfather_name', 'great_grandfather_name',
                'mother_first_name', 'mother_father_name',
            ]);
        });
    }

    /**
     * Split a name into `$count` parts, keeping compound names such as
     * "عبد الرحمن" together; any extra words stay in the last part.
     *
     * @return list<string|null>
     */
    private function parts(string $name, int $count): array
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];

        for ($index = 0; $index < count($words); $index++) {
            $word = $words[$index];

            if (in_array($word, self::NAME_PREFIXES, true) && isset($words[$index + 1])) {
                $word .= ' '.$words[++$index];
            }

            $parts[] = $word;
        }

        if (count($parts) > $count) {
            $parts = [...array_slice($parts, 0, $count - 1), implode(' ', array_slice($parts, $count - 1))];
        }

        return array_map(
            fn (int $index): ?string => isset($parts[$index]) ? mb_substr($parts[$index], 0, 40) : null,
            range(0, $count - 1),
        );
    }
};

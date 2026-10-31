<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/**
 * Public portal content. Missing pages are created and pages no administrator
 * has edited yet (`updated_by` is empty) take the latest wording, so admin
 * edits are never overwritten by a redeploy. The instructions, conditions
 * and documents pages follow the authority's published instructions.
 */
class ContentPageSeeder extends Seeder
{
    /**
     * Title (ar, en) and body (ar, en) of the application regulations shown
     * on the terms page. One numbered item per paragraph.
     */
    public const ELIGIBILITY = [
        'ضوابط وتعليمات التقديم للامتحانات التمهيدية للدراسة المهنية للعام الدراسي (2026-2027)',
        'Regulations and Instructions for Applying to the Preliminary Examinations for Vocational Study, Academic Year (2026-2027)',
        <<<'TEXT'
        ١. يبدأ التقديم للامتحانات المهنية العامة ( الامتحانات الخارجية ) للعام الدراسي 2026-2027 اعتبارا من يوم الخميس الموافق 15/11/2026 ولغاية نهاية الدوام الرسمي ليوم الاحد الموافق 25/12/2026.

        ٢. يتم سحب استمارة واقع الحال عن طريق الموقع الالكتروني المخصص للامتحانات المهنية العامه الخاص بقسم التعليم المهني في كربلاء المقدسة.

        ٣. اذا كانت اخر مدرسة مسجلا فيها الطالب في محافظة أخرى فعلى الطالب ملئ الاستمارة الخاصة من مدرسته ( اخر مدرسة كان فيها ) ومصادق عليها من قبل مديرية التربية التي تقع بها مدرسته.

        ٤. يخضع الطالب المتقدم للفرع الصناعي وفرع الحاسوب وتقنية المعلومات لاختبار عملي تطبيقي لبيان اهليتهم للامتحان التمهيدي.

        ٥. اذا كان المتقدم متخرج من المدرسة المتوسطة ولم يلتحق بالمدرسة الإعدادية يجب ان يكون لديه ثلاث سنوات ترك على الأقل (2023-2024 و 2024-2025 و 2025-2026) او اكثر من ذلك.

        ٦. اذا كان المتقدم راسبا في الصف الرابع الاعدادي فيجب ان يكون لديه ثلاث سنوات ترك على الأقل (2023-2024 و 2024-2025 و 2025-2026) او اكثر من ذلك.

        ٧. اذا كان المتقدم راسبا في الصف الخامس الاعدادي او ناجحا من الصف الرابع الاعدادي فيجب ان يكون لديه سنتان ترك على الأقل (2024-2025 و 2025-2026) او اكثر من ذلك.

        ٨. اذا كان المتقدم راسبا في الصف السادس الاعدادي او ناجحا من الصف الخامس الاعدادي فيجب ان يكون لديه سنه ترك واحدة على الأقل (2025-2026) او اكثر من ذلك.

        ٩. طلبة المدارس المسائية اذا كان راسبا سنــة واحــــــدة في الصــــف السادس الاعدادي للعام (2025-2026) يحق له التقديم للاشتراك في الامتحان التمهيدي.

        ١٠. يحق لخريجي الدراسة الإعدادية ( الاحيائي والتطبيقي والادبي ) للاعوام السابقة التقديم لاداء الامتحانات المهنية العامة ( التمهيدية ) الخارجي المهني في ( للفروع المهنية كافة ).

        ١١. يحق لخريجي الدراسة الإعدادية للتمريض او الراسبين فيها وخريجي اعداديات السياحة والفندقة التقديم لاداء الامتحانات المهنية العامة ( التمهيدية ) الخارجي الاعدادي المهني للفروع المهنية كافة بعد ان يحصلوا على الموافقات الأصولية من الجهات المسؤولة في وزارة الصحة وتنطبق عليهم شروط التقديم.

        ١٢. يحق للطلبة الراسبين في الصف الرابع والخامس والسادس ( العلمي والادبي ) والتي تنطبق عليهم شروط التقديم في أداء الامتحانات المهنية العامة ( التمهيدية ) الخارجي المهني.

        ١٣. يحق لخريجي الدراسة المتوسطة التقديم لأداء الامتحانات المهنية العامة ( التمهيدية ) في الفروع المهنية كافة اما بالنسبـــة الى التمريض يكــــون من خريجـــي الدراســـة المتوسطــة الاكاديميــة ( الوزارية ) بعد استحصال الموافقات الأصولية من الجهات المسؤولة في وزارة الصحة وتنطبق عليهم شروط التقديم.

        ١٤. يحق للطلبة الراسبين او التاركين في المدارس المهنية التقديم لاداء الامتحانات المهنيــة العامــة (التمهيدية ) على أن تنطبق عليهم التعليمات.

        ١٥. يحق للطلبة خريجي المدارس المهنية التقديم لاداء الامتحانات المهنية العامة ( التمهيدية ) الخارجي الاعدادي المهني للفروع المهنية كافة.

        ١٦. يحق التقديم لاداء الامتحانات المهنيــة العامــة ( التمهيدية ) من الذين سجلوا في المراكز التدريبية المعتمدة التابعة الى الوزارات كافة في الاختصاصات التي تخرجوا منها واللذين رقنت قيودهم فيها على ان تنطبق عليهم الضوابط والتعليمات على ان يكون خريج الدراسة المتوسطة الاكاديمية ( الوزارية ).

        ١٧. يحق لطلبة المدارس الثانوية المهنية المسائية الملغاة والممتحنين وزارياً بالتقديم على الامتحانات الخارجية كلا حسب اختصاصه وذلك استنادا الى كتاب المديرية العامة للتعليم المهني المرقم (6289) في 6/10/2003.

        ١٨. يحق لطلبة معاهد اعداد المعلمين والفنون الجميلة والذين يعاملون معاملة خريجي الدراسة المتوسطة بالتقديم على الامتحانات الخارجية ، اما المرقنة قيودهم يجب ان يستحصلوا على الموافقات الأصولية من الجهات المسؤولة التي كانوا فيها.

        ١٩. يكون التقديم على الفرع الزراعي فقط من كان طالبا بالفرع الزراعي بالمدارس المهنية او من يعمل ضمن الاختصاص ويقدم ما يثبت ذلك.

        ٢٠. تقوم اللجنة بتدقيق استمارات المتقدمين للامتحانات التمهيدية وإعلان أسماء الطلبة اللذين تنطبق عليهم التعليمات.

        ٢١. يتم دفع مبلغ (50.000) خمسون الف دينار بموجب وصل رسمي يرفق نسخة مصورة منه مع طلب التقديم ويحتفظ الطالب بالنسخة الاصلية.
        TEXT,
        <<<'TEXT'
        1. Applications for the general vocational examinations (external examinations) for the 2026-2027 academic year open on Thursday 15/11/2026 and close at the end of official working hours on Sunday 25/12/2026.

        2. The status form is obtained from the website dedicated to the general vocational examinations of the Vocational Education Department in Holy Karbala.

        3. If the student's last registered school is in another governorate, the student must complete the special form from that school (the last school attended), certified by the education directorate where the school is located.

        4. Applicants to the industrial branch and the computer and information technology branch sit a practical test to establish their eligibility for the preliminary examination.

        5. Applicants who graduated from intermediate school and did not join preparatory school must have left study for at least three years (2023-2024, 2024-2025 and 2025-2026) or more.

        6. Applicants who failed the fourth preparatory grade must have left study for at least three years (2023-2024, 2024-2025 and 2025-2026) or more.

        7. Applicants who failed the fifth preparatory grade or passed the fourth preparatory grade must have left study for at least two years (2024-2025 and 2025-2026) or more.

        8. Applicants who failed the sixth preparatory grade or passed the fifth preparatory grade must have left study for at least one year (2025-2026) or more.

        9. Evening school students who failed the sixth preparatory grade once in the year (2025-2026) may apply to take part in the preliminary examination.

        10. Graduates of preparatory study (biological, applied and literary) of previous years may apply for the general vocational (preliminary) examinations, external vocational, in all vocational branches.

        11. Graduates of preparatory nursing study or those who failed it, and graduates of tourism and hospitality preparatory schools, may apply for the general vocational (preliminary) examinations, external vocational preparatory, in all vocational branches after obtaining the proper approvals from the responsible authorities in the Ministry of Health, provided that they meet the application conditions.

        12. Students who failed the fourth, fifth or sixth grade (scientific and literary) and who meet the application conditions may take the general vocational (preliminary) examinations, external vocational.

        13. Intermediate study graduates may apply for the general vocational (preliminary) examinations in all vocational branches. Nursing is open to graduates of academic (ministerial) intermediate study, after obtaining the proper approvals from the responsible authorities in the Ministry of Health, provided that they meet the application conditions.

        14. Students who failed or left vocational schools may apply for the general vocational (preliminary) examinations, provided that the instructions apply to them.

        15. Graduates of vocational schools may apply for the general vocational (preliminary) examinations, external vocational preparatory, in all vocational branches.

        16. Those who registered at accredited training centers of any ministry may apply for the general vocational (preliminary) examinations in the specializations they graduated from, as may those whose registration there was cancelled, provided that the regulations and instructions apply to them and that they are graduates of academic (ministerial) intermediate study.

        17. Students of the abolished evening vocational secondary schools who sat ministerial examinations may apply for the external examinations, each in their own specialization, under letter No. (6289) of the General Directorate of Vocational Education dated 6/10/2003.

        18. Students of teacher training institutes and fine arts institutes who are treated as intermediate study graduates may apply for the external examinations. Those whose registration was cancelled must obtain the proper approvals from the responsible authorities where they studied.

        19. Only students of the agricultural branch in vocational schools, or those working in the specialization who provide proof of it, may apply to the agricultural branch.

        20. The committee reviews the applicants' forms for the preliminary examinations and announces the names of the students to whom the instructions apply.

        21. A fee of (50,000) fifty thousand dinars is paid against an official receipt. A copy of the receipt is attached to the application and the student keeps the original.
        TEXT,
    ];

    public function run(): void
    {
        $pages = [
            'about' => [
                'عن الخدمة', 'About the service',
                "تتيح منصة التقديم الإلكتروني للامتحانات الخارجية للطلبة التقديم من الهاتف أو الحاسوب، ورفع المستمسكات، ومتابعة حالة الطلب إلكترونياً دون الحاجة إلى المراجعة الحضورية إلا عند الطلب.\n\nبعد إرسال الطلب تحصل على رقم طلب فريد وإيصال قابل للطباعة.",
                "The external examination e-application portal lets applicants apply from a phone or computer, upload their documents and follow their application online, visiting in person only when asked.\n\nAfter submitting you receive a unique reference number and a printable receipt.",
            ],
            'instructions' => [
                'تعليمات ملء الاستمارة', 'How to fill in the form',
                "١. أنشئ حساباً برقم الموبايل، وسيُنشأ لك رقم طالب خاص بهذا التقديم. الحساب يُستخدم مرة واحدة ويُغلق بعد طباعة الاستمارة.\n\n٢. اقرأ «شروط التقديم» و«المستمسكات المطلوبة» قبل البدء.\n\n٣. اختر الدورة الامتحانية المفتوحة واضغط «بدء طلب جديد».\n\n٤. املأ بيانات استمارة واقع حال بدقة كما وردت في المستمسكات الرسمية: الاسم الرباعي واللقب، والتولد، واسم الأم الثلاثي، وعنوان السكن الدائم في كربلاء (الحي، المحلة، الزقاق، الدار)، ورقم الهاتف، وآخر مدرسة، والمدرسة المتوسطة المتخرج منها، وآخر صف، وسنوات الرسوب والترك، ثم الفرع والاختصاص. يُحفظ الطلب كمسودة ويمكنك إكماله لاحقاً.\n\n٥. ارفع المستمسكات المطلوبة بصيغة JPG أو PNG أو PDF ضمن الحجم المسموح.\n\n٦. راجع البيانات ثم وافق على الإقرار وأرسل الطلب. لا يمكن تعديل الطلب بعد الإرسال إلا إذا طُلب منك الاستكمال.\n\n٧. تُختم استمارة واقع حال الورقية من آخر مدرسة، ويتحمل مدير المدرسة صحة المعلومات، وتسلَّم إلى قسم التعليم المهني.",
                "1. Create an account with your mobile number and you will receive a student number for this application. The account is used once and closes after you print the form.\n\n2. Read the application conditions and the required documents before you start.\n\n3. Choose the open exam period and select \"Start a new application\".\n\n4. Fill in the student status form exactly as in your official documents: full name and surname, date of birth, mother's name, permanent Karbala address (district, mahalla, alley, house), phone number, last school, intermediate school, last grade, years failed and years out of school, then the branch and specialization. Your application is saved as a draft and you can finish it later.\n\n5. Upload the required documents as JPG, PNG or PDF within the size limit.\n\n6. Review your details, accept the declaration and submit. You cannot edit the application after submission unless you are asked for a correction.\n\n7. The paper status form is stamped by your last school, whose principal vouches for it, and handed to the vocational education department.",
            ],
            'eligibility' => self::ELIGIBILITY,
            'documents' => [
                'المستمسكات المطلوبة', 'Required documents',
                "١. طلب الاشتراك في الامتحان الخارجي.\n\n٢. هوية الأحوال المدنية أو البطاقة الموحدة للطالب، أصل وصورة (ملونة).\n\n٣. هوية الأحوال المدنية أو البطاقة الموحدة لوالد الطالب، وفي حالة وفاة الأب يتم جلب شهادة الوفاة أو صورة قيد، أصل وصورة (ملونة).\n\n٤. هوية الأحوال المدنية أو البطاقة الموحدة لأم الطالب، وفي حالة وفاة الأم يتم جلب شهادة الوفاة أو صورة قيد، أصل وصورة (ملونة).\n\n٥. بطاقة السكن (كربلاء حصراً) للطالب، أصل وصورة (ملونة).\n\n٦. بالنسبة للمستمسكات المذكورة أعلاه يجب أن تكون مستنسخة في ورقة واحدة.\n\n٧. بالنسبة للنازحين الذين ليس لديهم بطاقة سكن (كربلاء) فيجب تقديم كتاب من دائرة الهجرة والمهجرين + تأييد سكن المتقدم من المختار مدعماً بتوقيع شاهدين مع أرقام الموبايل لهم مع ختم القائممقامية.\n\n٨. صورة أمامية خلفية بيضاء قياس (3.5×4.5) عدد 10 بدون غطاء رأس بالنسبة للذكور.\n\n٩. توضع المستمسكات أعلاه في فايل كارتون ويكون لون الفايل حسب الفرع:\nالصناعي: أحمر\nالحاسوب وتقنية المعلومات: أبيض\nالتجاري: أصفر\nالفنون التطبيقية: أزرق\nالزراعي: أخضر\nالسياحة والفندقة: وردي\n\nارفع نسخة واضحة من كل مستمسك في النظام. تُفحص الملفات تلقائياً قبل اعتمادها.",
                "1. The external examination participation request.\n\n2. The student's civil ID or unified national card, original and colour copy.\n\n3. The father's civil ID or unified national card; if the father has died, the death certificate or a civil registry record, original and colour copy.\n\n4. The mother's civil ID or unified national card; if the mother has died, the death certificate or a civil registry record, original and colour copy.\n\n5. The student's residence card (Karbala only), original and colour copy.\n\n6. Each of the documents above must be copied on one page.\n\n7. Displaced students without a Karbala residence card must provide a letter from the Migration and Displacement office plus a residence confirmation from the mukhtar, signed by two witnesses with their mobile numbers and stamped by the district office.\n\n8. Ten front-facing photos on a white background (3.5×4.5 cm), without a head cover for males.\n\n9. The documents go in a cardboard folder in the branch's colour:\nIndustrial: red\nComputing and IT: white\nCommercial: yellow\nApplied arts: blue\nAgricultural: green\nTourism and hospitality: pink\n\nUpload a clear copy of each document to the system. Files are checked automatically before they are accepted.",
            ],
            'faq' => [
                'الأسئلة الشائعة', 'Frequently asked questions',
                "هل يمكن لأكثر من طالب استخدام رقم الموبايل نفسه؟\nنعم. لكل طالب حساب مستقل برقم طالب خاص به، ولا يستطيع أي حساب الاطلاع على طلبات حساب آخر.\n\nهل يمكنني الدخول إلى حسابي بعد طباعة الاستمارة؟\nلا. يُغلق الحساب نهائياً بعد طباعة الاستمارة، وإذا احتجت إلى التقديم مرة أخرى فأنشئ حساباً جديداً.\n\nهل يمكنني تعديل الطلب بعد إرساله؟\nلا، إلا إذا طلبت منك لجنة التدقيق استكمال البيانات.",
                "Can several students use the same mobile number?\nYes. Each student has a separate account with their own student number, and no account can see another account's applications.\n\nCan I sign in again after printing the form?\nNo. The account closes for good once the form is printed. To apply again, create a new account.\n\nCan I edit my application after submitting it?\nNo, unless the review team asks you for a correction.",
            ],
            'privacy' => [
                'سياسة الخصوصية', 'Privacy policy',
                "نجمع البيانات اللازمة فقط لمعالجة طلب الامتحان. تُحفظ المستمسكات في تخزين خاص ولا يطّلع عليها إلا الموظفون المخولون، ويُسجَّل كل اطلاع إداري عليها.\n\nلا تُستخدم البيانات لأي غرض آخر، وتُحذف أو تُؤرشف وفق مدة الاحتفاظ المعتمدة من الجهة المنظمة.",
                "We collect only the data needed to process your examination application. Documents are kept in private storage, only authorized staff can view them, and every administrative view is logged.\n\nThe data is not used for any other purpose and is deleted or archived according to the retention period set by the authority.",
            ],
            'terms' => [
                'شروط الاستخدام', 'Terms of use',
                "يقر مقدم الطلب بصحة البيانات والمستمسكات المرفوعة ويتحمل المسؤولية القانونية عن أي بيانات غير صحيحة.\n\nيُمنع استخدام المنصة بأي طريقة تضر بها أو بمستخدميها.",
                "Applicants confirm that their data and uploaded documents are accurate and bear legal responsibility for any false information.\n\nUsing the portal in any way that harms it or its users is prohibited.",
            ],
            'help' => [
                'المساعدة والتواصل', 'Help and contact',
                "للاستفسار يُرجى التواصل مع قسم الامتحانات في المديرية العامة للتربية في محافظتك خلال أوقات الدوام الرسمي.\n\nعند التواصل اذكر رقم الطلب إن وجد، ولا تشارك رمز الدخول مع أي شخص.",
                "For questions, contact the examinations department of the education directorate in your governorate during working hours.\n\nMention your reference number if you have one, and never share your access code with anyone.",
            ],
        ];

        foreach ($pages as $slug => [$titleAr, $titleEn, $bodyAr, $bodyEn]) {
            $page = ContentPage::query()->firstOrNew(['slug' => $slug]);

            if ($page->exists && $page->updated_by !== null) {
                continue;
            }

            $page->fill([
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
            ]);
            $page->is_published ??= true;
            $page->save();
        }
    }
}

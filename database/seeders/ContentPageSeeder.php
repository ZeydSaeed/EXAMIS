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
                "١. أنشئ حساباً باسم مستخدم خاص بك واحفظ اسم المستخدم، فستدخل به في كل مرة.\n\n٢. اقرأ «شروط التقديم» و«المستمسكات المطلوبة» قبل البدء.\n\n٣. اختر الدورة الامتحانية المفتوحة واضغط «بدء طلب جديد».\n\n٤. املأ بيانات استمارة واقع حال بدقة كما وردت في المستمسكات الرسمية: الاسم الرباعي واللقب، والتولد، واسم الأم الثلاثي، وعنوان السكن الدائم في كربلاء (الحي، المحلة، الزقاق، الدار)، ورقم الهاتف، وآخر مدرسة، والمدرسة المتوسطة المتخرج منها، وآخر صف، وسنوات الرسوب والترك، ثم الفرع والاختصاص. يُحفظ الطلب كمسودة ويمكنك إكماله لاحقاً.\n\n٥. ارفع المستمسكات المطلوبة بصيغة JPG أو PNG أو PDF ضمن الحجم المسموح.\n\n٦. راجع البيانات ثم وافق على الإقرار وأرسل الطلب. لا يمكن تعديل الطلب بعد الإرسال إلا إذا طُلب منك الاستكمال.\n\n٧. تُختم استمارة واقع حال الورقية من آخر مدرسة، ويتحمل مدير المدرسة صحة المعلومات، وتسلَّم إلى قسم التعليم المهني.",
                "1. Create an account with your own username and keep it: you sign in with it every time.\n\n2. Read the application conditions and the required documents before you start.\n\n3. Choose the open exam period and select \"Start a new application\".\n\n4. Fill in the student status form exactly as in your official documents: full name and surname, date of birth, mother's name, permanent Karbala address (district, mahalla, alley, house), phone number, last school, intermediate school, last grade, years failed and years out of school, then the branch and specialization. Your application is saved as a draft and you can finish it later.\n\n5. Upload the required documents as JPG, PNG or PDF within the size limit.\n\n6. Review your details, accept the declaration and submit. You cannot edit the application after submission unless you are asked for a correction.\n\n7. The paper status form is stamped by your last school, whose principal vouches for it, and handed to the vocational education department.",
            ],
            'eligibility' => [
                'شروط التقديم', 'Application conditions',
                "١. أن يكون الطالب المتقدم من سكنة إحدى المحافظات المشمولة بالتقديم ضمن الرقعة الجغرافية للمعهد {بغداد، بابل، ديالى، النجف الأشرف، كربلاء، واسط، الديوانية، المثنى} وفقاً لما مثبت في وثيقة التخرج الصادرة من الإعدادية.\n\n٢. أن لا يتجاوز عمر الطالب المتقدم 24 سنة، ويُسمح بالتقديم لخريجي المدارس الإعدادية العراقية حصراً {صباحي، مسائي، خارجي}، الإعدادية {العلمي / المهني} للعامين الدراسيين 2023/2024 و2024/2025.\n\n٣. أن لا يقل معدل الطالب المتقدم عن 75%.\n\n٤. تخصص نسبة 20% من خطة القبول بنفس الشروط الواردة في أعلاه لخريجي الدراسة الإعدادية المهنية {الصناعة} بتأييد رسمي صادر من المديرية العامة للتعليم المهني معززاً برمز الاستجابة السريع (QR Code)، يؤيد أن المتقدم من ضمن 10% الأوائل للاختصاصات المشمولة بالتقديم {تكرير النفط ومعالجة الغاز / صناعات بتروكيمياوية / إلكترونيك وسيطرة / كهرباء / لحام / سيارات / ميكانيك}، وستكون المفاضلة وفقاً لأعلى المعدلات ضمن الاختصاص المهني الواحد.\n\n٥. قبول الإناث بنسبة {10-15}% من خطة القبول وحسب الاختصاصات الملائمة.\n\n٦. تخصص نسبة 10% من خطة القبول بنفس الشروط الواردة في أعلاه لذوي الشهداء من الدرجة الأولى فقط وفقاً للمادة (29) ثانياً من القانون المدني رقم (40) لسنة 1951.\n\n٧. يشترط في الطالب المتقدم أن يكون لائقاً من الناحية الصحية والبدنية بحسب طبيعة ومتطلبات الاختصاص الذي سيُقبل فيه.\n\n٨. الوزارة غير ملزمة بتعيين خريجي المعاهد النفطية بعد التخرج مباشرة.\n\n٩. السقف الزمني لمراجعة الطالب المقبول أسبوعان من تاريخ إعلان نتائج القبول، وفي حال عدم مراجعة الطالب خلال المدة المذكورة يسقط حقه في القبول، ويتم تعويضه من قوائم الاحتياط وفقاً لأعلى المعدلات.",
                "1. The applicant must live in one of the governorates covered by the institute {Baghdad, Babil, Diyala, Najaf, Karbala, Wasit, Diwaniyah, Muthanna}, as shown in the preparatory school graduation document.\n\n2. The applicant must be no older than 24. Only graduates of Iraqi preparatory schools {morning, evening, external}, preparatory {scientific / vocational}, of the 2023/2024 and 2024/2025 school years may apply.\n\n3. The applicant's average must be at least 75%.\n\n4. 20% of the admission plan, under the same conditions, is reserved for graduates of vocational preparatory {industrial} schools with an official confirmation from the General Directorate of Vocational Education, backed by a QR code, that the applicant is in the top 10% of the eligible specializations {oil refining and gas processing / petrochemical industries / electronics and control / electrical / welding / automotive / mechanics}; ranking is by the highest averages within each specialization.\n\n5. Female applicants are admitted at {10-15}% of the admission plan, in suitable specializations.\n\n6. 10% of the admission plan, under the same conditions, is reserved for first-degree relatives of martyrs only, under Article 29(2) of Civil Law No. 40 of 1951.\n\n7. The applicant must be medically and physically fit for the specialization they are admitted to.\n\n8. The ministry is not obliged to employ graduates of the oil institutes immediately after graduation.\n\n9. An admitted student must report within two weeks of the announcement of admission results; otherwise the place is lost and given to the reserve list by highest average.",
            ],
            'documents' => [
                'المستمسكات المطلوبة', 'Required documents',
                "١. طلب الاشتراك في الامتحان الخارجي.\n\n٢. هوية الأحوال المدنية أو البطاقة الموحدة للطالب، أصل وصورة (ملونة).\n\n٣. هوية الأحوال المدنية أو البطاقة الموحدة لوالد الطالب، وفي حالة وفاة الأب يتم جلب شهادة الوفاة أو صورة قيد، أصل وصورة (ملونة).\n\n٤. هوية الأحوال المدنية أو البطاقة الموحدة لأم الطالب، وفي حالة وفاة الأم يتم جلب شهادة الوفاة أو صورة قيد، أصل وصورة (ملونة).\n\n٥. بطاقة السكن (كربلاء حصراً) للطالب، أصل وصورة (ملونة).\n\n٦. بالنسبة للمستمسكات المذكورة أعلاه يجب أن تكون مستنسخة في ورقة واحدة.\n\n٧. بالنسبة للنازحين الذين ليس لديهم بطاقة سكن (كربلاء) فيجب تقديم كتاب من دائرة الهجرة والمهجرين + تأييد سكن المتقدم من المختار مدعماً بتوقيع شاهدين مع أرقام الموبايل لهم مع ختم القائممقامية.\n\n٨. صورة أمامية خلفية بيضاء قياس (3.5×4.5) عدد 10 بدون غطاء رأس بالنسبة للذكور.\n\n٩. توضع المستمسكات أعلاه في فايل كارتون ويكون لون الفايل حسب الفرع:\nالصناعي: أحمر\nالحاسوب وتقنية المعلومات: أبيض\nالتجاري: أصفر\nالفنون التطبيقية: أزرق\nالزراعي: أخضر\nالسياحة والفندقة: وردي\n\nارفع نسخة واضحة من كل مستمسك في النظام. تُفحص الملفات تلقائياً قبل اعتمادها.",
                "1. The external examination participation request.\n\n2. The student's civil ID or unified national card, original and colour copy.\n\n3. The father's civil ID or unified national card; if the father has died, the death certificate or a civil registry record, original and colour copy.\n\n4. The mother's civil ID or unified national card; if the mother has died, the death certificate or a civil registry record, original and colour copy.\n\n5. The student's residence card (Karbala only), original and colour copy.\n\n6. Each of the documents above must be copied on one page.\n\n7. Displaced students without a Karbala residence card must provide a letter from the Migration and Displacement office plus a residence confirmation from the mukhtar, signed by two witnesses with their mobile numbers and stamped by the district office.\n\n8. Ten front-facing photos on a white background (3.5×4.5 cm), without a head cover for males.\n\n9. The documents go in a cardboard folder in the branch's colour:\nIndustrial: red\nComputing and IT: white\nCommercial: yellow\nApplied arts: blue\nAgricultural: green\nTourism and hospitality: pink\n\nUpload a clear copy of each document to the system. Files are checked automatically before they are accepted.",
            ],
            'faq' => [
                'الأسئلة الشائعة', 'Frequently asked questions',
                "هل يمكن لأكثر من طالب استخدام البريد الإلكتروني نفسه؟\nنعم. لكل طالب حساب مستقل باسم مستخدم خاص به، ولا يستطيع أي حساب الاطلاع على طلبات حساب آخر.\n\nنسيت كلمة المرور، ماذا أفعل؟\nاستخدم «نسيت كلمة المرور» وأدخل اسم المستخدم، وسيصل الرابط إلى البريد المسجل في الحساب.\n\nهل يمكنني تعديل الطلب بعد إرساله؟\nلا، إلا إذا طلبت منك لجنة التدقيق استكمال البيانات.",
                "Can several students use the same email address?\nYes. Each student has a separate account with their own username, and no account can see another account's applications.\n\nI forgot my password. What should I do?\nUse \"Forgot password\" with your username; the link is sent to the email address on the account.\n\nCan I edit my application after submitting it?\nNo, unless the review team asks you for a correction.",
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
                "للاستفسار يُرجى التواصل مع قسم الامتحانات في المديرية العامة للتربية في محافظتك خلال أوقات الدوام الرسمي.\n\nعند التواصل اذكر رقم الطلب إن وجد، ولا تشارك كلمة المرور مع أي شخص.",
                "For questions, contact the examinations department of the education directorate in your governorate during working hours.\n\nMention your reference number if you have one, and never share your password with anyone.",
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

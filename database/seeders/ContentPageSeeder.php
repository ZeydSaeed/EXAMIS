<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/**
 * Initial public portal content. Pages are only created if missing so that
 * administrator edits are never overwritten by a redeploy. The texts are
 * drafts to be replaced with the authority's approved wording.
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
                "١. أنشئ حساباً باسم مستخدم خاص بك وفعّل بريدك الإلكتروني.\n\n٢. اختر الدورة الامتحانية المفتوحة واضغط «بدء طلب جديد».\n\n٣. املأ البيانات بدقة كما وردت في المستمسكات الرسمية. يُحفظ الطلب كمسودة ويمكنك إكماله لاحقاً.\n\n٤. ارفع المستمسكات المطلوبة بصيغة JPG أو PNG أو PDF ضمن الحجم المسموح.\n\n٥. راجع البيانات ثم وافق على الإقرار وأرسل الطلب. لا يمكن تعديل الطلب بعد الإرسال إلا إذا طُلب منك الاستكمال.",
                "1. Create an account with your own username and verify your email address.\n\n2. Choose the open exam period and select \"Start a new application\".\n\n3. Enter your details exactly as they appear in your official documents. Your application is saved as a draft and you can finish it later.\n\n4. Upload the required documents as JPG, PNG or PDF within the size limit.\n\n5. Review your details, accept the declaration and submit. You cannot edit the application after submission unless you are asked for a correction.",
            ],
            'eligibility' => [
                'الفئات المؤهلة', 'Who can apply',
                'تحدد تعليمات كل دورة امتحانية الفئات المؤهلة والفروع المتاحة. تظهر لك عند ملء الاستمارة الخيارات المتاحة للدورة المختارة فقط.',
                'The instructions of each exam period define the eligible categories and branches. The form shows only the options available for the selected period.',
            ],
            'documents' => [
                'المستمسكات المطلوبة', 'Required documents',
                "تختلف المستمسكات المطلوبة حسب الدورة، وتشمل عادةً: الصورة الشخصية، والبطاقة الوطنية، ووثيقة المدرسة أو الشهادة السابقة.\n\nيجب أن تكون الصور واضحة ومقروءة. تُفحص الملفات تلقائياً قبل اعتمادها.",
                "Required documents depend on the exam period and usually include a personal photo, the national ID card and a school document or previous certificate.\n\nImages must be clear and readable. Files are checked automatically before they are accepted.",
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
            ContentPage::query()->firstOrCreate(['slug' => $slug], [
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
                'is_published' => true,
            ]);
        }
    }
}

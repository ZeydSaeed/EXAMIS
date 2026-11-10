import { Mail } from 'lucide-react';

type Contact = {
    label: string;
    name: string;
    email: string;
};

/** Credits and how to reach them; English in both languages. */
const contacts: Contact[] = [
    {
        label: 'Design',
        name: 'Dr. Zeyd Saeed',
        email: 'zeydsaeed@gmail.com',
    },
    {
        label: 'Support',
        name: 'Muhamed Abd Al Bari',
        email: 'mumh159@gmail.com',
    },
];

/**
 * The people behind the portal, above the ministry line of the start page.
 */
export function StartContacts() {
    return (
        <div
            dir="ltr"
            lang="en"
            className="mb-[0.8svh] space-y-[0.4svh] text-[clamp(0.625rem,min(1.7svh,3.1vw),0.8125rem)] leading-tight text-[var(--examis-navy)] dark:text-white"
        >
            {contacts.map((contact) => (
                <div
                    key={contact.name}
                    className="flex flex-wrap items-center justify-center gap-x-2.5 gap-y-0.5"
                >
                    <span className="basis-full font-semibold sm:basis-auto">
                        {contact.label}: {contact.name}
                    </span>
                    <a
                        href={`mailto:${contact.email}`}
                        className="inline-flex items-center gap-1 underline-offset-2 hover:underline"
                    >
                        <Mail className="size-3.5" aria-hidden />
                        {contact.email}
                    </a>
                </div>
            ))}
        </div>
    );
}

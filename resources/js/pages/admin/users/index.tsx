import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { ShieldCheck, ShieldOff, UserPlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { Field } from '@/components/examis/field';
import { PageContainer, PageHeader } from '@/components/examis/page-header';
import { Pagination } from '@/components/examis/pagination';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-trans';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type StaffRow = {
    id: number;
    username: string;
    name: string;
    email: string;
    roles: string[];
    is_active: boolean;
    two_factor_enabled: boolean;
    last_login_at: string | null;
};

type Props = {
    staff: Paginated<StaffRow>;
    roles: { value: string; label: string }[];
};

function RoleEditor({
    user,
    roles,
}: {
    user: StaffRow;
    roles: Props['roles'];
}) {
    const { t } = useTrans();
    const form = useForm({ roles: user.roles });

    return (
        <form
            className="flex flex-wrap items-center gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(admin.users.roles.url(user.id), {
                    preserveScroll: true,
                });
            }}
        >
            {roles.map((role) => (
                <div key={role.value} className="flex items-center gap-1.5">
                    <Checkbox
                        id={`role-${user.id}-${role.value}`}
                        checked={form.data.roles.includes(role.value)}
                        onCheckedChange={(checked) =>
                            form.setData(
                                'roles',
                                checked === true
                                    ? [...form.data.roles, role.value]
                                    : form.data.roles.filter(
                                          (value) => value !== role.value,
                                      ),
                            )
                        }
                    />
                    <Label
                        htmlFor={`role-${user.id}-${role.value}`}
                        className="text-xs font-normal"
                    >
                        {role.label}
                    </Label>
                </div>
            ))}
            {form.isDirty && (
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    disabled={form.processing}
                >
                    {t('common.save')}
                </Button>
            )}
            <InputError message={form.errors.roles} className="w-full" />
        </form>
    );
}

export default function StaffUsers({ staff, roles }: Props) {
    const { t, locale } = useTrans();
    const [showCreate, setShowCreate] = useState(false);
    const create = useForm<{
        username: string;
        name: string;
        email: string;
        roles: string[];
    }>({ username: '', name: '', email: '', roles: [] });

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.users'), href: admin.users.index() }],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        create.post(admin.users.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                create.reset();
                setShowCreate(false);
            },
        });
    };

    return (
        <>
            <Head title={t('nav.users')} />
            <PageContainer>
                <PageHeader
                    title={t('nav.users')}
                    description={t('users.description')}
                    actions={
                        <Button
                            onClick={() => setShowCreate((value) => !value)}
                            aria-expanded={showCreate}
                        >
                            <UserPlus className="size-4" aria-hidden />
                            {t('users.create')}
                        </Button>
                    }
                />

                {showCreate && (
                    <form
                        onSubmit={submit}
                        className="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-3"
                    >
                        <Field
                            id="new-username"
                            label={t('auth.username')}
                            error={create.errors.username}
                            required
                        >
                            <Input
                                id="new-username"
                                dir="ltr"
                                value={create.data.username}
                                onChange={(event) =>
                                    create.setData(
                                        'username',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id="new-name"
                            label={t('auth.full_name')}
                            error={create.errors.name}
                            required
                        >
                            <Input
                                id="new-name"
                                value={create.data.name}
                                onChange={(event) =>
                                    create.setData('name', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="new-email"
                            label={t('auth.email')}
                            error={create.errors.email}
                            required
                        >
                            <Input
                                id="new-email"
                                type="email"
                                dir="ltr"
                                value={create.data.email}
                                onChange={(event) =>
                                    create.setData('email', event.target.value)
                                }
                            />
                        </Field>
                        <fieldset className="sm:col-span-3">
                            <legend className="mb-2 text-sm font-medium">
                                {t('users.roles')}
                            </legend>
                            <div className="flex flex-wrap gap-4">
                                {roles.map((role) => (
                                    <div
                                        key={role.value}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`new-role-${role.value}`}
                                            checked={create.data.roles.includes(
                                                role.value,
                                            )}
                                            onCheckedChange={(checked) =>
                                                create.setData(
                                                    'roles',
                                                    checked === true
                                                        ? [
                                                              ...create.data
                                                                  .roles,
                                                              role.value,
                                                          ]
                                                        : create.data.roles.filter(
                                                              (value) =>
                                                                  value !==
                                                                  role.value,
                                                          ),
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={`new-role-${role.value}`}
                                            className="font-normal"
                                        >
                                            {role.label}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                            <InputError message={create.errors.roles} />
                        </fieldset>
                        <p className="text-xs text-muted-foreground sm:col-span-2">
                            {t('users.password_link_hint')}
                        </p>
                        <Button type="submit" disabled={create.processing}>
                            {t('users.create')}
                        </Button>
                    </form>
                )}

                <DataTableFrame>
                    <table className="min-w-[52rem]">
                        <thead>
                            <tr>
                                <th scope="col">{t('auth.full_name')}</th>
                                <th scope="col">{t('users.roles')}</th>
                                <th scope="col">{t('users.security')}</th>
                                <th scope="col">{t('users.last_login')}</th>
                                <th scope="col">
                                    <span className="sr-only">
                                        {t('admin.actions')}
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {staff.data.map((user) => (
                                <tr
                                    key={user.id}
                                    className={
                                        user.is_active ? '' : 'opacity-60'
                                    }
                                >
                                    <td>
                                        <p className="font-medium">
                                            {user.name}
                                        </p>
                                        <p
                                            className="text-xs text-muted-foreground"
                                            dir="ltr"
                                        >
                                            {user.username} · {user.email}
                                        </p>
                                    </td>
                                    <td>
                                        <RoleEditor user={user} roles={roles} />
                                    </td>
                                    <td>
                                        <span className="flex items-center gap-1.5">
                                            {user.two_factor_enabled ? (
                                                <ShieldCheck
                                                    className="size-4 text-success"
                                                    aria-hidden
                                                />
                                            ) : (
                                                <ShieldOff
                                                    className="size-4 text-warning"
                                                    aria-hidden
                                                />
                                            )}
                                            {user.two_factor_enabled
                                                ? t('users.two_factor_on')
                                                : t('users.two_factor_off')}
                                        </span>
                                    </td>
                                    <td className="text-muted-foreground">
                                        {formatDateTime(
                                            user.last_login_at,
                                            locale,
                                        )}
                                    </td>
                                    <td>
                                        <ConfirmDialog
                                            title={
                                                user.is_active
                                                    ? t('users.deactivate')
                                                    : t('users.reactivate')
                                            }
                                            description={
                                                user.is_active
                                                    ? t('users.deactivate_text')
                                                    : undefined
                                            }
                                            confirmLabel={
                                                user.is_active
                                                    ? t('users.deactivate')
                                                    : t('users.reactivate')
                                            }
                                            destructive={user.is_active}
                                            onConfirm={() =>
                                                router.put(
                                                    admin.users.status.url(
                                                        user.id,
                                                    ),
                                                    { active: !user.is_active },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Button variant="ghost" size="sm">
                                                {user.is_active
                                                    ? t('users.deactivate')
                                                    : t('users.reactivate')}
                                            </Button>
                                        </ConfirmDialog>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </DataTableFrame>

                <Pagination
                    prev={staff.prev_page_url}
                    next={staff.next_page_url}
                />
            </PageContainer>
        </>
    );
}

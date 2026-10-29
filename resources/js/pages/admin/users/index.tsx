import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { ShieldCheck, ShieldOff, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { ConfirmDialog } from '@/components/examis/confirm-dialog';
import { DataTableFrame } from '@/components/examis/data-table-frame';
import { EmptyState } from '@/components/examis/empty-state';
import { Field } from '@/components/examis/field';
import { Highlight } from '@/components/examis/highlight';
import {
    HeaderButton,
    HeaderFilter,
    ListHeader,
} from '@/components/examis/list-header';
import { NumberedPagination } from '@/components/examis/pagination';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useListQuery } from '@/hooks/use-list-query';
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
    filters: {
        role?: string;
        state?: string;
        q?: string;
        per_page?: string | number;
    };
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

function CreateStaffDialog({
    open,
    onOpenChange,
    roles,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: Props['roles'];
}) {
    const { t } = useTrans();
    const create = useForm<{
        username: string;
        name: string;
        email: string;
        roles: string[];
    }>({ username: '', name: '', email: '', roles: [] });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        create.post(admin.users.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                create.reset();
                onOpenChange(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader className="text-start">
                    <DialogTitle>{t('users.create')}</DialogTitle>
                    <DialogDescription>
                        {t('users.password_link_hint')}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-3">
                    <Field
                        id="new-username"
                        label={t('auth.username')}
                        error={create.errors.username}
                        required
                    >
                        <Input
                            id="new-username"
                            dir="auto"
                            value={create.data.username}
                            onChange={(event) =>
                                create.setData('username', event.target.value)
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
                                                          ...create.data.roles,
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
                    <Button
                        type="submit"
                        disabled={create.processing}
                        className="sm:col-start-3"
                    >
                        {t('users.create')}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function StaffUsers({ staff, filters, roles }: Props) {
    const { t, locale } = useTrans();
    const [showCreate, setShowCreate] = useState(false);
    const { search, setSearch, urlFor, visit, tableArea } = useListQuery({
        url: admin.users.index.url,
        filters,
        paginator: staff,
    });

    setLayoutProps({
        breadcrumbs: [{ title: t('nav.users'), href: admin.users.index() }],
    });

    return (
        <>
            <Head title={t('nav.users')} />

            <ListHeader
                search={search}
                onSearchChange={setSearch}
                onSearchSubmit={() => visit({ q: search.trim(), page: 1 })}
                placeholder={t('users.search_placeholder')}
                compactSearch
                actions={
                    <HeaderButton
                        onClick={() => setShowCreate(true)}
                        aria-haspopup="dialog"
                        aria-label={t('users.create')}
                        title={t('users.create')}
                    >
                        <UserPlus aria-hidden />
                    </HeaderButton>
                }
            >
                <HeaderFilter
                    label={t('users.role')}
                    value={filters.role ?? ''}
                    options={roles}
                    onChange={(role) => visit({ role, page: 1 })}
                />
                <HeaderFilter
                    label={t('users.state')}
                    value={filters.state ?? ''}
                    options={[
                        { value: 'active', label: t('users.active') },
                        { value: 'inactive', label: t('users.inactive') },
                    ]}
                    onChange={(state) => visit({ state, page: 1 })}
                />
            </ListHeader>

            <CreateStaffDialog
                open={showCreate}
                onOpenChange={setShowCreate}
                roles={roles}
            />

            <div className="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col gap-3 p-4 sm:p-6">
                <h1 className="sr-only">{t('nav.users')}</h1>

                <div ref={tableArea} className="min-h-0 flex-1">
                    {staff.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title={t('admin.no_results')}
                        />
                    ) : (
                        <DataTableFrame className="examis-data-table--plain max-h-full">
                            <table className="min-w-[56rem]">
                                <caption className="sr-only">
                                    {t('nav.users')}
                                </caption>
                                <thead>
                                    <tr>
                                        <th
                                            scope="col"
                                            className="w-12 text-center!"
                                        >
                                            #
                                        </th>
                                        <th scope="col">
                                            {t('auth.full_name')}
                                        </th>
                                        <th scope="col">{t('users.roles')}</th>
                                        <th scope="col">
                                            {t('users.security')}
                                        </th>
                                        <th scope="col">
                                            {t('users.last_login')}
                                        </th>
                                        <th scope="col">
                                            <span className="sr-only">
                                                {t('admin.actions')}
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {staff.data.map((user, index) => (
                                        <tr
                                            key={user.id}
                                            className={
                                                user.is_active
                                                    ? ''
                                                    : 'opacity-60'
                                            }
                                        >
                                            <td className="text-center! text-muted-foreground tabular-nums">
                                                {(staff.from ?? 1) + index}
                                            </td>
                                            <td className="whitespace-nowrap">
                                                <p className="font-medium">
                                                    <Highlight
                                                        text={user.name}
                                                        query={filters.q}
                                                    />
                                                </p>
                                                <p
                                                    className="text-xs text-muted-foreground"
                                                    dir="ltr"
                                                >
                                                    <Highlight
                                                        text={user.username}
                                                        query={filters.q}
                                                    />
                                                    {' · '}
                                                    <Highlight
                                                        text={user.email}
                                                        query={filters.q}
                                                    />
                                                </p>
                                            </td>
                                            <td>
                                                <RoleEditor
                                                    key={user.roles.join()}
                                                    user={user}
                                                    roles={roles}
                                                />
                                            </td>
                                            <td className="whitespace-nowrap">
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
                                                        ? t(
                                                              'users.two_factor_on',
                                                          )
                                                        : t(
                                                              'users.two_factor_off',
                                                          )}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(
                                                    user.last_login_at,
                                                    locale,
                                                )}
                                            </td>
                                            <td>
                                                <ConfirmDialog
                                                    title={
                                                        user.is_active
                                                            ? t(
                                                                  'users.deactivate',
                                                              )
                                                            : t(
                                                                  'users.reactivate',
                                                              )
                                                    }
                                                    description={
                                                        user.is_active
                                                            ? t(
                                                                  'users.deactivate_text',
                                                              )
                                                            : undefined
                                                    }
                                                    confirmLabel={
                                                        user.is_active
                                                            ? t(
                                                                  'users.deactivate',
                                                              )
                                                            : t(
                                                                  'users.reactivate',
                                                              )
                                                    }
                                                    destructive={user.is_active}
                                                    onConfirm={() =>
                                                        router.put(
                                                            admin.users.status.url(
                                                                user.id,
                                                            ),
                                                            {
                                                                active: !user.is_active,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        {user.is_active
                                                            ? t(
                                                                  'users.deactivate',
                                                              )
                                                            : t(
                                                                  'users.reactivate',
                                                              )}
                                                    </Button>
                                                </ConfirmDialog>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableFrame>
                    )}
                </div>

                <NumberedPagination
                    current={staff.current_page}
                    last={staff.last_page}
                    hrefFor={(page) => urlFor({ page })}
                />
            </div>
        </>
    );
}

<script module lang="ts">
    import { index } from '@/routes/household/accounts';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Accounts',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, Link, router } from '@inertiajs/svelte';
    import AccountController from '@/actions/App/Http/Controllers/Household/AccountController';
    import ActionableEmptyState from '@/components/ActionableEmptyState.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import { index as assumptions } from '@/routes/household/assumptions';
    import { index as reconcile } from '@/routes/household/reconcile';
    import { index as registered } from '@/routes/household/registered';

    type Account = {
        id: string;
        name: string;
        institution: string | null;
        type: string;
        type_label: string;
        currency: string;
        owner_user_id: number | null;
        owner: string | null;
        starting_balance: number;
        starting_balance_formatted: string;
        starting_balance_date: string;
        archived: boolean;
    };

    let {
        accounts,
        archivedAccounts,
        members,
        types,
        defaults,
    }: {
        accounts: Account[];
        archivedAccounts: Account[];
        members: { id: number; name: string }[];
        types: { value: string; label: string }[];
        defaults: { starting_balance_date: string };
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    let showForm = $state(false);
    let showArchived = $state(false);
    let editingId = $state<string | null>(null);

    type EditForm = {
        name: string;
        institution: string;
        type: string;
        owner_user_id: string;
        starting_balance: number;
        starting_balance_date: string;
    };

    let editForm = $state<EditForm>({
        name: '',
        institution: '',
        type: 'chequing',
        owner_user_id: '',
        starting_balance: 0,
        starting_balance_date: '',
    });
    let editErrors = $state<Record<string, string>>({});

    function startEdit(account: Account): void {
        editingId = account.id;
        editErrors = {};
        editForm = {
            name: account.name,
            institution: account.institution ?? '',
            type: account.type,
            owner_user_id: account.owner_user_id?.toString() ?? '',
            starting_balance: account.starting_balance,
            starting_balance_date: account.starting_balance_date,
        };
    }

    function saveEdit(): void {
        if (editingId === null) {
            return;
        }

        router.patch(
            AccountController.update(editingId).url,
            {
                name: editForm.name,
                institution: editForm.institution || null,
                type: editForm.type,
                owner_user_id: editForm.owner_user_id ? Number(editForm.owner_user_id) : null,
                starting_balance: Number(editForm.starting_balance),
                starting_balance_date: editForm.starting_balance_date,
            },
            {
                preserveScroll: true,
                onError: (errors) => (editErrors = errors),
                onSuccess: () => {
                    editingId = null;
                    editErrors = {};
                },
            },
        );
    }

    function archive(account: Account): void {
        router.post(AccountController.archive(account.id).url, {}, { preserveScroll: true });
    }

    function restore(account: Account): void {
        router.post(AccountController.restore(account.id).url, {}, { preserveScroll: true });
    }

    function move(indexPosition: number, direction: -1 | 1): void {
        const next = indexPosition + direction;

        if (next < 0 || next >= accounts.length) {
            return;
        }

        const ids = accounts.map((account) => account.id);
        [ids[indexPosition], ids[next]] = [ids[next], ids[indexPosition]];

        router.post(AccountController.reorder.url(), { ids }, { preserveScroll: true });
    }
</script>

<AppHead title="Accounts" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading title="Accounts" />
        <div class="flex gap-2">
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(reconcile())} class={props.class}>Reconcile</Link>
                {/snippet}
            </Button>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(registered())} class={props.class}>Registered</Link>
                {/snippet}
            </Button>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(assumptions())} class={props.class}>Assumptions</Link>
                {/snippet}
            </Button>
            <Button
                type="button"
                data-test="open-create-account"
                onclick={() => (showForm = true)}
            >
                Add account
            </Button>
        </div>
    </div>

    {#if accounts.length === 0 && !showForm}
        <ActionableEmptyState
            title="No accounts yet"
            description="Add a chequing, savings, credit card, or registered account (TFSA, RRSP, FHSA, RESP)."
        >
            {#snippet action()}
                <Button
                    type="button"
                    data-test="empty-create-account"
                    onclick={() => (showForm = true)}
                >
                    Add account
                </Button>
            {/snippet}
        </ActionableEmptyState>
    {:else if accounts.length > 0}
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Owner</th>
                        <th class="px-4 py-3 font-medium">Institution</th>
                        <th class="px-4 py-3 text-right font-medium">Starting balance</th>
                        <th class="px-4 py-3 font-medium">Currency</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {#each accounts as account, i (account.id)}
                        <tr class="border-t" data-test="account-row">
                            <td class="px-4 py-3">{account.name}</td>
                            <td class="px-4 py-3">{account.type_label}</td>
                            <td class="px-4 py-3">{account.owner ?? 'Joint'}</td>
                            <td class="px-4 py-3">{account.institution ?? '—'}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {account.starting_balance_formatted}
                            </td>
                            <td class="px-4 py-3">{account.currency}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button type="button" variant="ghost" onclick={() => move(i, -1)} disabled={i === 0}>
                                        ↑
                                    </Button>
                                    <Button type="button" variant="ghost" onclick={() => move(i, 1)} disabled={i === accounts.length - 1}>
                                        ↓
                                    </Button>
                                    <Button type="button" variant="ghost" onclick={() => startEdit(account)}>
                                        Edit
                                    </Button>
                                    <Button type="button" variant="ghost" onclick={() => archive(account)}>
                                        Archive
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

    {#if editingId !== null}
        <div class="max-w-lg space-y-4 rounded-xl border p-4" data-test="edit-account">
            <h2 class="font-semibold">Edit account</h2>
            <ErrorSummary
                errors={Object.entries(editErrors).map(([fieldId, message]) => ({ fieldId, message }))}
            />
            <div class="grid gap-2">
                <Label for="edit-name">Name</Label>
                <Input id="edit-name" bind:value={editForm.name} />
                <InputError message={editErrors.name} />
            </div>
            <div class="grid gap-2">
                <Label for="edit-institution">Institution</Label>
                <Input id="edit-institution" bind:value={editForm.institution} />
            </div>
            <div class="grid gap-2">
                <Label for="edit-type">Type</Label>
                <select id="edit-type" class={selectClass} bind:value={editForm.type}>
                    {#each types as type (type.value)}
                        <option value={type.value}>{type.label}</option>
                    {/each}
                </select>
                <InputError message={editErrors.type} />
            </div>
            <div class="grid gap-2">
                <Label for="edit-owner">Owner</Label>
                <select id="edit-owner" class={selectClass} bind:value={editForm.owner_user_id}>
                    <option value="">Joint (no single owner)</option>
                    {#each members as member (member.id)}
                        <option value={member.id.toString()}>{member.name}</option>
                    {/each}
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="edit-balance">Starting balance (cents)</Label>
                <Input id="edit-balance" type="number" step="1" bind:value={editForm.starting_balance} />
                <InputError message={editErrors.starting_balance} />
            </div>
            <div class="grid gap-2">
                <Label for="edit-balance-date">Starting balance date</Label>
                <Input id="edit-balance-date" type="date" bind:value={editForm.starting_balance_date} />
                <InputError message={editErrors.starting_balance_date} />
            </div>
            <div class="flex justify-end gap-2">
                <Button type="button" variant="ghost" onclick={() => (editingId = null)}>Cancel</Button>
                <Button type="button" onclick={saveEdit} data-test="save-account">Save</Button>
            </div>
        </div>
    {/if}

    {#if showForm}
        <Form
            {...AccountController.store.form()}
            class="max-w-lg space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add account</h2>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />

                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" name="name" required placeholder="Chequing" />
                    <InputError message={errors.name} />
                </div>

                <div class="grid gap-2">
                    <Label for="institution">Institution (optional)</Label>
                    <Input id="institution" name="institution" placeholder="RBC" />
                    <InputError message={errors.institution} />
                </div>

                <div class="grid gap-2">
                    <Label for="type">Type</Label>
                    <select id="type" name="type" class={selectClass}>
                        {#each types as type (type.value)}
                            <option value={type.value}>{type.label}</option>
                        {/each}
                    </select>
                    <InputError message={errors.type} />
                </div>

                <div class="grid gap-2">
                    <Label for="owner_user_id">Owner</Label>
                    <select id="owner_user_id" name="owner_user_id" class={selectClass}>
                        <option value="">Joint (no single owner)</option>
                        {#each members as member (member.id)}
                            <option value={member.id}>{member.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.owner_user_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="starting_balance">Starting balance (cents)</Label>
                    <Input
                        id="starting_balance"
                        name="starting_balance"
                        type="number"
                        step="1"
                        value="0"
                    />
                    <InputError message={errors.starting_balance} />
                </div>

                <div class="grid gap-2">
                    <Label for="starting_balance_date">Starting balance date</Label>
                    <Input
                        id="starting_balance_date"
                        name="starting_balance_date"
                        type="date"
                        required
                        value={defaults.starting_balance_date}
                    />
                    <InputError message={errors.starting_balance_date} />
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        onclick={() => (showForm = false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="submit-create-account"
                    >
                        Create
                    </Button>
                </div>
            {/snippet}
        </Form>
    {/if}

    {#if archivedAccounts.length > 0}
        <div class="rounded-xl border p-4">
            <button
                type="button"
                class="flex w-full items-center justify-between text-sm font-medium"
                onclick={() => (showArchived = !showArchived)}
            >
                <span>Archived accounts ({archivedAccounts.length})</span>
                <span>{showArchived ? '▲' : '▼'}</span>
            </button>
            {#if showArchived}
                <ul class="mt-3 flex flex-col gap-2 text-sm">
                    {#each archivedAccounts as account (account.id)}
                        <li class="flex items-center justify-between gap-4 border-t pt-2">
                            <span>{account.name} · {account.type_label}</span>
                            <Button type="button" variant="outline" onclick={() => restore(account)}>
                                Restore
                            </Button>
                        </li>
                    {/each}
                </ul>
            {/if}
        </div>
    {/if}
</div>

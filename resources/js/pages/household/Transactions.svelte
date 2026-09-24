<script module lang="ts">
    import { index } from '@/routes/household/transactions';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Transactions',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, Link, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import TransactionController from '@/actions/App/Http/Controllers/Household/TransactionController';
    import ActionableEmptyState from '@/components/ActionableEmptyState.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import { index as goals } from '@/routes/household/goals';
    import { index as importCsv } from '@/routes/household/import';
    import { index as recurring } from '@/routes/household/recurring';
    import { index as reports } from '@/routes/household/reports';
    import { index as rules } from '@/routes/household/rules';
    import { index as transfers } from '@/routes/household/transfers';
    import { TransactionEntryState } from './TransactionEntryState.svelte';

    type Option = { id: string; name: string };

    type Row = {
        id: string;
        date: string;
        account_id: string | null;
        account: string | null;
        payee: string;
        category_id: string | null;
        category: string | null;
        bucket_id: string | null;
        bucket: string | null;
        amount: number;
        amount_formatted: string;
        memo: string | null;
        cleared: boolean;
        reviewed: boolean;
        assignee: string | null;
        source: string;
        is_split: boolean;
    };

    type Paginator = {
        data: Row[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };

    type Filters = {
        date_from: string | null;
        date_to: string | null;
        account_id: string | null;
        category_id: string | null;
        bucket_id: string | null;
        source: string | null;
        payee: string | null;
        amount_min: number | null;
        amount_max: number | null;
        cleared: boolean | null;
        reviewed: boolean | null;
    };

    let {
        transactions,
        filters,
        accounts,
        categories,
        buckets,
        sources,
        activities,
        currentUserId,
        defaults,
    }: {
        transactions: Paginator;
        filters: Filters;
        accounts: Option[];
        categories: Option[];
        buckets: Option[];
        sources: string[];
        activities: { id: string; action: string; description: string; user: string | null; at: string | null }[];
        currentUserId: number;
        defaults: { date: string };
    } = $props();

    function assignToMe(row: Row): void {
        router.post(
            TransactionController.assign(row.id).url,
            { assignee_id: currentUserId },
            { preserveScroll: true },
        );
    }

    const entry = new TransactionEntryState();
    let showForm = $state(false);
    let showActivity = $state(false);
    let selected = $state<Set<string>>(new Set());
    let editingId = $state<string | null>(null);

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    const triState = (value: boolean | null): string =>
        value === null ? '' : value ? '1' : '0';

    // Local filter form seeded once from the server's applied filters.
    let filterForm = $state(
        untrack(() => ({
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
            account_id: filters.account_id ?? '',
            category_id: filters.category_id ?? '',
            bucket_id: filters.bucket_id ?? '',
            source: filters.source ?? '',
            payee: filters.payee ?? '',
            amount_min: filters.amount_min?.toString() ?? '',
            amount_max: filters.amount_max?.toString() ?? '',
            cleared: triState(filters.cleared),
            reviewed: triState(filters.reviewed),
        })),
    );

    function applyFilters(): void {
        const query: Record<string, string> = {};

        for (const [key, value] of Object.entries(filterForm)) {
            if (value !== '') {
                query[key] = value;
            }
        }

        router.get(index().url, query, { preserveScroll: true, preserveState: true, replace: true });
    }

    function clearFilters(): void {
        filterForm = {
            date_from: '',
            date_to: '',
            account_id: '',
            category_id: '',
            bucket_id: '',
            source: '',
            payee: '',
            amount_min: '',
            amount_max: '',
            cleared: '',
            reviewed: '',
        };
        router.get(index().url, {}, { preserveScroll: true, replace: true });
    }

    function toggleSelected(id: string): void {
        const next = new Set(selected);
        next.has(id) ? next.delete(id) : next.add(id);
        selected = next;
    }

    function bulkReview(reviewed: boolean): void {
        router.post(
            TransactionController.review.url(),
            { ids: [...selected], reviewed },
            { preserveScroll: true, onSuccess: () => (selected = new Set()) },
        );
    }

    type EditForm = {
        date: string;
        account_id: string;
        payee: string;
        category_id: string;
        bucket_id: string;
        amount: number;
        memo: string;
        cleared: boolean;
        reviewed: boolean;
    };

    let editForm = $state<EditForm>({
        date: '',
        account_id: '',
        payee: '',
        category_id: '',
        bucket_id: '',
        amount: 0,
        memo: '',
        cleared: false,
        reviewed: false,
    });
    let editErrors = $state<Record<string, string>>({});

    function startEdit(row: Row): void {
        editingId = row.id;
        editErrors = {};
        splitMode = false;
        splitRows = [];
        splitAmount = row.amount;
        editForm = {
            date: row.date,
            account_id: row.account_id ?? '',
            payee: row.payee,
            category_id: row.category_id ?? '',
            bucket_id: row.bucket_id ?? '',
            amount: row.amount,
            memo: row.memo ?? '',
            cleared: row.cleared,
            reviewed: row.reviewed,
        };
    }

    function saveEdit(): void {
        if (editingId === null) {
            return;
        }

        router.patch(
            TransactionController.update(editingId).url,
            {
                date: editForm.date,
                account_id: editForm.account_id,
                payee: editForm.payee,
                category_id: editForm.category_id || null,
                bucket_id: editForm.bucket_id || null,
                amount: Number(editForm.amount),
                memo: editForm.memo || null,
                cleared: editForm.cleared,
                reviewed: editForm.reviewed,
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

    function destroy(row: Row): void {
        if (!confirm(`Delete "${row.payee}" (${row.amount_formatted})?`)) {
            return;
        }

        router.delete(TransactionController.destroy(row.id).url, { preserveScroll: true });
    }

    // Split editing for the row currently open in the edit panel.
    type SplitAllocation = { bucket_id: string; category_id: string; amount: number };
    let splitMode = $state(false);
    let splitRows = $state<SplitAllocation[]>([]);
    let splitAmount = $state(0);

    function beginSplit(): void {
        splitMode = true;
        if (splitRows.length === 0) {
            splitRows = [{ bucket_id: '', category_id: '', amount: splitAmount }];
        }
    }

    function addAllocation(): void {
        splitRows = [...splitRows, { bucket_id: '', category_id: '', amount: 0 }];
    }

    function removeAllocation(i: number): void {
        splitRows = splitRows.filter((_, index) => index !== i);
    }

    const splitTotal = $derived(splitRows.reduce((sum, s) => sum + (Number(s.amount) || 0), 0));

    function saveSplit(): void {
        if (editingId === null) {
            return;
        }

        router.post(
            TransactionController.split(editingId).url,
            {
                splits: splitMode
                    ? splitRows.map((s) => ({
                          bucket_id: s.bucket_id,
                          category_id: s.category_id || null,
                          amount: Number(s.amount),
                      }))
                    : [],
            },
            {
                preserveScroll: true,
                onError: (errors) => (editErrors = errors),
                onSuccess: () => {
                    editingId = null;
                    splitMode = false;
                    splitRows = [];
                    editErrors = {};
                },
            },
        );
    }
</script>

<AppHead title="Transactions" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between gap-4">
        <Heading title="Transactions" />
        <div class="flex gap-2">
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(importCsv())} class={props.class}
                        >Import CSV</Link
                    >
                {/snippet}
            </Button>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(rules())} class={props.class}>Rules</Link>
                {/snippet}
            </Button>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(recurring())} class={props.class}>Recurring</Link>
                {/snippet}
            </Button>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(goals())} class={props.class}>Goals</Link>
                {/snippet}
            </Button>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(reports())} class={props.class}>Reports</Link>
                {/snippet}
            </Button>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(transfers())} class={props.class}
                        >Transfers</Link
                    >
                {/snippet}
            </Button>
            <Button
                type="button"
                data-test="open-create-transaction"
                onclick={() => (showForm = true)}
            >
                Add transaction
            </Button>
        </div>
    </div>

    <!-- Filters -->
    <div class="grid gap-3 rounded-xl border p-4 md:grid-cols-4" data-test="filters">
        <div class="grid gap-1">
            <Label for="f-payee">Payee</Label>
            <Input id="f-payee" bind:value={filterForm.payee} placeholder="Search payee" />
        </div>
        <div class="grid gap-1">
            <Label for="f-account">Account</Label>
            <select id="f-account" class={selectClass} bind:value={filterForm.account_id}>
                <option value="">All accounts</option>
                {#each accounts as account (account.id)}
                    <option value={account.id}>{account.name}</option>
                {/each}
            </select>
        </div>
        <div class="grid gap-1">
            <Label for="f-category">Category</Label>
            <select id="f-category" class={selectClass} bind:value={filterForm.category_id}>
                <option value="">All categories</option>
                {#each categories as category (category.id)}
                    <option value={category.id}>{category.name}</option>
                {/each}
            </select>
        </div>
        <div class="grid gap-1">
            <Label for="f-bucket">Bucket</Label>
            <select id="f-bucket" class={selectClass} bind:value={filterForm.bucket_id}>
                <option value="">All buckets</option>
                {#each buckets as bucket (bucket.id)}
                    <option value={bucket.id}>{bucket.name}</option>
                {/each}
            </select>
        </div>
        <div class="grid gap-1">
            <Label for="f-from">From</Label>
            <Input id="f-from" type="date" bind:value={filterForm.date_from} />
        </div>
        <div class="grid gap-1">
            <Label for="f-to">To</Label>
            <Input id="f-to" type="date" bind:value={filterForm.date_to} />
        </div>
        <div class="grid gap-1">
            <Label for="f-min">Min amount (cents)</Label>
            <Input id="f-min" type="number" step="1" bind:value={filterForm.amount_min} />
        </div>
        <div class="grid gap-1">
            <Label for="f-max">Max amount (cents)</Label>
            <Input id="f-max" type="number" step="1" bind:value={filterForm.amount_max} />
        </div>
        <div class="grid gap-1">
            <Label for="f-source">Source</Label>
            <select id="f-source" class={selectClass} bind:value={filterForm.source}>
                <option value="">All sources</option>
                {#each sources as source (source)}
                    <option value={source}>{source}</option>
                {/each}
            </select>
        </div>
        <div class="grid gap-1">
            <Label for="f-cleared">Cleared</Label>
            <select id="f-cleared" class={selectClass} bind:value={filterForm.cleared}>
                <option value="">Any</option>
                <option value="1">Cleared</option>
                <option value="0">Uncleared</option>
            </select>
        </div>
        <div class="grid gap-1">
            <Label for="f-reviewed">Reviewed</Label>
            <select id="f-reviewed" class={selectClass} bind:value={filterForm.reviewed}>
                <option value="">Any</option>
                <option value="1">Reviewed</option>
                <option value="0">Needs review</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <Button type="button" onclick={applyFilters} data-test="apply-filters">Apply</Button>
            <Button type="button" variant="ghost" onclick={clearFilters}>Clear</Button>
        </div>
    </div>

    {#if selected.size > 0}
        <div class="flex items-center gap-3 rounded-lg border bg-muted/40 p-3 text-sm">
            <span>{selected.size} selected</span>
            <Button type="button" variant="outline" onclick={() => bulkReview(true)} data-test="bulk-reviewed">
                Mark reviewed
            </Button>
            <Button type="button" variant="ghost" onclick={() => bulkReview(false)}>
                Mark needs review
            </Button>
        </div>
    {/if}

    {#if transactions.data.length === 0 && !showForm}
        <ActionableEmptyState
            title="No transactions found"
            description="Add one manually, or adjust the filters above. Transfers live on their own page."
        >
            {#snippet action()}
                <Button
                    type="button"
                    data-test="empty-create-transaction"
                    onclick={() => (showForm = true)}
                >
                    Add transaction
                </Button>
            {/snippet}
        </ActionableEmptyState>
    {:else if transactions.data.length > 0}
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-3 py-3"></th>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Account</th>
                        <th class="px-4 py-3 font-medium">Payee</th>
                        <th class="px-4 py-3 font-medium">Category</th>
                        <th class="px-4 py-3 font-medium">Bucket</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {#each transactions.data as transaction (transaction.id)}
                        <tr class="border-t">
                            <td class="px-3 py-3">
                                <input
                                    type="checkbox"
                                    checked={selected.has(transaction.id)}
                                    onchange={() => toggleSelected(transaction.id)}
                                    aria-label="Select transaction"
                                    data-test="select-row"
                                />
                            </td>
                            <td class="px-4 py-3">{transaction.date}</td>
                            <td class="px-4 py-3">{transaction.account ?? '—'}</td>
                            <td class="px-4 py-3">{transaction.payee}</td>
                            <td class="px-4 py-3">{transaction.category ?? '—'}</td>
                            <td class="px-4 py-3">{transaction.bucket ?? '—'}</td>
                            <td class="px-4 py-3 text-right font-mono">{transaction.amount_formatted}</td>
                            <td class="px-4 py-3">
                                <span class="flex gap-1 text-xs">
                                    {#if transaction.cleared}
                                        <span class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800">Cleared</span>
                                    {/if}
                                    {#if transaction.reviewed}
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800">Reviewed</span>
                                    {:else}
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">Needs review</span>
                                    {/if}
                                    {#if transaction.assignee}
                                        <span class="rounded-full bg-violet-100 px-2 py-0.5 text-violet-800">→ {transaction.assignee}</span>
                                    {/if}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="ghost" onclick={() => startEdit(transaction)}>
                                        Edit
                                    </Button>
                                    <Button type="button" variant="ghost" onclick={() => assignToMe(transaction)} data-test="assign-to-me">
                                        Assign me
                                    </Button>
                                    <Button type="button" variant="ghost" onclick={() => destroy(transaction)}>
                                        Delete
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between text-sm text-muted-foreground">
            <span>Showing {transactions.from ?? 0}–{transactions.to ?? 0} of {transactions.total}</span>
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={!transactions.prev_page_url}
                    onclick={() => transactions.prev_page_url && router.get(transactions.prev_page_url, {}, { preserveScroll: true })}
                >
                    Previous
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!transactions.next_page_url}
                    onclick={() => transactions.next_page_url && router.get(transactions.next_page_url, {}, { preserveScroll: true })}
                >
                    Next
                </Button>
            </div>
        </div>
    {/if}

    <Dialog
        open={editingId !== null}
        onOpenChange={(value) => {
            if (!value) {
                editingId = null;
            }
        }}
    >
        <DialogContent class="max-h-[85vh] overflow-y-auto">
            <DialogTitle>Edit transaction</DialogTitle>
            <div class="mt-4 space-y-4" data-test="edit-transaction">
            <ErrorSummary
                errors={Object.entries(editErrors).map(([fieldId, message]) => ({ fieldId, message }))}
            />
            <div class="grid gap-2">
                <Label for="edit-date">Date</Label>
                <Input id="edit-date" type="date" bind:value={editForm.date} />
                <InputError message={editErrors.date} />
            </div>
            <div class="grid gap-2">
                <Label for="edit-account">Account</Label>
                <select id="edit-account" class={selectClass} bind:value={editForm.account_id}>
                    {#each accounts as account (account.id)}
                        <option value={account.id}>{account.name}</option>
                    {/each}
                </select>
                <InputError message={editErrors.account_id} />
            </div>
            <div class="grid gap-2">
                <Label for="edit-payee">Payee</Label>
                <Input id="edit-payee" bind:value={editForm.payee} />
                <InputError message={editErrors.payee} />
            </div>
            <div class="grid gap-2">
                <Label for="edit-category">Category</Label>
                <select id="edit-category" class={selectClass} bind:value={editForm.category_id}>
                    <option value="">(none)</option>
                    {#each categories as category (category.id)}
                        <option value={category.id}>{category.name}</option>
                    {/each}
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="edit-bucket">Bucket</Label>
                <select id="edit-bucket" class={selectClass} bind:value={editForm.bucket_id}>
                    <option value="">(none)</option>
                    {#each buckets as bucket (bucket.id)}
                        <option value={bucket.id}>{bucket.name}</option>
                    {/each}
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="edit-amount">Amount (cents, negative for spend)</Label>
                <Input id="edit-amount" type="number" step="1" bind:value={editForm.amount} />
                <InputError message={editErrors.amount} />
            </div>
            <div class="grid gap-2">
                <Label for="edit-memo">Memo</Label>
                <Input id="edit-memo" bind:value={editForm.memo} />
            </div>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" bind:checked={editForm.cleared} /> Cleared
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" bind:checked={editForm.reviewed} /> Reviewed
                </label>
            </div>
            <div class="rounded-lg border border-dashed p-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium">Split across buckets</span>
                    {#if !splitMode}
                        <Button type="button" variant="outline" onclick={beginSplit} data-test="begin-split">
                            Split
                        </Button>
                    {/if}
                </div>
                {#if splitMode}
                    <InputError message={editErrors.splits} />
                    {#each splitRows as split, i (i)}
                        <div class="mt-2 grid items-end gap-2 md:grid-cols-[1fr_1fr_110px_auto]">
                            <select class={selectClass} bind:value={split.bucket_id}>
                                <option value="">Bucket</option>
                                {#each buckets as bucket (bucket.id)}
                                    <option value={bucket.id}>{bucket.name}</option>
                                {/each}
                            </select>
                            <select class={selectClass} bind:value={split.category_id}>
                                <option value="">(no category)</option>
                                {#each categories as category (category.id)}
                                    <option value={category.id}>{category.name}</option>
                                {/each}
                            </select>
                            <Input type="number" step="1" bind:value={split.amount} />
                            <Button type="button" variant="ghost" onclick={() => removeAllocation(i)}>Remove</Button>
                        </div>
                    {/each}
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <Button type="button" variant="outline" onclick={addAllocation}>Add allocation</Button>
                        <span class={splitTotal === Number(editForm.amount) ? 'text-muted-foreground' : 'text-red-600'}>
                            Allocated {splitTotal} / {editForm.amount} cents
                        </span>
                    </div>
                    <div class="mt-2 flex justify-end">
                        <Button type="button" onclick={saveSplit} data-test="save-split">Save split</Button>
                    </div>
                {/if}
            </div>

            </div>
            <DialogFooter>
                <Button type="button" variant="ghost" onclick={() => (editingId = null)}>Cancel</Button>
                <Button type="button" onclick={saveEdit} data-test="save-edit">Save</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    {#if showForm}
        <Form
            {...TransactionController.store.form()}
            class="max-w-lg space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add transaction</h2>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />

                <div class="grid gap-2">
                    <Label for="date">Date</Label>
                    <Input
                        id="date"
                        name="date"
                        type="date"
                        required
                        value={defaults.date}
                    />
                    <InputError message={errors.date} />
                </div>

                <div class="grid gap-2">
                    <Label for="account_id">Account</Label>
                    <select
                        id="account_id"
                        name="account_id"
                        required
                        class={selectClass}
                    >
                        <option value="">Select an account</option>
                        {#each accounts as account (account.id)}
                            <option value={account.id}>{account.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.account_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="payee">Payee</Label>
                    <Input
                        id="payee"
                        name="payee"
                        required
                        placeholder="Loblaws"
                        onblur={(event) =>
                            entry.suggest(event.currentTarget.value)}
                    />
                    {#if entry.suggestedRuleId}
                        <p class="text-sm text-muted-foreground">
                            Prefilled from payee rule (override below if needed).
                        </p>
                    {/if}
                    <InputError message={errors.payee} />
                </div>

                <div class="grid gap-2">
                    <Label for="category_id">Category</Label>
                    <select
                        id="category_id"
                        name="category_id"
                        class={selectClass}
                        bind:value={entry.categoryId}
                        onchange={() => entry.touchCategory()}
                    >
                        <option value="">(none)</option>
                        {#each categories as category (category.id)}
                            <option value={category.id}>{category.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.category_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="bucket_id">Bucket</Label>
                    <select
                        id="bucket_id"
                        name="bucket_id"
                        class={selectClass}
                        bind:value={entry.bucketId}
                        onchange={() => entry.touchBucket()}
                    >
                        <option value="">(none)</option>
                        {#each buckets as bucket (bucket.id)}
                            <option value={bucket.id}>{bucket.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.bucket_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="amount">Amount (cents, negative for spend)</Label>
                    <Input id="amount" name="amount" type="number" step="1" required />
                    <InputError message={errors.amount} />
                </div>

                <div class="grid gap-2">
                    <Label for="memo">Memo (optional)</Label>
                    <Input id="memo" name="memo" />
                    <InputError message={errors.memo} />
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
                        data-test="submit-create-transaction"
                    >
                        Create
                    </Button>
                </div>
            {/snippet}
        </Form>
    {/if}

    <!-- Activity trail -->
    <div class="rounded-xl border p-4">
        <button
            type="button"
            class="flex w-full items-center justify-between text-sm font-medium"
            onclick={() => (showActivity = !showActivity)}
        >
            <span>Activity ({activities.length})</span>
            <span>{showActivity ? '▲' : '▼'}</span>
        </button>
        {#if showActivity}
            {#if activities.length === 0}
                <p class="mt-3 text-sm text-muted-foreground">No activity yet.</p>
            {:else}
                <ul class="mt-3 flex flex-col gap-2 text-sm">
                    {#each activities as activity (activity.id)}
                        <li class="flex justify-between gap-4 border-t pt-2">
                            <span>{activity.description}</span>
                            <span class="shrink-0 text-muted-foreground">
                                {activity.user ?? 'Someone'} · {activity.at ?? ''}
                            </span>
                        </li>
                    {/each}
                </ul>
            {/if}
        {/if}
    </div>
</div>

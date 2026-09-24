<script module lang="ts">
    import { index } from '@/routes/household/import';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Import',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import CategoryController from '@/actions/App/Http/Controllers/Household/CategoryController';
    import ImportController from '@/actions/App/Http/Controllers/Household/ImportController';
    import PayeeRuleController from '@/actions/App/Http/Controllers/Household/PayeeRuleController';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogFooter,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import InputError from '@/components/InputError.svelte';
    import { formatCents } from '@/lib/importCsv';

    const CATEGORY_TYPES = [
        'income',
        'housing',
        'transportation',
        'food',
        'household',
        'personal',
        'health',
        'debt',
        'savings',
        'fees',
        'other',
    ] as const;

    type Option = { id: string; name: string };

    type ServerSplit = {
        category_id: string | null;
        bucket_id: string;
        amount: number;
        memo: string | null;
    };

    type ServerRow = {
        id: string;
        date: string;
        payee: string;
        raw_payee: string;
        amount: number;
        amount_formatted: string;
        status: string;
        accept: boolean;
        is_possible_duplicate: boolean;
        duplicate_reason: string | null;
        is_split: boolean;
        final_category_id: string | null;
        final_bucket_id: string | null;
        splits: ServerSplit[];
    };

    type EditSplit = { bucket_id: string; category_id: string; amount: number; memo: string };

    type EditRow = {
        id: string;
        date: string;
        payee: string;
        amount: number;
        amountFormatted: string;
        rawPayee: string;
        status: string;
        accept: boolean;
        isPossibleDuplicate: boolean;
        duplicateReason: string | null;
        categoryId: string;
        bucketId: string;
        isSplit: boolean;
        splits: EditSplit[];
    };

    let {
        upload,
        rows,
        categories,
        buckets,
    }: {
        upload: {
            id: string;
            filename: string;
            status: string;
            parsed_count: number;
            imported_count: number;
        };
        rows: ServerRow[];
        categories: Option[];
        buckets: Option[];
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    function toEdit(row: ServerRow): EditRow {
        return {
            id: row.id,
            date: row.date,
            payee: row.payee,
            amount: row.amount,
            amountFormatted: row.amount_formatted,
            rawPayee: row.raw_payee,
            status: row.status,
            accept: row.accept,
            isPossibleDuplicate: row.is_possible_duplicate,
            duplicateReason: row.duplicate_reason,
            categoryId: row.final_category_id ?? '',
            bucketId: row.final_bucket_id ?? '',
            isSplit: row.is_split,
            splits: row.splits.map((split) => ({
                bucket_id: split.bucket_id,
                category_id: split.category_id ?? '',
                amount: split.amount,
                memo: split.memo ?? '',
            })),
        };
    }

    function signature(serverRows: ServerRow[]): string {
        return serverRows.map((row) => `${row.id}:${row.status}`).join('|');
    }

    let editRows = $state<EditRow[]>(untrack(() => rows.map(toEdit)));
    let errors = $state<Record<string, string>>({});
    let processing = $state(false);
    let lastSignature = untrack(() => signature(rows));

    // Re-seed local edits only when the server rows actually change (after a save
    // or promotion round-trip), never mid-edit.
    $effect(() => {
        const current = signature(rows);

        untrack(() => {
            if (current !== lastSignature) {
                lastSignature = current;
                editRows = rows.map(toEdit);
            }
        });
    });

    function splitSum(row: EditRow): number {
        return row.splits.reduce((total, split) => total + (Number(split.amount) || 0), 0);
    }

    function toggleSplit(row: EditRow): void {
        row.isSplit = !row.isSplit;

        if (row.isSplit && row.splits.length === 0) {
            row.splits = [{ bucket_id: '', category_id: '', amount: row.amount, memo: '' }];
        }

        if (!row.isSplit) {
            row.splits = [];
        }
    }

    function addSplit(row: EditRow): void {
        row.splits = [...row.splits, { bucket_id: '', category_id: '', amount: 0, memo: '' }];
    }

    function removeSplit(row: EditRow, index: number): void {
        row.splits = row.splits.filter((_, i) => i !== index);

        if (row.splits.length === 0) {
            row.isSplit = false;
        }
    }

    function toggleReject(row: EditRow): void {
        if (row.status === 'rejected') {
            row.status = 'pending';
        } else {
            row.status = 'rejected';
            row.accept = false;
        }
    }

    const pendingRows = $derived(editRows.filter((row) => row.status !== 'promoted'));
    const promotedCount = $derived(rows.filter((row) => row.status === 'promoted').length);

    type StatusFilter = 'all' | 'pending' | 'promoted' | 'rejected';

    const FILTERS: { value: StatusFilter; label: string }[] = [
        { value: 'all', label: 'All' },
        { value: 'pending', label: 'Pending' },
        { value: 'promoted', label: 'Promoted' },
        { value: 'rejected', label: 'Rejected' },
    ];

    let statusFilter = $state<StatusFilter>('all');

    const counts = $derived({
        all: editRows.length,
        pending: editRows.filter((row) => row.status === 'pending').length,
        promoted: editRows.filter((row) => row.status === 'promoted').length,
        rejected: editRows.filter((row) => row.status === 'rejected').length,
    });

    const visibleRows = $derived(
        statusFilter === 'all'
            ? editRows
            : editRows.filter((row) => row.status === statusFilter),
    );

    function save(): void {
        processing = true;

        const payload = pendingRows.map((row) => ({
            id: row.id,
            date: row.date,
            payee: row.payee,
            amount: Number(row.amount),
            final_category_id: row.isSplit ? null : row.categoryId || null,
            final_bucket_id: row.isSplit ? null : row.bucketId || null,
            accept: row.accept,
            status: row.status,
            splits: row.isSplit
                ? row.splits.map((split) => ({
                      bucket_id: split.bucket_id,
                      category_id: split.category_id || null,
                      amount: Number(split.amount),
                      memo: split.memo || null,
                  }))
                : [],
        }));

        router.patch(
            ImportController.updateStaged(upload.id).url,
            { rows: payload },
            {
                preserveScroll: true,
                onError: (formErrors) => (errors = formErrors),
                onSuccess: () => (errors = {}),
                onFinish: () => (processing = false),
            },
        );
    }

    function promote(): void {
        processing = true;

        router.post(
            ImportController.promote(upload.id).url,
            {},
            {
                preserveScroll: true,
                onError: (formErrors) => (errors = formErrors),
                onFinish: () => (processing = false),
            },
        );
    }

    let showCategoryModal = $state(false);
    let newCategoryName = $state('');
    let newCategoryType = $state<(typeof CATEGORY_TYPES)[number]>('other');
    let creatingCategory = $state(false);
    let categoryError = $state<string | null>(null);

    function openCategoryModal(): void {
        newCategoryName = '';
        newCategoryType = 'other';
        categoryError = null;
        showCategoryModal = true;
    }

    // Create a category inline; preserveState keeps the in-progress row edits, and the
    // refreshed `categories` prop makes the new option appear in every dropdown.
    function createCategory(): void {
        creatingCategory = true;
        categoryError = null;

        router.post(
            CategoryController.store.url(),
            { name: newCategoryName, type: newCategoryType },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    showCategoryModal = false;
                },
                onError: (formErrors) => {
                    categoryError =
                        formErrors.name ??
                        formErrors.type ??
                        'Could not create the category.';
                },
                onFinish: () => (creatingCategory = false),
            },
        );
    }

    let showRuleModal = $state(false);
    let rulePattern = $state('');
    let ruleCategoryId = $state('');
    let ruleBucketId = $state('');
    let creatingRule = $state(false);
    let ruleError = $state<string | null>(null);

    function openRuleModal(row: EditRow): void {
        // Prefill the rule from the row: match on its payee, reuse its category/bucket.
        rulePattern = row.payee;
        ruleCategoryId = row.categoryId;
        ruleBucketId = row.bucketId;
        ruleError = null;
        showRuleModal = true;
    }

    // Apply the just-created rule to the other pending rows in this import so matching
    // merchants categorize immediately (the reviewer still saves to persist).
    function applyRuleToPendingRows(): void {
        const needle = rulePattern.trim().toLowerCase();

        if (needle === '') {
            return;
        }

        for (const row of editRows) {
            if (row.status !== 'pending' || row.isSplit) {
                continue;
            }

            if (row.payee.toLowerCase().includes(needle)) {
                if (ruleCategoryId) {
                    row.categoryId = ruleCategoryId;
                }

                if (ruleBucketId) {
                    row.bucketId = ruleBucketId;
                }
            }
        }
    }

    function createRule(): void {
        creatingRule = true;
        ruleError = null;

        router.post(
            PayeeRuleController.storeInline.url(),
            {
                name: null,
                pattern: rulePattern,
                enabled: true,
                category_id: ruleCategoryId || null,
                bucket_id: ruleBucketId || null,
                rename_to: null,
                hide_from_reports: false,
                mark_for_review: false,
                auto_apply: true,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    applyRuleToPendingRows();
                    showRuleModal = false;
                },
                onError: (formErrors) => {
                    ruleError = formErrors.pattern ?? 'Could not create the rule.';
                },
                onFinish: () => (creatingRule = false),
            },
        );
    }
</script>

<AppHead title="Review import" />

<div class="flex flex-col gap-6 p-4">
    <Heading
        title="Review import"
        description={`${upload.filename} · ${upload.parsed_count} staged · ${upload.imported_count} promoted`}
    />

    <ErrorSummary
        errors={Object.entries(errors).map(([fieldId, message]) => ({
            fieldId,
            message,
        }))}
    />

    <div class="flex justify-end">
        <Button
            type="button"
            variant="outline"
            onclick={openCategoryModal}
            data-test="new-category"
        >
            New category
        </Button>
    </div>

    <div class="flex flex-wrap gap-2" data-test="status-filter">
        {#each FILTERS as filter (filter.value)}
            <Button
                type="button"
                size="sm"
                variant={statusFilter === filter.value ? 'default' : 'outline'}
                onclick={() => (statusFilter = filter.value)}
                data-test={`filter-${filter.value}`}
            >
                {filter.label} ({counts[filter.value]})
            </Button>
        {/each}
    </div>

    <div class="flex flex-col gap-4">
        {#if visibleRows.length === 0}
            <p
                class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
                data-test="empty-filter"
            >
                No {statusFilter === 'all' ? '' : statusFilter} rows to show.
            </p>
        {/if}
        {#each visibleRows as row (row.id)}
            {#if row.status === 'promoted'}
                <div
                    class="flex items-center justify-between rounded-xl border bg-muted/30 p-4 text-sm"
                    data-test="review-row"
                >
                    <span>{row.date} · {row.payee}</span>
                    <span class="flex items-center gap-3">
                        <span class="font-mono">{row.amountFormatted}</span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">
                            Promoted
                        </span>
                    </span>
                </div>
            {:else}
                <div
                    class="flex flex-col gap-3 rounded-xl border p-4 {row.status === 'rejected' ? 'opacity-60' : ''}"
                    data-test="review-row"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        {#if row.status === 'rejected'}
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">
                                Rejected
                            </span>
                        {:else}
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                Pending
                            </span>
                        {/if}
                        {#if row.isPossibleDuplicate}
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900">
                                Possible duplicate
                            </span>
                        {/if}
                    </div>

                    {#if row.isPossibleDuplicate}
                        <p class="rounded-md bg-amber-100 px-3 py-2 text-xs text-amber-900">
                            Possible duplicate: {row.duplicateReason}
                        </p>
                    {/if}

                    <div class="grid gap-3 md:grid-cols-4">
                        <div class="grid gap-1">
                            <Label for={`date-${row.id}`}>Date</Label>
                            <Input id={`date-${row.id}`} type="date" bind:value={row.date} />
                        </div>
                        <div class="grid gap-1 md:col-span-2">
                            <Label for={`payee-${row.id}`}>Payee</Label>
                            <Input id={`payee-${row.id}`} bind:value={row.payee} />
                        </div>
                        <div class="grid gap-1">
                            <Label for={`amount-${row.id}`}>Amount (cents)</Label>
                            <Input id={`amount-${row.id}`} type="number" step="1" bind:value={row.amount} />
                        </div>
                    </div>

                    {#if !row.isSplit}
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="grid gap-1">
                                <Label for={`category-${row.id}`}>Category</Label>
                                <select
                                    id={`category-${row.id}`}
                                    class={selectClass}
                                    bind:value={row.categoryId}
                                >
                                    <option value="">(none)</option>
                                    {#each categories as category (category.id)}
                                        <option value={category.id}>{category.name}</option>
                                    {/each}
                                </select>
                            </div>
                            <div class="grid gap-1">
                                <Label for={`bucket-${row.id}`}>Bucket</Label>
                                <select
                                    id={`bucket-${row.id}`}
                                    class={selectClass}
                                    bind:value={row.bucketId}
                                >
                                    <option value="">(none)</option>
                                    {#each buckets as bucket (bucket.id)}
                                        <option value={bucket.id}>{bucket.name}</option>
                                    {/each}
                                </select>
                            </div>
                        </div>
                    {:else}
                        <div class="flex flex-col gap-2 rounded-lg border border-dashed p-3">
                            {#each row.splits as split, splitIndex (splitIndex)}
                                <div class="grid items-end gap-2 md:grid-cols-[1fr_1fr_140px_auto]">
                                    <div class="grid gap-1">
                                        <Label for={`split-bucket-${row.id}-${splitIndex}`}>Bucket</Label>
                                        <select
                                            id={`split-bucket-${row.id}-${splitIndex}`}
                                            class={selectClass}
                                            bind:value={split.bucket_id}
                                        >
                                            <option value="">Select a bucket</option>
                                            {#each buckets as bucket (bucket.id)}
                                                <option value={bucket.id}>{bucket.name}</option>
                                            {/each}
                                        </select>
                                    </div>
                                    <div class="grid gap-1">
                                        <Label for={`split-category-${row.id}-${splitIndex}`}>Category</Label>
                                        <select
                                            id={`split-category-${row.id}-${splitIndex}`}
                                            class={selectClass}
                                            bind:value={split.category_id}
                                        >
                                            <option value="">(none)</option>
                                            {#each categories as category (category.id)}
                                                <option value={category.id}>{category.name}</option>
                                            {/each}
                                        </select>
                                    </div>
                                    <div class="grid gap-1">
                                        <Label for={`split-amount-${row.id}-${splitIndex}`}>Amount</Label>
                                        <Input
                                            id={`split-amount-${row.id}-${splitIndex}`}
                                            type="number"
                                            step="1"
                                            bind:value={split.amount}
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onclick={() => removeSplit(row, splitIndex)}
                                    >
                                        Remove
                                    </Button>
                                </div>
                            {/each}
                            <div class="flex items-center justify-between text-xs">
                                <Button type="button" variant="outline" onclick={() => addSplit(row)}>
                                    Add allocation
                                </Button>
                                <span
                                    class={splitSum(row) === Number(row.amount)
                                        ? 'text-muted-foreground'
                                        : 'text-red-600'}
                                >
                                    Allocated {formatCents(splitSum(row))} of {row.amountFormatted}
                                </span>
                            </div>
                        </div>
                    {/if}

                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" bind:checked={row.accept} disabled={row.status === 'rejected'} />
                            Import this row
                        </label>
                        <Button type="button" variant="outline" onclick={() => toggleSplit(row)}>
                            {row.isSplit ? 'Remove split' : 'Split'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onclick={() => openRuleModal(row)}
                            data-test="make-rule"
                        >
                            Make rule
                        </Button>
                        <Button type="button" variant="ghost" onclick={() => toggleReject(row)}>
                            {row.status === 'rejected' ? 'Restore' : 'Reject'}
                        </Button>
                    </div>
                </div>
            {/if}
        {/each}
    </div>

    <div class="flex items-center justify-end gap-3">
        <Button
            type="button"
            variant="outline"
            onclick={save}
            disabled={processing || pendingRows.length === 0}
            data-test="review-save"
        >
            Save corrections
        </Button>
        <Button
            type="button"
            onclick={promote}
            disabled={processing || pendingRows.length === 0}
            data-test="review-promote"
        >
            Promote accepted
        </Button>
    </div>

    {#if promotedCount > 0 && pendingRows.length === 0}
        <p class="text-sm text-muted-foreground">All rows have been promoted to the ledger.</p>
    {/if}
</div>

<Dialog bind:open={showCategoryModal}>
    <DialogContent>
        <DialogTitle>Create a category</DialogTitle>
        <div class="mt-4 flex flex-col gap-4">
            <div class="grid gap-1">
                <Label for="new-category-name">Name</Label>
                <Input
                    id="new-category-name"
                    bind:value={newCategoryName}
                    placeholder="e.g. Streaming"
                    data-test="new-category-name"
                />
            </div>
            <div class="grid gap-1">
                <Label for="new-category-type">Type</Label>
                <select
                    id="new-category-type"
                    class={selectClass}
                    bind:value={newCategoryType}
                    data-test="new-category-type"
                >
                    {#each CATEGORY_TYPES as type (type)}
                        <option value={type}>{type}</option>
                    {/each}
                </select>
            </div>
            <InputError message={categoryError ?? undefined} />
        </div>
        <DialogFooter>
            <Button
                type="button"
                variant="ghost"
                onclick={() => (showCategoryModal = false)}
                disabled={creatingCategory}
            >
                Cancel
            </Button>
            <Button
                type="button"
                onclick={createCategory}
                disabled={creatingCategory || newCategoryName.trim() === ''}
                data-test="new-category-save"
            >
                {creatingCategory ? 'Creating…' : 'Create category'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

<Dialog bind:open={showRuleModal}>
    <DialogContent>
        <DialogTitle>Create a payee rule</DialogTitle>
        <p class="mt-1 text-sm text-muted-foreground">
            Any transaction whose payee contains this text gets the category and
            bucket below, on this and future imports.
        </p>
        <div class="mt-4 flex flex-col gap-4">
            <div class="grid gap-1">
                <Label for="rule-pattern">When the payee contains</Label>
                <Input
                    id="rule-pattern"
                    bind:value={rulePattern}
                    placeholder="e.g. NETFLIX"
                    data-test="rule-pattern"
                />
            </div>
            <div class="grid gap-1">
                <Label for="rule-category">Category</Label>
                <select id="rule-category" class={selectClass} bind:value={ruleCategoryId}>
                    <option value="">(none)</option>
                    {#each categories as category (category.id)}
                        <option value={category.id}>{category.name}</option>
                    {/each}
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="rule-bucket">Bucket</Label>
                <select id="rule-bucket" class={selectClass} bind:value={ruleBucketId}>
                    <option value="">(none)</option>
                    {#each buckets as bucket (bucket.id)}
                        <option value={bucket.id}>{bucket.name}</option>
                    {/each}
                </select>
            </div>
            <InputError message={ruleError ?? undefined} />
        </div>
        <DialogFooter>
            <Button
                type="button"
                variant="ghost"
                onclick={() => (showRuleModal = false)}
                disabled={creatingRule}
            >
                Cancel
            </Button>
            <Button
                type="button"
                onclick={createRule}
                disabled={creatingRule || rulePattern.trim() === ''}
                data-test="rule-save"
            >
                {creatingRule ? 'Creating…' : 'Create rule'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>

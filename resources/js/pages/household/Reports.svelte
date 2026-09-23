<script module lang="ts">
    import { index } from '@/routes/household/reports';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Reports',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import ReportController from '@/actions/App/Http/Controllers/Household/ReportController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Option = { id: string; name: string };
    type LabelValue = { label: string; value: string };
    type Drill = { id: string; date: string; account: string | null; payee: string; category: string | null; amount: string };

    type Result = {
        income?: string;
        expense?: string;
        net?: string;
        drilldown?: Drill[];
        rows?: LabelValue[];
    };

    type Saved = { id: string; name: string; type: string; filters: Record<string, string | null> };

    let {
        type,
        filters,
        result,
        savedReports,
        accounts,
        categories,
        types,
    }: {
        type: string;
        filters: Record<string, string | null>;
        result: Result;
        savedReports: Saved[];
        accounts: Option[];
        categories: Option[];
        types: string[];
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    const typeLabels: Record<string, string> = {
        cash_flow: 'Cash flow',
        spending: 'Spending',
        income: 'Income',
        budget: 'Budget variance',
        net_worth: 'Net worth',
    };

    let form = $state(
        untrack(() => ({
            type,
            from: filters.from ?? '',
            to: filters.to ?? '',
            account_id: filters.account_id ?? '',
            category_id: filters.category_id ?? '',
        })),
    );
    let saveName = $state('');

    function query(): Record<string, string> {
        const q: Record<string, string> = { type: form.type };
        if (form.from) q.from = form.from;
        if (form.to) q.to = form.to;
        if (form.account_id) q.account_id = form.account_id;
        if (form.category_id) q.category_id = form.category_id;
        return q;
    }

    function run(): void {
        router.get(index().url, query(), { preserveScroll: true, preserveState: true });
    }

    function reopen(saved: Saved): void {
        form = {
            type: saved.type,
            from: saved.filters.from ?? '',
            to: saved.filters.to ?? '',
            account_id: saved.filters.account_id ?? '',
            category_id: saved.filters.category_id ?? '',
        };
        run();
    }

    function save(): void {
        if (!saveName) return;
        router.post(
            ReportController.store.url(),
            {
                name: saveName,
                type: form.type,
                filters: {
                    from: form.from || null,
                    to: form.to || null,
                    account_id: form.account_id || null,
                    category_id: form.category_id || null,
                },
            },
            { preserveScroll: true, onSuccess: () => (saveName = '') },
        );
    }

    function remove(saved: Saved): void {
        router.delete(ReportController.destroy(saved.id).url, { preserveScroll: true });
    }

    const exportHref = $derived(ReportController.export.url({ query: query() }));
</script>

<AppHead title="Reports" />

<div class="flex flex-col gap-6 p-4">
    <Heading title="Reports" description="Filter, drill down, save, and export. All totals are computed on the server from your ledger." />

    <div class="grid gap-3 rounded-xl border p-4 md:grid-cols-5">
        <div class="grid gap-1">
            <Label for="r-type">Report</Label>
            <select id="r-type" class={selectClass} bind:value={form.type} data-test="report-type">
                {#each types as t (t)}
                    <option value={t}>{typeLabels[t] ?? t}</option>
                {/each}
            </select>
        </div>
        <div class="grid gap-1">
            <Label for="r-from">From</Label>
            <Input id="r-from" type="date" bind:value={form.from} />
        </div>
        <div class="grid gap-1">
            <Label for="r-to">To</Label>
            <Input id="r-to" type="date" bind:value={form.to} />
        </div>
        <div class="grid gap-1">
            <Label for="r-account">Account</Label>
            <select id="r-account" class={selectClass} bind:value={form.account_id}>
                <option value="">All accounts</option>
                {#each accounts as account (account.id)}<option value={account.id}>{account.name}</option>{/each}
            </select>
        </div>
        <div class="grid gap-1">
            <Label for="r-category">Category</Label>
            <select id="r-category" class={selectClass} bind:value={form.category_id}>
                <option value="">All categories</option>
                {#each categories as category (category.id)}<option value={category.id}>{category.name}</option>{/each}
            </select>
        </div>
        <div class="flex items-end gap-2 md:col-span-5">
            <Button type="button" onclick={run} data-test="run-report">Run</Button>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <a href={exportHref} class={props.class} data-test="export-report">Export CSV</a>
                {/snippet}
            </Button>
            <Input placeholder="Save this filter as…" bind:value={saveName} class="max-w-xs" />
            <Button type="button" variant="outline" onclick={save} data-test="save-report">Save</Button>
        </div>
    </div>

    {#if savedReports.length > 0}
        <div class="flex flex-wrap gap-2">
            {#each savedReports as saved (saved.id)}
                <span class="flex items-center gap-1 rounded-full border px-3 py-1 text-sm">
                    <button type="button" class="hover:underline" onclick={() => reopen(saved)}>{saved.name}</button>
                    <button type="button" class="text-muted-foreground" onclick={() => remove(saved)} aria-label="Delete saved report">×</button>
                </span>
            {/each}
        </div>
    {/if}

    <div class="rounded-xl border p-4" data-test="report-result">
        {#if type === 'cash_flow'}
            <div class="grid gap-4 sm:grid-cols-3">
                <div><span class="text-sm text-muted-foreground">Income</span><br /><span class="text-lg font-semibold">{result.income}</span></div>
                <div><span class="text-sm text-muted-foreground">Expense</span><br /><span class="text-lg font-semibold">{result.expense}</span></div>
                <div><span class="text-sm text-muted-foreground">Net</span><br /><span class="text-lg font-semibold">{result.net}</span></div>
            </div>
            {#if result.drilldown && result.drilldown.length > 0}
                <table class="mt-4 w-full text-sm">
                    <thead class="text-left text-muted-foreground"><tr><th class="py-1 pr-4">Date</th><th class="py-1 pr-4">Payee</th><th class="py-1 pr-4">Category</th><th class="py-1 text-right">Amount</th></tr></thead>
                    <tbody>
                        {#each result.drilldown as row (row.id)}
                            <tr class="border-t"><td class="py-1 pr-4">{row.date}</td><td class="py-1 pr-4">{row.payee}</td><td class="py-1 pr-4">{row.category ?? '—'}</td><td class="py-1 text-right font-mono">{row.amount}</td></tr>
                        {/each}
                    </tbody>
                </table>
            {/if}
        {:else}
            <table class="w-full text-sm">
                <tbody>
                    {#each result.rows ?? [] as row (row.label)}
                        <tr class="border-t first:border-t-0"><td class="py-2">{row.label}</td><td class="py-2 text-right font-mono">{row.value}</td></tr>
                    {/each}
                </tbody>
            </table>
            {#if (result.rows ?? []).length === 0}
                <p class="text-sm text-muted-foreground">No data for this filter.</p>
            {/if}
        {/if}
    </div>
</div>

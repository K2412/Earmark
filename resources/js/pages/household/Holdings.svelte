<script module lang="ts">
    import { index } from '@/routes/household/holdings';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Holdings',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, router } from '@inertiajs/svelte';
    import HoldingController from '@/actions/App/Http/Controllers/Household/HoldingController';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Holding = {
        id: string;
        name: string;
        symbol: string | null;
        asset_class: string;
        cost_basis: string;
        market_value: string;
        gain: string;
    };

    let {
        holdings,
        allocation,
        performance,
        methodology,
        assetClasses,
        accounts,
    }: {
        holdings: Holding[];
        allocation: { asset_class: string; market: string; actual_percent: string; target_percent: string | null }[];
        performance: { total_market: string; total_cost: string; gain: string; return_percent: string };
        methodology: string;
        assetClasses: string[];
        accounts: { id: string; name: string }[];
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    const label = (value: string): string => value.replace(/_/g, ' ');

    let showForm = $state(false);

    function remove(holding: Holding): void {
        if (confirm(`Remove "${holding.name}"?`)) {
            router.delete(HoldingController.destroy(holding.id).url, { preserveScroll: true });
        }
    }
</script>

<AppHead title="Holdings" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading title="Investments" description="Manually maintained holdings — no brokerage connection or trading." />
        <Button type="button" data-test="open-create-holding" onclick={() => (showForm = true)}>Add holding</Button>
    </div>

    <div class="grid gap-4 rounded-xl border p-4 sm:grid-cols-4">
        <div><span class="text-sm text-muted-foreground">Market value</span><br /><span class="text-lg font-semibold">{performance.total_market}</span></div>
        <div><span class="text-sm text-muted-foreground">Cost basis</span><br /><span class="text-lg font-semibold">{performance.total_cost}</span></div>
        <div><span class="text-sm text-muted-foreground">Gain</span><br /><span class="text-lg font-semibold">{performance.gain}</span></div>
        <div><span class="text-sm text-muted-foreground">Return</span><br /><span class="text-lg font-semibold">{performance.return_percent}%</span></div>
    </div>
    <p class="-mt-4 text-xs text-muted-foreground">{methodology}</p>

    {#if allocation.length > 0}
        <div class="overflow-x-auto rounded-xl border">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Allocation vs target</h2>
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr><th class="px-4 py-2 font-medium">Asset class</th><th class="px-4 py-2 text-right font-medium">Market</th><th class="px-4 py-2 text-right font-medium">Actual</th><th class="px-4 py-2 text-right font-medium">Target</th></tr>
                </thead>
                <tbody>
                    {#each allocation as row (row.asset_class)}
                        <tr class="border-t">
                            <td class="px-4 py-2 capitalize">{label(row.asset_class)}</td>
                            <td class="px-4 py-2 text-right font-mono">{row.market}</td>
                            <td class="px-4 py-2 text-right">{row.actual_percent}%</td>
                            <td class="px-4 py-2 text-right">{row.target_percent ? row.target_percent + '%' : '—'}</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

    {#if holdings.length > 0}
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr><th class="px-4 py-3 font-medium">Name</th><th class="px-4 py-3 font-medium">Class</th><th class="px-4 py-3 text-right font-medium">Cost</th><th class="px-4 py-3 text-right font-medium">Market</th><th class="px-4 py-3 text-right font-medium">Gain</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody>
                    {#each holdings as holding (holding.id)}
                        <tr class="border-t" data-test="holding-row">
                            <td class="px-4 py-3">{holding.name}{#if holding.symbol}<span class="ml-1 text-xs text-muted-foreground">{holding.symbol}</span>{/if}</td>
                            <td class="px-4 py-3 capitalize">{label(holding.asset_class)}</td>
                            <td class="px-4 py-3 text-right font-mono">{holding.cost_basis}</td>
                            <td class="px-4 py-3 text-right font-mono">{holding.market_value}</td>
                            <td class="px-4 py-3 text-right font-mono">{holding.gain}</td>
                            <td class="px-4 py-3 text-right"><Button type="button" variant="ghost" size="sm" onclick={() => remove(holding)}>Remove</Button></td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

    {#if showForm}
        <Form {...HoldingController.store.form()} class="max-w-lg space-y-4 rounded-xl border p-4">
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add holding</h2>
                <ErrorSummary errors={Object.entries(errors).map(([fieldId, message]) => ({ fieldId, message }))} />
                <div class="grid gap-2">
                    <Label for="h-name">Name</Label>
                    <Input id="h-name" name="name" required />
                    <InputError message={errors.name} />
                </div>
                <div class="grid gap-2">
                    <Label for="h-symbol">Symbol (optional)</Label>
                    <Input id="h-symbol" name="symbol" />
                </div>
                <div class="grid gap-2">
                    <Label for="h-class">Asset class</Label>
                    <select id="h-class" name="asset_class" class={selectClass}>
                        {#each assetClasses as ac (ac)}<option value={ac}>{label(ac)}</option>{/each}
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="h-cost">Cost basis (cents)</Label>
                    <Input id="h-cost" name="cost_basis_cents" type="number" min="0" step="1" required />
                    <InputError message={errors.cost_basis_cents} />
                </div>
                <div class="grid gap-2">
                    <Label for="h-market">Market value (cents)</Label>
                    <Input id="h-market" name="market_value_cents" type="number" min="0" step="1" required />
                    <InputError message={errors.market_value_cents} />
                </div>
                <div class="grid gap-2">
                    <Label for="h-target">Target allocation (basis points, optional)</Label>
                    <Input id="h-target" name="target_allocation_bps" type="number" min="0" max="10000" step="1" />
                    <InputError message={errors.target_allocation_bps} />
                </div>
                <div class="grid gap-2">
                    <Label for="h-account">Account (optional)</Label>
                    <select id="h-account" name="account_id" class={selectClass}>
                        <option value="">(none)</option>
                        {#each accounts as account (account.id)}<option value={account.id}>{account.name}</option>{/each}
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="ghost" onclick={() => (showForm = false)}>Cancel</Button>
                    <Button type="submit" disabled={processing} data-test="submit-holding">Save</Button>
                </div>
            {/snippet}
        </Form>
    {/if}
</div>

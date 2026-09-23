<script module lang="ts">
    import { index } from '@/routes/household/scenarios';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Scenarios',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import ScenarioController from '@/actions/App/Http/Controllers/Household/ScenarioController';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Ending = { nominal: string; real: string };

    type Scenario = {
        id: string;
        name: string;
        formula_version: string;
        assumptions_snapshot: Record<string, number | null>;
        ending: { conservative: Ending; base: Ending; optimistic: Ending };
    };

    type Comparison = {
        a: { id: string; name: string };
        b: { id: string; name: string };
        changes: { field: string; a: unknown; b: unknown }[];
    } | null;

    let {
        scenarios,
        comparison,
        defaults,
    }: {
        scenarios: Scenario[];
        comparison: Comparison;
        defaults: { base_year: number; horizon_years: number; return_bps: number; inflation_bps: number };
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    let showForm = $state(false);
    let compareA = $state(untrack(() => comparison?.a.id ?? ''));
    let compareB = $state(untrack(() => comparison?.b.id ?? ''));

    function runCompare(): void {
        if (compareA && compareB) {
            router.get(index().url, { a: compareA, b: compareB }, { preserveScroll: true, preserveState: true });
        }
    }

    function remove(scenario: Scenario): void {
        if (confirm(`Remove scenario "${scenario.name}"?`)) {
            router.delete(ScenarioController.destroy(scenario.id).url, { preserveScroll: true });
        }
    }

    const label = (field: string): string => field.replace(/_cents$/, '').replace(/_bps$/, '').replace(/_/g, ' ');
</script>

<AppHead title="Scenarios" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading title="Scenarios" description="Saved, reproducible projections. Each records the assumptions it used, so results are auditable and never rewritten by later catalogue changes." />
        <Button type="button" data-test="open-create-scenario" onclick={() => (showForm = true)}>Add scenario</Button>
    </div>

    {#if scenarios.length > 1}
        <div class="flex flex-wrap items-end gap-2 rounded-xl border p-4">
            <div class="grid gap-1">
                <Label for="cmp-a">Compare</Label>
                <select id="cmp-a" class={selectClass} bind:value={compareA}>
                    <option value="">Scenario A</option>
                    {#each scenarios as s (s.id)}<option value={s.id}>{s.name}</option>{/each}
                </select>
            </div>
            <div class="grid gap-1">
                <Label for="cmp-b">with</Label>
                <select id="cmp-b" class={selectClass} bind:value={compareB}>
                    <option value="">Scenario B</option>
                    {#each scenarios as s (s.id)}<option value={s.id}>{s.name}</option>{/each}
                </select>
            </div>
            <Button type="button" variant="outline" onclick={runCompare} data-test="run-compare">Compare</Button>
        </div>
    {/if}

    {#if comparison}
        <div class="rounded-xl border p-4" data-test="comparison">
            <h2 class="text-sm font-semibold">{comparison.a.name} vs {comparison.b.name}</h2>
            {#if comparison.changes.length === 0}
                <p class="mt-2 text-sm text-muted-foreground">No differing inputs.</p>
            {:else}
                <table class="mt-2 w-full text-sm">
                    <thead class="text-left text-muted-foreground"><tr><th class="py-1 pr-4">Assumption</th><th class="py-1 pr-4">{comparison.a.name}</th><th class="py-1">{comparison.b.name}</th></tr></thead>
                    <tbody>
                        {#each comparison.changes as change (change.field)}
                            <tr class="border-t"><td class="py-1 pr-4 capitalize">{label(change.field)}</td><td class="py-1 pr-4">{change.a}</td><td class="py-1">{change.b}</td></tr>
                        {/each}
                    </tbody>
                </table>
            {/if}
        </div>
    {/if}

    {#if scenarios.length === 0}
        <div class="rounded-xl border p-6 text-sm text-muted-foreground">No scenarios yet. Add one to project your plan.</div>
    {:else}
        <div class="grid gap-3 md:grid-cols-2">
            {#each scenarios as scenario (scenario.id)}
                <div class="rounded-xl border p-4" data-test="scenario-card">
                    <div class="flex items-start justify-between">
                        <h2 class="font-medium">{scenario.name}</h2>
                        <Button type="button" variant="ghost" size="sm" onclick={() => remove(scenario)}>Remove</Button>
                    </div>
                    <table class="mt-2 w-full text-sm">
                        <thead class="text-left text-muted-foreground"><tr><th class="py-1 pr-4"></th><th class="py-1 pr-4 text-right">Nominal</th><th class="py-1 text-right">Today's $</th></tr></thead>
                        <tbody>
                            <tr class="border-t"><td class="py-1 pr-4">Conservative</td><td class="py-1 pr-4 text-right font-mono">{scenario.ending.conservative.nominal}</td><td class="py-1 text-right font-mono">{scenario.ending.conservative.real}</td></tr>
                            <tr class="border-t"><td class="py-1 pr-4">Base</td><td class="py-1 pr-4 text-right font-mono">{scenario.ending.base.nominal}</td><td class="py-1 text-right font-mono">{scenario.ending.base.real}</td></tr>
                            <tr class="border-t"><td class="py-1 pr-4">Optimistic</td><td class="py-1 pr-4 text-right font-mono">{scenario.ending.optimistic.nominal}</td><td class="py-1 text-right font-mono">{scenario.ending.optimistic.real}</td></tr>
                        </tbody>
                    </table>
                    <p class="mt-2 text-xs text-muted-foreground">{scenario.formula_version}</p>
                </div>
            {/each}
        </div>
    {/if}

    {#if showForm}
        <Form {...ScenarioController.store.form()} class="max-w-lg space-y-4 rounded-xl border p-4">
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add scenario</h2>
                <ErrorSummary errors={Object.entries(errors).map(([fieldId, message]) => ({ fieldId, message }))} />
                <div class="grid gap-2">
                    <Label for="sc-name">Name</Label>
                    <Input id="sc-name" name="name" required />
                    <InputError message={errors.name} />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1"><Label for="sc-base">Base year</Label><Input id="sc-base" name="base_year" type="number" value={defaults.base_year} required /></div>
                    <div class="grid gap-1"><Label for="sc-horizon">Horizon (years)</Label><Input id="sc-horizon" name="horizon_years" type="number" value={defaults.horizon_years} required /></div>
                    <div class="grid gap-1"><Label for="sc-start">Starting investable (cents)</Label><Input id="sc-start" name="starting_investable_cents" type="number" value="0" required /></div>
                    <div class="grid gap-1"><Label for="sc-contrib">Annual contribution (cents)</Label><Input id="sc-contrib" name="annual_contribution_cents" type="number" value="0" required /></div>
                    <div class="grid gap-1"><Label for="sc-cyears">Contribution years</Label><Input id="sc-cyears" name="contribution_years" type="number" value="20" required /></div>
                    <div class="grid gap-1"><Label for="sc-return">Return (bps)</Label><Input id="sc-return" name="return_bps" type="number" value={defaults.return_bps} required /></div>
                    <div class="grid gap-1"><Label for="sc-infl">Inflation (bps)</Label><Input id="sc-infl" name="inflation_bps" type="number" value={defaults.inflation_bps} required /></div>
                    <div class="grid gap-1"><Label for="sc-draw-year">Drawdown start year</Label><Input id="sc-draw-year" name="drawdown_start_year" type="number" /></div>
                    <div class="grid gap-1"><Label for="sc-draw">Drawdown / year (cents)</Label><Input id="sc-draw" name="drawdown_annual_cents" type="number" /></div>
                    <div class="grid gap-1"><Label for="sc-down-year">Downsizing year</Label><Input id="sc-down-year" name="downsizing_year" type="number" /></div>
                    <div class="grid gap-1"><Label for="sc-down">Downsizing proceeds (cents)</Label><Input id="sc-down" name="downsizing_proceeds_cents" type="number" /></div>
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="ghost" onclick={() => (showForm = false)}>Cancel</Button>
                    <Button type="submit" disabled={processing} data-test="submit-scenario">Save scenario</Button>
                </div>
            {/snippet}
        </Form>
    {/if}
</div>

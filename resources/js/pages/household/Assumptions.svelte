<script module lang="ts">
    import { index } from '@/routes/household/assumptions';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Assumptions',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AssumptionController from '@/actions/App/Http/Controllers/Household/AssumptionController';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Assumption = {
        key: string;
        value: string;
        unit: string | null;
        effective_year: number | null;
        source_url: string | null;
        source_date: string | null;
        catalogue_version: string | null;
        jurisdiction: string | null;
        is_override: boolean;
        stale: boolean;
    };

    let {
        assumptions,
        keys,
        year,
        defaults,
    }: {
        assumptions: Assumption[];
        keys: string[];
        year: number;
        defaults: { effective_year: number; source_date: string };
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    const label = (key: string): string => key.replace(/_/g, ' ');

    let showForm = $state(false);
</script>

<AppHead title="Assumptions" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading title="Planning assumptions" description={`Effective-dated, source-labelled Canadian assumptions used by projections and scenarios (${year}). Override any value with your own source.`} />
        <Button type="button" data-test="open-override" onclick={() => (showForm = true)}>Override an assumption</Button>
    </div>

    <div class="overflow-x-auto rounded-xl border">
        <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Assumption</th>
                    <th class="px-4 py-3 text-right font-medium">Value</th>
                    <th class="px-4 py-3 font-medium">Effective</th>
                    <th class="px-4 py-3 font-medium">Source</th>
                    <th class="px-4 py-3 font-medium">Version</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                {#each assumptions as row (row.key)}
                    <tr class="border-t" data-test="assumption-row">
                        <td class="px-4 py-3 capitalize">{label(row.key)}</td>
                        <td class="px-4 py-3 text-right font-mono">{row.value}</td>
                        <td class="px-4 py-3">{row.effective_year ?? '—'}</td>
                        <td class="px-4 py-3 text-xs">
                            {#if row.source_url}
                                <a href={row.source_url} target="_blank" rel="noopener" class="underline">{row.source_date ?? 'source'}</a>
                            {:else}
                                {row.source_date ?? '—'}
                            {/if}
                        </td>
                        <td class="px-4 py-3 text-xs">{row.catalogue_version ?? '—'}</td>
                        <td class="px-4 py-3">
                            <span class="flex gap-1 text-xs">
                                {#if row.is_override}
                                    <span class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800">Override</span>
                                {:else}
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-muted-foreground">System</span>
                                {/if}
                                {#if row.stale}
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">Stale source</span>
                                {/if}
                            </span>
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>

    {#if showForm}
        <Form {...AssumptionController.store.form()} class="max-w-lg space-y-4 rounded-xl border p-4">
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Override an assumption</h2>
                <ErrorSummary errors={Object.entries(errors).map(([fieldId, message]) => ({ fieldId, message }))} />
                <div class="grid gap-2">
                    <Label for="a-key">Assumption</Label>
                    <select id="a-key" name="key" class={selectClass}>
                        {#each keys as key (key)}<option value={key}>{label(key)}</option>{/each}
                    </select>
                    <InputError message={errors.key} />
                </div>
                <div class="grid gap-2">
                    <Label for="a-value">Value (cents, or basis points for rates)</Label>
                    <Input id="a-value" name="value" type="number" step="1" required />
                    <InputError message={errors.value} />
                </div>
                <div class="grid gap-2">
                    <Label for="a-unit">Unit</Label>
                    <select id="a-unit" name="unit" class={selectClass}>
                        <option value="cents">cents</option>
                        <option value="bps">basis points</option>
                        <option value="count">count</option>
                    </select>
                    <InputError message={errors.unit} />
                </div>
                <div class="grid gap-2">
                    <Label for="a-year">Effective year</Label>
                    <Input id="a-year" name="effective_year" type="number" step="1" value={defaults.effective_year} required />
                    <InputError message={errors.effective_year} />
                </div>
                <div class="grid gap-2">
                    <Label for="a-source-date">Source date</Label>
                    <Input id="a-source-date" name="source_date" type="date" value={defaults.source_date} />
                    <InputError message={errors.source_date} />
                </div>
                <div class="grid gap-2">
                    <Label for="a-source-url">Source URL</Label>
                    <Input id="a-source-url" name="source_url" type="url" placeholder="https://…" />
                    <InputError message={errors.source_url} />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="ghost" onclick={() => (showForm = false)}>Cancel</Button>
                    <Button type="submit" disabled={processing} data-test="submit-override">Save override</Button>
                </div>
            {/snippet}
        </Form>
    {/if}
</div>

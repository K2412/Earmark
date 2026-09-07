<script module lang="ts">
    import { show } from '@/routes/household/net-worth';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Net Worth',
                href: show(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import NetWorthController from '@/actions/App/Http/Controllers/Household/NetWorthController';
    import ActionableEmptyState from '@/components/ActionableEmptyState.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import FinanceSummary from '@/components/FinanceSummary.svelte';
    import FreshnessIndicator from '@/components/FreshnessIndicator.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import MoneyRow from '@/components/MoneyRow.svelte';
    import OwnerIndicator from '@/components/OwnerIndicator.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Snapshot = {
        has_positions: boolean;
        investable: string;
        home_purchase: string;
        home_equity: string;
        total_net_worth: string;
        freshness: {
            status: 'current' | 'stale' | 'unknown';
            label: string;
        };
        positions: {
            id: string;
            name: string;
            classification: string;
            purpose: string;
            owner: string | null;
            amount: string;
            valued_at: string | null;
        }[];
    };

    type ProjectionPoint = { year: number; cents: number; amount: string };

    type Projection = {
        formula_version: string;
        labels: { values: string; return: string };
        starting: string;
        inflated_target: string;
        projected_at_target: string;
        gap: string;
        series: {
            base: ProjectionPoint[];
            bps_500: ProjectionPoint[];
            bps_700: ProjectionPoint[];
            bps_900: ProjectionPoint[];
        };
        table: Array<{
            year: number;
            base: string;
            bps_500: string;
            bps_700: string;
            bps_900: string;
        }>;
    };

    let {
        snapshot,
        plan,
        projection,
        defaults,
        classifications,
        purposes,
    }: {
        snapshot: Snapshot;
        plan: {
            target_cents: number;
            target_year: number;
            inflation_bps: number;
            return_bps: number;
            windfall_cents: number;
            windfall_year: number | null;
            phases: {
                start_year: number;
                end_year: number;
                annual_contribution_cents: number;
            }[];
        } | null;
        projection: Projection | null;
        defaults: {
            valued_at: string;
            target_year: number;
            phase_start: number;
            phase_end: number;
        };
        classifications: { value: string; label: string }[];
        purposes: { value: string; label: string }[];
    } = $props();

    let showPositionForm = $state(false);
    let valuingId = $state<string | null>(null);

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    function chartPoints(
        series: ProjectionPoint[],
        width: number,
        height: number,
    ): string {
        const values = series.map((point) => point.cents);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const range = Math.max(max - min, 1);

        return series
            .map((point, index) => {
                const x =
                    series.length === 1
                        ? width / 2
                        : (index / (series.length - 1)) * width;
                const y = height - ((point.cents - min) / range) * height;

                return `${x},${y}`;
            })
            .join(' ');
    }
</script>

<AppHead title="Net Worth" />

<div class="flex flex-col gap-6 p-4">
    <Heading
        title="Net Worth"
        description="Snapshot of household positions. Projection values are before tax; returns are after fees."
    />

    {#if !snapshot.has_positions}
        <ActionableEmptyState
            title="Add first financial position"
            description="A projection is not shown until the household records at least one valued position. Home equity is never treated as investable."
        >
            {#snippet action()}
                <Button
                    type="button"
                    data-test="open-create-position"
                    onclick={() => (showPositionForm = true)}
                >
                    Add financial position
                </Button>
            {/snippet}
        </ActionableEmptyState>
    {:else}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <FinanceSummary
                title="Investable assets"
                value={snapshot.investable}
                description="Long-term portfolio only"
            />
            <FinanceSummary
                title="Total net worth"
                value={snapshot.total_net_worth}
                description="Includes home equity"
            />
            <FinanceSummary
                title="Home equity"
                value={snapshot.home_equity}
                description="Residence assets minus mortgage"
            />
            <FinanceSummary
                title="Home-purchase assets"
                value={snapshot.home_purchase}
                description="Kept out of the projection seed"
            >
                {#snippet action()}
                    <FreshnessIndicator
                        label={snapshot.freshness.label}
                        status={snapshot.freshness.status}
                    />
                {/snippet}
            </FinanceSummary>
        </div>

        {#if projection}
            <section class="space-y-4" aria-labelledby="projection-heading">
                <div>
                    <h2 id="projection-heading" class="text-lg font-semibold">
                        Projection
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {projection.labels.values}, return {projection.labels
                            .return}. Formula {projection.formula_version}.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <FinanceSummary
                        title="Projected at target"
                        value={projection.projected_at_target}
                    />
                    <FinanceSummary
                        title="Inflated target"
                        value={projection.inflated_target}
                    />
                    <FinanceSummary title="Gap" value={projection.gap} />
                </div>

                <figure>
                    <figcaption class="mb-2 text-sm text-muted-foreground">
                        Investable assets over time at 5%, 7%, and 9% after-fee
                        returns. Line pattern, not color, distinguishes each
                        path.
                    </figcaption>
                    <svg
                        role="img"
                        aria-label="Line chart of projected investable assets at 5, 7, and 9 percent returns"
                        viewBox="0 0 360 160"
                        class="w-full rounded-xl border bg-card"
                    >
                        <polyline
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-dasharray="1 4"
                            points={chartPoints(projection.series.bps_500, 360, 140)}
                            transform="translate(0 10)"
                        />
                        <polyline
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            points={chartPoints(projection.series.bps_700, 360, 140)}
                            transform="translate(0 10)"
                        />
                        <polyline
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-dasharray="6 4"
                            points={chartPoints(projection.series.bps_900, 360, 140)}
                            transform="translate(0 10)"
                        />
                    </svg>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Dotted 5% · Solid 7% · Dashed 9%
                    </p>
                </figure>

                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <caption class="sr-only">
                            Yearly projected investable assets before tax
                        </caption>
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Year</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Plan
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    5%
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    7%
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    9%
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each projection.table as row (row.year)}
                                <tr class="border-t">
                                    <td class="px-4 py-3">{row.year}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {row.base}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {row.bps_500}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {row.bps_700}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {row.bps_900}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </section>
        {/if}

        <section class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Positions</h2>
                <Button
                    type="button"
                    data-test="open-create-position"
                    onclick={() => (showPositionForm = true)}
                >
                    Add position
                </Button>
            </div>
            <div class="divide-y rounded-xl border px-4">
                {#each snapshot.positions as position (position.id)}
                    <div class="py-2">
                        <MoneyRow
                            label={position.name}
                            amount={position.amount}
                            detail="{position.classification} · {position.purpose.replaceAll(
                                '_',
                                ' ',
                            )}{position.valued_at
                                ? ` · ${position.valued_at}`
                                : ''}"
                        />
                        <div class="flex items-center gap-2 pb-2">
                            {#if position.owner}
                                <OwnerIndicator label={position.owner} />
                            {/if}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                data-test="open-create-valuation-{position.id}"
                                onclick={() => (valuingId = position.id)}
                            >
                                New valuation
                            </Button>
                        </div>
                        {#if valuingId === position.id}
                            <Form
                                {...NetWorthController.storeValuation.form(
                                    position.id,
                                )}
                                class="mb-4 grid gap-3 md:grid-cols-3"
                            >
                                {#snippet children({ errors, processing })}
                                    <div class="grid gap-2">
                                        <Label for="valuation-amount-{position.id}"
                                            >Amount (cents)</Label
                                        >
                                        <Input
                                            id="valuation-amount-{position.id}"
                                            name="amount"
                                            type="number"
                                            min="0"
                                            required
                                        />
                                        <InputError message={errors.amount} />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label for="valuation-date-{position.id}"
                                            >Valued at</Label
                                        >
                                        <Input
                                            id="valuation-date-{position.id}"
                                            name="valued_at"
                                            type="date"
                                            required
                                            value={defaults.valued_at}
                                        />
                                        <InputError message={errors.valued_at} />
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-test="submit-create-valuation-{position.id}"
                                            >Save</Button
                                        >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onclick={() => (valuingId = null)}
                                            >Cancel</Button
                                        >
                                    </div>
                                {/snippet}
                            </Form>
                        {/if}
                    </div>
                {/each}
            </div>
        </section>
    {/if}

    {#if showPositionForm}
        <Form
            {...NetWorthController.storePosition.form()}
            class="max-w-lg space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add financial position</h2>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" name="name" required placeholder="RRSP" />
                    <InputError message={errors.name} />
                </div>
                <div class="grid gap-2">
                    <Label for="classification">Classification</Label>
                    <select
                        id="classification"
                        name="classification"
                        class={selectClass}
                    >
                        {#each classifications as option (option.value)}
                            <option value={option.value}>{option.label}</option>
                        {/each}
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="purpose">Purpose</Label>
                    <select id="purpose" name="purpose" class={selectClass}>
                        {#each purposes as option (option.value)}
                            <option value={option.value}>{option.label}</option>
                        {/each}
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="amount">Current value (cents)</Label>
                    <Input id="amount" name="amount" type="number" min="0" required />
                    <InputError message={errors.amount} />
                </div>
                <div class="grid gap-2">
                    <Label for="valued_at">Valued at</Label>
                    <Input
                        id="valued_at"
                        name="valued_at"
                        type="date"
                        required
                        value={defaults.valued_at}
                    />
                    <InputError message={errors.valued_at} />
                </div>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        onclick={() => (showPositionForm = false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="submit-create-position"
                    >
                        Create
                    </Button>
                </div>
            {/snippet}
        </Form>
    {/if}

    {#if snapshot.has_positions}
        <Form
            {...NetWorthController.storePlan.form()}
            class="space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Household plan assumptions</h2>
                <p class="text-sm text-muted-foreground">
                    Used only after a position exists. Return is after fees;
                    illustrated values are before tax.
                </p>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="target_cents">Target (cents, today)</Label>
                        <Input
                            id="target_cents"
                            name="target_cents"
                            type="number"
                            min="0"
                            required
                            value={plan?.target_cents ?? 1000000000}
                        />
                        <InputError message={errors.target_cents} />
                    </div>
                    <div class="grid gap-2">
                        <Label for="target_year">Target year</Label>
                        <Input
                            id="target_year"
                            name="target_year"
                            type="number"
                            required
                            value={plan?.target_year ?? defaults.target_year}
                        />
                        <InputError message={errors.target_year} />
                    </div>
                    <div class="grid gap-2">
                        <Label for="inflation_bps">Inflation (bps)</Label>
                        <Input
                            id="inflation_bps"
                            name="inflation_bps"
                            type="number"
                            min="0"
                            required
                            value={plan?.inflation_bps ?? 200}
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="return_bps">Return after fees (bps)</Label>
                        <Input
                            id="return_bps"
                            name="return_bps"
                            type="number"
                            min="0"
                            required
                            value={plan?.return_bps ?? 700}
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="windfall_cents">Windfall (cents)</Label>
                        <Input
                            id="windfall_cents"
                            name="windfall_cents"
                            type="number"
                            min="0"
                            value={plan?.windfall_cents ?? 0}
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="windfall_year">Windfall year</Label>
                        <Input
                            id="windfall_year"
                            name="windfall_year"
                            type="number"
                            value={plan?.windfall_year ?? ''}
                        />
                    </div>
                </div>
                <fieldset class="grid gap-3">
                    <legend class="text-sm font-medium">Contribution phase</legend>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="phase-start">Start year</Label>
                            <Input
                                id="phase-start"
                                name="phases[0][start_year]"
                                type="number"
                                required
                                value={plan?.phases[0]?.start_year ??
                                    defaults.phase_start}
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phase-end">End year</Label>
                            <Input
                                id="phase-end"
                                name="phases[0][end_year]"
                                type="number"
                                required
                                value={plan?.phases[0]?.end_year ??
                                    defaults.phase_end}
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phase-amount">Annual contribution (cents)</Label>
                            <Input
                                id="phase-amount"
                                name="phases[0][annual_contribution_cents]"
                                type="number"
                                min="0"
                                required
                                value={plan?.phases[0]
                                    ?.annual_contribution_cents ?? 0}
                            />
                        </div>
                    </div>
                </fieldset>
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="submit-save-plan"
                >
                    Save plan
                </Button>
            {/snippet}
        </Form>
    {/if}
</div>

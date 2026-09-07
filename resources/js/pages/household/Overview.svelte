<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Overview',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import FinanceSummary from '@/components/FinanceSummary.svelte';
    import MoneyRow from '@/components/MoneyRow.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { toUrl } from '@/lib/utils';
    import FreshnessIndicator from '@/components/FreshnessIndicator.svelte';
    import { index as accounts } from '@/routes/household/accounts';
    import { index as members } from '@/routes/household/members';
    import { show as netWorth } from '@/routes/household/net-worth';
    import { index as plan } from '@/routes/household/plan';
    import { index as transactions } from '@/routes/household/transactions';

    let {
        unassignedAvailable,
        unassignedAvailableCents,
        underfundedBuckets,
        netWorth: netWorthSummary,
    }: {
        unassignedAvailable: string;
        unassignedAvailableCents: number;
        underfundedBuckets: { id: string; name: string }[];
        netWorth: {
            investable: string;
            total: string;
            targetGap: string | null;
            freshness: {
                status: 'current' | 'stale' | 'unknown';
                label: string;
            };
            hasPositions: boolean;
        };
    } = $props();
</script>

<AppHead title="Overview" />

<div class="flex flex-col gap-6 p-4">
    <div class="grid gap-4 md:grid-cols-3">
        <FinanceSummary
            title="Unassigned Funds"
            value={unassignedAvailable}
            description="Available to assign"
            class={unassignedAvailableCents < 0 ? 'text-destructive' : ''}
        />

        <div class="md:col-span-2" data-test="underfunded-buckets">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    Underfunded buckets
                </CardTitle>
                <span class="text-sm tabular-nums">{underfundedBuckets.length}</span>
            </CardHeader>
            <CardContent>
                {#if underfundedBuckets.length === 0}
                    <p class="text-sm text-muted-foreground">
                        Every bucket has enough for this month and any
                        rolled-forward gaps. Nice.
                    </p>
                {:else}
                    {#each underfundedBuckets as bucket (bucket.id)}
                        <MoneyRow
                            label={bucket.name}
                            amount="Fund"
                            detail="Needs more this month"
                        />
                    {/each}
                    <Button variant="link" class="px-0" asChild>
                        {#snippet children(props)}
                            <Link href={toUrl(plan())} class={props.class}>
                                Open Plan
                            </Link>
                        {/snippet}
                    </Button>
                {/if}
            </CardContent>
        </Card>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3" data-test="net-worth-cards">
        <FinanceSummary
            title="Investable assets"
            value={netWorthSummary.investable}
            description="Long-term portfolio, excluding home"
        />
        <FinanceSummary
            title="Total net worth"
            value={netWorthSummary.total}
            description="Assets minus liabilities, including home"
        />
        <FinanceSummary
            title="Target gap"
            value={netWorthSummary.targetGap ?? '—'}
            description={netWorthSummary.targetGap
                ? 'Household target minus current investable'
                : 'Save a household plan on Net Worth'}
        >
            {#snippet action()}
                <div class="flex flex-col items-end gap-2">
                    <FreshnessIndicator
                        label={netWorthSummary.freshness.label}
                        status={netWorthSummary.freshness.status}
                    />
                    <Button variant="link" class="px-0" asChild>
                        {#snippet children(props)}
                            <Link href={toUrl(netWorth())} class={props.class}>
                                {netWorthSummary.hasPositions
                                    ? 'Open Net Worth'
                                    : 'Add first financial position'}
                            </Link>
                        {/snippet}
                    </Button>
                </div>
            {/snippet}
        </FinanceSummary>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Button variant="outline" class="h-auto justify-start p-4" asChild>
            {#snippet children(props)}
                <Link href={toUrl(accounts())} class={props.class}>Accounts</Link>
            {/snippet}
        </Button>
        <Button variant="outline" class="h-auto justify-start p-4" asChild>
            {#snippet children(props)}
                <Link href={toUrl(plan())} class={props.class}>Plan</Link>
            {/snippet}
        </Button>
        <Button variant="outline" class="h-auto justify-start p-4" asChild>
            {#snippet children(props)}
                <Link href={toUrl(transactions())} class={props.class}
                    >Transactions</Link
                >
            {/snippet}
        </Button>
        <Button variant="outline" class="h-auto justify-start p-4" asChild>
            {#snippet children(props)}
                <Link href={toUrl(members())} class={props.class}
                    >Household members</Link
                >
            {/snippet}
        </Button>
    </div>
</div>

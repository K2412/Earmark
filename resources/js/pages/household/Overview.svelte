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
    import { untrack } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import CategoryPill from '@/components/CategoryPill.svelte';
    import FinanceSummary from '@/components/FinanceSummary.svelte';
    import MoneyRow from '@/components/MoneyRow.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import FreshnessIndicator from '@/components/FreshnessIndicator.svelte';
    import { updateCards } from '@/actions/App/Http/Controllers/Household/DashboardController';
    import { index as accounts } from '@/routes/household/accounts';
    import { index as goals } from '@/routes/household/goals';
    import { index as holdings } from '@/routes/household/holdings';
    import { index as members } from '@/routes/household/members';
    import { show as netWorth } from '@/routes/household/net-worth';
    import { index as plan } from '@/routes/household/plan';
    import { index as recurring } from '@/routes/household/recurring';
    import { index as scenarios } from '@/routes/household/scenarios';
    import { index as transactions } from '@/routes/household/transactions';

    type CardKey =
        | 'budget'
        | 'top_categories'
        | 'review_queue'
        | 'recurring'
        | 'goals'
        | 'net_worth'
        | 'investments'
        | 'scenarios';

    let {
        availableCards,
        enabledCards,
        cards,
    }: {
        availableCards: CardKey[];
        enabledCards: CardKey[];
        cards: {
            budget: {
                unassigned: string;
                underfunded: { id: string; name: string }[];
            };
            top_categories: {
                items: {
                    name: string;
                    type: string | null;
                    spent: string;
                    ratio: number;
                }[];
                total: string;
            };
            review_queue: { count: number };
            recurring: { count: number };
            goals: { count: number };
            net_worth: {
                investable: string;
                total: string;
                targetGap: string | null;
                freshness: {
                    status: 'current' | 'stale' | 'unknown';
                    label: string;
                };
                hasPositions: boolean;
            };
            investments: { total: string; count: number };
            scenarios: { count: number };
        };
    } = $props();

    const cardLabels: Record<CardKey, string> = {
        budget: 'Budget',
        top_categories: 'Top categories',
        review_queue: 'Review queue',
        recurring: 'Recurring',
        goals: 'Goals',
        net_worth: 'Net worth',
        investments: 'Investments',
        scenarios: 'Scenarios',
    };

    let customizing = $state(false);
    let selected = $state<CardKey[]>(untrack(() => [...enabledCards]));
    let saving = $state(false);

    const shown = (key: CardKey): boolean => enabledCards.includes(key);
    const unassignedNegative = $derived(cards.budget.unassigned.startsWith('-'));

    function startCustomizing(): void {
        selected = [...enabledCards];
        customizing = true;
    }

    function toggle(key: CardKey): void {
        selected = selected.includes(key)
            ? selected.filter((k) => k !== key)
            : [...selected, key];
    }

    function saveCards(): void {
        saving = true;
        // Persist in the canonical card order so the overview always lays out consistently.
        const ordered = availableCards.filter((key) => selected.includes(key));

        router.post(
            updateCards.url(),
            { cards: ordered },
            {
                preserveScroll: true,
                onSuccess: () => {
                    customizing = false;
                },
                onFinish: () => {
                    saving = false;
                },
            },
        );
    }
</script>

<AppHead title="Overview" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">Overview</h1>
        <Button variant="outline" size="sm" onclick={startCustomizing}>
            Customize
        </Button>
    </div>

    {#if customizing}
        <Card>
            <CardHeader>
                <CardTitle class="text-sm font-medium">
                    Choose the cards you want on your overview
                </CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {#each availableCards as key (key)}
                        <Label class="flex items-center gap-2 font-normal">
                            <Checkbox
                                checked={selected.includes(key)}
                                onclick={() => toggle(key)}
                            />
                            {cardLabels[key]}
                        </Label>
                    {/each}
                </div>
                <div class="flex gap-2">
                    <Button size="sm" onclick={saveCards} disabled={saving}>
                        {saving ? 'Saving…' : 'Save'}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onclick={() => (customizing = false)}
                        disabled={saving}
                    >
                        Cancel
                    </Button>
                </div>
            </CardContent>
        </Card>
    {/if}

    {#if enabledCards.length === 0}
        <Card>
            <CardContent class="py-8 text-center text-sm text-muted-foreground">
                No cards selected. Choose some with the Customize button above.
            </CardContent>
        </Card>
    {/if}

    {#if shown('budget')}
        <div class="grid gap-4 md:grid-cols-3">
            <FinanceSummary
                title="Unassigned Funds"
                value={cards.budget.unassigned}
                description="Available to assign"
                class={unassignedNegative ? 'text-destructive' : ''}
            />

            <div class="md:col-span-2" data-test="underfunded-buckets">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Underfunded buckets
                        </CardTitle>
                        <span class="text-sm tabular-nums"
                            >{cards.budget.underfunded.length}</span
                        >
                    </CardHeader>
                    <CardContent>
                        {#if cards.budget.underfunded.length === 0}
                            <p class="text-sm text-muted-foreground">
                                Every bucket has enough for this month and any
                                rolled-forward gaps. Nice.
                            </p>
                        {:else}
                            {#each cards.budget.underfunded as bucket (bucket.id)}
                                <MoneyRow
                                    label={bucket.name}
                                    amount="Fund"
                                    detail="Needs more this month"
                                />
                            {/each}
                            <Button variant="link" class="px-0" asChild>
                                {#snippet children(props)}
                                    <Link
                                        href={toUrl(plan())}
                                        class={props.class}
                                    >
                                        Open Plan
                                    </Link>
                                {/snippet}
                            </Button>
                        {/if}
                    </CardContent>
                </Card>
            </div>
        </div>
    {/if}

    {#if shown('top_categories')}
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    Top categories
                </CardTitle>
                <span class="text-sm text-muted-foreground">
                    This month · {cards.top_categories.total}
                </span>
            </CardHeader>
            <CardContent class="flex flex-col gap-3">
                {#if cards.top_categories.items.length === 0}
                    <p class="text-sm text-muted-foreground">
                        No spending recorded this month yet.
                    </p>
                {:else}
                    {#each cards.top_categories.items as item (item.name)}
                        <div class="flex items-center gap-3">
                            <div class="w-40 shrink-0">
                                <CategoryPill name={item.name} type={item.type} />
                            </div>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-primary"
                                    style="width: {Math.round(item.ratio * 100)}%"
                                ></div>
                            </div>
                            <span class="w-24 shrink-0 text-right font-mono text-sm">
                                {item.spent}
                            </span>
                        </div>
                    {/each}
                {/if}
            </CardContent>
        </Card>
    {/if}

    {#if shown('net_worth')}
        <div class="grid gap-4 md:grid-cols-3" data-test="net-worth-cards">
            <FinanceSummary
                title="Investable assets"
                value={cards.net_worth.investable}
                description="Long-term portfolio, excluding home"
            />
            <FinanceSummary
                title="Total net worth"
                value={cards.net_worth.total}
                description="Assets minus liabilities, including home"
            />
            <FinanceSummary
                title="Target gap"
                value={cards.net_worth.targetGap ?? '—'}
                description={cards.net_worth.targetGap
                    ? 'Household target minus current investable'
                    : 'Save a household plan on Net Worth'}
            >
                {#snippet action()}
                    <div class="flex flex-col items-end gap-2">
                        <FreshnessIndicator
                            label={cards.net_worth.freshness.label}
                            status={cards.net_worth.freshness.status}
                        />
                        <Button variant="link" class="px-0" asChild>
                            {#snippet children(props)}
                                <Link
                                    href={toUrl(netWorth())}
                                    class={props.class}
                                >
                                    {cards.net_worth.hasPositions
                                        ? 'Open Net Worth'
                                        : 'Add first financial position'}
                                </Link>
                            {/snippet}
                        </Button>
                    </div>
                {/snippet}
            </FinanceSummary>
        </div>
    {/if}

    {#if shown('review_queue') || shown('recurring') || shown('goals') || shown('investments') || shown('scenarios')}
        <div
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            data-test="parity-cards"
        >
            {#if shown('review_queue')}
                <FinanceSummary
                    title="Review queue"
                    value={String(cards.review_queue.count)}
                    description="Transactions awaiting review"
                >
                    {#snippet action()}
                        <Button variant="link" class="px-0" asChild>
                            {#snippet children(props)}
                                <Link
                                    href={toUrl(transactions())}
                                    class={props.class}
                                >
                                    Review
                                </Link>
                            {/snippet}
                        </Button>
                    {/snippet}
                </FinanceSummary>
            {/if}

            {#if shown('recurring')}
                <FinanceSummary
                    title="Recurring"
                    value={String(cards.recurring.count)}
                    description="Confirmed schedules"
                >
                    {#snippet action()}
                        <Button variant="link" class="px-0" asChild>
                            {#snippet children(props)}
                                <Link
                                    href={toUrl(recurring())}
                                    class={props.class}
                                >
                                    Open Recurring
                                </Link>
                            {/snippet}
                        </Button>
                    {/snippet}
                </FinanceSummary>
            {/if}

            {#if shown('goals')}
                <FinanceSummary
                    title="Goals"
                    value={String(cards.goals.count)}
                    description="Active goals"
                >
                    {#snippet action()}
                        <Button variant="link" class="px-0" asChild>
                            {#snippet children(props)}
                                <Link
                                    href={toUrl(goals())}
                                    class={props.class}
                                >
                                    Open Goals
                                </Link>
                            {/snippet}
                        </Button>
                    {/snippet}
                </FinanceSummary>
            {/if}

            {#if shown('investments')}
                <FinanceSummary
                    title="Investments"
                    value={cards.investments.total}
                    description="{cards.investments.count} holding(s) tracked"
                >
                    {#snippet action()}
                        <Button variant="link" class="px-0" asChild>
                            {#snippet children(props)}
                                <Link
                                    href={toUrl(holdings())}
                                    class={props.class}
                                >
                                    Open Holdings
                                </Link>
                            {/snippet}
                        </Button>
                    {/snippet}
                </FinanceSummary>
            {/if}

            {#if shown('scenarios')}
                <FinanceSummary
                    title="Scenarios"
                    value={String(cards.scenarios.count)}
                    description="Saved what-if plans"
                >
                    {#snippet action()}
                        <Button variant="link" class="px-0" asChild>
                            {#snippet children(props)}
                                <Link
                                    href={toUrl(scenarios())}
                                    class={props.class}
                                >
                                    Open Scenarios
                                </Link>
                            {/snippet}
                        </Button>
                    {/snippet}
                </FinanceSummary>
            {/if}
        </div>
    {/if}

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Button variant="outline" class="h-auto justify-start p-4" asChild>
            {#snippet children(props)}
                <Link href={toUrl(accounts())} class={props.class}>Accounts</Link
                >
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

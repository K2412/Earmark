<script module lang="ts">
    import { index } from '@/routes/household/plan';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Plan',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, Link, router } from '@inertiajs/svelte';
    import PlanController from '@/actions/App/Http/Controllers/Household/PlanController';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import { index as planIndex } from '@/routes/household/plan';
    import { PlanState, type PlanRow } from './PlanState.svelte';

    let {
        year,
        month,
        monthLabel,
        rows,
        categories,
        previous,
        next,
        defaults,
    }: {
        year: number;
        month: number;
        monthLabel: string;
        rows: PlanRow[];
        categories: { id: string; name: string; type: string }[];
        previous: { year: number; month: number };
        next: { year: number; month: number };
        defaults: { target_date: string };
    } = $props();

    const plan = new PlanState({
        year: 0,
        month: 0,
        monthLabel: '',
        rows: [],
    });

    $effect(() => {
        plan.year = year;
        plan.month = month;
        plan.monthLabel = monthLabel;
        plan.rows = rows;
    });

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    const monthQuery = (cursor: { year: number; month: number }) =>
        planIndex({ query: { year: cursor.year, month: cursor.month } });

    let obBucketId = $state('');
    let obAmount = $state(0);

    function saveObligation(): void {
        if (!obBucketId) {
            return;
        }

        router.post(
            PlanController.setObligation(obBucketId).url,
            {
                monthly_obligation: Number(obAmount),
                effective_year: year,
                effective_month: month,
            },
            { preserveScroll: true },
        );
    }
</script>

<AppHead title="Plan" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between gap-4">
        <Heading title="Plan" />
        <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" asChild>
                {#snippet children(props)}
                    <Link
                        href={toUrl(monthQuery(previous))}
                        class={props.class}
                        data-test="plan-prev-month"
                    >
                        Prev
                    </Link>
                {/snippet}
            </Button>
            <h2 class="text-lg font-semibold">{plan.monthLabel}</h2>
            <Button variant="outline" size="sm" asChild>
                {#snippet children(props)}
                    <Link
                        href={toUrl(monthQuery(next))}
                        class={props.class}
                        data-test="plan-next-month"
                    >
                        Next
                    </Link>
                {/snippet}
            </Button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border">
        <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">Bucket</th>
                    <th class="px-4 py-3 text-right font-medium">Obligation</th>
                    <th class="px-4 py-3 text-right font-medium">Rolled-fwd</th>
                    <th class="px-4 py-3 text-right font-medium">Needed</th>
                    <th class="px-4 py-3 text-right font-medium">Available</th>
                    <th class="px-4 py-3 font-medium">Funded</th>
                </tr>
            </thead>
            <tbody>
                {#each plan.rows as row (row.id)}
                    {@const status = plan.statusFor(row)}
                    {@const funded =
                        row.needed_cents > 0
                            ? Math.max(
                                  0,
                                  Math.min(1, row.available_cents / row.needed_cents),
                              )
                            : 1}
                    <tr class="border-t">
                        <td class="px-4 py-3">{row.name}</td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {row.obligation}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {row.rolled}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums">
                            {row.needed}
                        </td>
                        <td
                            class="px-4 py-3 text-right tabular-nums {status ===
                            'Negative'
                                ? 'text-destructive'
                                : status === 'Underfunded'
                                  ? 'text-amber-600'
                                  : ''}"
                        >
                            {row.available}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div
                                    class="h-2 w-28 shrink-0 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full {status ===
                                        'Negative'
                                            ? 'bg-destructive'
                                            : status === 'Underfunded'
                                              ? 'bg-amber-500'
                                              : 'bg-emerald-500'}"
                                        style="width: {Math.round(funded * 100)}%"
                                    ></div>
                                </div>
                                {#if status !== 'OK'}
                                    <span
                                        class="text-xs {status === 'Negative'
                                            ? 'text-destructive'
                                            : 'text-amber-600'}"
                                    >
                                        {status}
                                    </span>
                                {/if}
                            </div>
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <Form
            {...PlanController.storeBucket.form({
                query: { year, month },
            })}
            class="space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add bucket</h2>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />
                <div class="grid gap-2">
                    <Label for="bucket-name">Name</Label>
                    <Input id="bucket-name" name="name" required />
                    <InputError message={errors.name} />
                </div>
                <div class="grid gap-2">
                    <Label for="kind">Kind</Label>
                    <select id="kind" name="kind" class={selectClass}>
                        <option value="ongoing">Ongoing</option>
                        <option value="goal">Goal</option>
                    </select>
                    <InputError message={errors.kind} />
                </div>
                <div class="grid gap-2">
                    <Label for="monthly_obligation">Monthly obligation (cents)</Label>
                    <Input
                        id="monthly_obligation"
                        name="monthly_obligation"
                        type="number"
                        min="0"
                        step="1"
                        value="0"
                    />
                    <InputError message={errors.monthly_obligation} />
                </div>
                <div class="grid gap-2">
                    <Label for="target_date">Target date</Label>
                    <Input
                        id="target_date"
                        name="target_date"
                        type="date"
                        required
                        value={defaults.target_date}
                    />
                    <InputError message={errors.target_date} />
                </div>
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="submit-create-bucket"
                >
                    Create bucket
                </Button>
            {/snippet}
        </Form>

        <Form
            {...PlanController.storeCategory.form({
                query: { year, month },
            })}
            class="space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add category</h2>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />
                <div class="grid gap-2">
                    <Label for="category-name">Name</Label>
                    <Input id="category-name" name="name" required />
                    <InputError message={errors.name} />
                </div>
                <div class="grid gap-2">
                    <Label for="type">Type</Label>
                    <select id="type" name="type" class={selectClass}>
                        <option value="other">Other</option>
                        <option value="income">Income</option>
                        <option value="housing">Housing</option>
                        <option value="transportation">Transportation</option>
                        <option value="food">Food</option>
                        <option value="household">Household</option>
                        <option value="personal">Personal</option>
                        <option value="health">Health</option>
                        <option value="debt">Debt</option>
                        <option value="savings">Savings</option>
                        <option value="fees">Fees</option>
                    </select>
                    <InputError message={errors.type} />
                </div>
                {#if categories.length > 0}
                    <p class="text-sm text-muted-foreground">
                        {categories.length} categories already on this household.
                    </p>
                {/if}
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="submit-create-category"
                >
                    Create category
                </Button>
            {/snippet}
        </Form>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <Form
            {...PlanController.assign.form()}
            class="space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Move money</h2>
                <p class="text-sm text-muted-foreground">
                    Move funds between buckets for {plan.monthLabel}. Every cent is
                    conserved.
                </p>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />
                <input type="hidden" name="year" value={year} />
                <input type="hidden" name="month" value={month} />
                <div class="grid gap-2">
                    <Label for="from_bucket_id">From</Label>
                    <select id="from_bucket_id" name="from_bucket_id" class={selectClass}>
                        <option value="">Select a bucket</option>
                        {#each plan.rows as row (row.id)}
                            <option value={row.id}>{row.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.from_bucket_id} />
                </div>
                <div class="grid gap-2">
                    <Label for="to_bucket_id">To</Label>
                    <select id="to_bucket_id" name="to_bucket_id" class={selectClass}>
                        <option value="">Select a bucket</option>
                        {#each plan.rows as row (row.id)}
                            <option value={row.id}>{row.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.to_bucket_id} />
                </div>
                <div class="grid gap-2">
                    <Label for="assign-amount">Amount (cents)</Label>
                    <Input id="assign-amount" name="amount" type="number" min="1" step="1" />
                    <InputError message={errors.amount} />
                </div>
                <Button type="submit" disabled={processing} data-test="submit-assign">
                    Move money
                </Button>
            {/snippet}
        </Form>

        <div class="space-y-4 rounded-xl border p-4">
            <h2 class="font-semibold">Set monthly obligation</h2>
            <p class="text-sm text-muted-foreground">
                Effective {plan.monthLabel} onwards — prior months are never
                rewritten.
            </p>
            <div class="grid gap-2">
                <Label for="ob-bucket">Bucket</Label>
                <select id="ob-bucket" class={selectClass} bind:value={obBucketId}>
                    <option value="">Select a bucket</option>
                    {#each plan.rows as row (row.id)}
                        <option value={row.id}>{row.name}</option>
                    {/each}
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="ob-amount">Monthly obligation (cents)</Label>
                <Input id="ob-amount" type="number" min="0" step="1" bind:value={obAmount} />
            </div>
            <Button type="button" onclick={saveObligation} data-test="submit-obligation">
                Save obligation
            </Button>
        </div>
    </div>
</div>

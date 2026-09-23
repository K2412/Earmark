<script module lang="ts">
    import { index } from '@/routes/household/recurring';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Recurring',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, router } from '@inertiajs/svelte';
    import RecurringController from '@/actions/App/Http/Controllers/Household/RecurringController';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Schedule = {
        id: string;
        name: string;
        amount: number;
        amount_formatted: string;
        frequency: string;
        next_due_date: string | null;
        status: string;
        detected: boolean;
    };

    type Candidate = {
        name: string;
        frequency: string;
        amount_min: number;
        amount_max: number;
        amount_range: string;
        occurrences: number;
        last_date: string;
    };

    let {
        schedules,
        candidates,
        frequencies,
        defaults,
    }: {
        schedules: Schedule[];
        candidates: Candidate[];
        frequencies: string[];
        defaults: { next_due_date: string };
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    let showForm = $state(false);

    function confirmCandidate(candidate: Candidate): void {
        router.post(
            RecurringController.store.url(),
            {
                name: candidate.name,
                amount: candidate.amount_min,
                frequency: candidate.frequency,
                status: 'confirmed',
                detected: true,
            },
            { preserveScroll: true },
        );
    }

    function dismissCandidate(candidate: Candidate): void {
        router.post(
            RecurringController.store.url(),
            {
                name: candidate.name,
                amount: candidate.amount_min,
                frequency: candidate.frequency,
                status: 'dismissed',
                detected: true,
            },
            { preserveScroll: true },
        );
    }

    function setStatus(schedule: Schedule, status: string): void {
        router.patch(
            RecurringController.update(schedule.id).url,
            {
                name: schedule.name,
                amount: schedule.amount,
                frequency: schedule.frequency,
                next_due_date: schedule.next_due_date,
                status,
            },
            { preserveScroll: true },
        );
    }
</script>

<AppHead title="Recurring" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading
            title="Recurring cash flow"
            description="Detected candidates are suggestions from your reviewed history — confirm or dismiss them. Confirmed schedules drive upcoming cash flow."
        />
        <Button type="button" data-test="open-create-recurring" onclick={() => (showForm = true)}>
            Add recurring item
        </Button>
    </div>

    {#if candidates.length > 0}
        <div class="rounded-xl border">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Detected candidates</h2>
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Payee</th>
                        <th class="px-4 py-3 font-medium">Frequency</th>
                        <th class="px-4 py-3 font-medium">Expected range</th>
                        <th class="px-4 py-3 font-medium">Seen</th>
                        <th class="px-4 py-3 text-right font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    {#each candidates as candidate (candidate.name)}
                        <tr class="border-t" data-test="candidate-row">
                            <td class="px-4 py-3">{candidate.name}</td>
                            <td class="px-4 py-3">{candidate.frequency}</td>
                            <td class="px-4 py-3 font-mono">{candidate.amount_range}</td>
                            <td class="px-4 py-3">{candidate.occurrences}× (last {candidate.last_date})</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" onclick={() => confirmCandidate(candidate)} data-test="confirm-candidate">
                                        Confirm
                                    </Button>
                                    <Button type="button" variant="ghost" onclick={() => dismissCandidate(candidate)}>
                                        Dismiss
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

    <div class="rounded-xl border">
        <h2 class="border-b px-4 py-3 text-sm font-semibold">Confirmed schedules</h2>
        {#if schedules.length === 0}
            <p class="px-4 py-6 text-sm text-muted-foreground">
                No confirmed recurring items yet. Confirm a candidate above or add one manually.
            </p>
        {:else}
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                        <th class="px-4 py-3 font-medium">Frequency</th>
                        <th class="px-4 py-3 font-medium">Next due</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    {#each schedules as schedule (schedule.id)}
                        <tr class="border-t">
                            <td class="px-4 py-3">{schedule.name}{#if schedule.detected}<span class="ml-1 text-xs text-muted-foreground">(detected)</span>{/if}</td>
                            <td class="px-4 py-3 text-right font-mono">{schedule.amount_formatted}</td>
                            <td class="px-4 py-3">{schedule.frequency}</td>
                            <td class="px-4 py-3">{schedule.next_due_date ?? '—'}</td>
                            <td class="px-4 py-3">{schedule.status}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    {#if schedule.status === 'confirmed'}
                                        <Button type="button" variant="ghost" onclick={() => setStatus(schedule, 'paused')}>Pause</Button>
                                    {:else}
                                        <Button type="button" variant="ghost" onclick={() => setStatus(schedule, 'confirmed')}>Resume</Button>
                                    {/if}
                                    <Button type="button" variant="ghost" onclick={() => setStatus(schedule, 'dismissed')}>Remove</Button>
                                </div>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        {/if}
    </div>

    {#if showForm}
        <Form {...RecurringController.store.form()} class="max-w-lg space-y-4 rounded-xl border p-4">
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add recurring item</h2>
                <ErrorSummary errors={Object.entries(errors).map(([fieldId, message]) => ({ fieldId, message }))} />
                <input type="hidden" name="status" value="confirmed" />
                <div class="grid gap-2">
                    <Label for="rec-name">Name</Label>
                    <Input id="rec-name" name="name" required placeholder="Rent" />
                    <InputError message={errors.name} />
                </div>
                <div class="grid gap-2">
                    <Label for="rec-amount">Amount (cents, negative for a bill)</Label>
                    <Input id="rec-amount" name="amount" type="number" step="1" required />
                    <InputError message={errors.amount} />
                </div>
                <div class="grid gap-2">
                    <Label for="rec-frequency">Frequency</Label>
                    <select id="rec-frequency" name="frequency" class={selectClass}>
                        {#each frequencies as frequency (frequency)}
                            <option value={frequency}>{frequency}</option>
                        {/each}
                    </select>
                    <InputError message={errors.frequency} />
                </div>
                <div class="grid gap-2">
                    <Label for="rec-due">Next due date</Label>
                    <Input id="rec-due" name="next_due_date" type="date" value={defaults.next_due_date} />
                    <InputError message={errors.next_due_date} />
                </div>
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="ghost" onclick={() => (showForm = false)}>Cancel</Button>
                    <Button type="submit" disabled={processing} data-test="submit-recurring">Save</Button>
                </div>
            {/snippet}
        </Form>
    {/if}
</div>

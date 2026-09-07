<script module lang="ts">
    import { index } from '@/routes/household/transfers';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Transfers',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import TransferController from '@/actions/App/Http/Controllers/Household/TransferController';
    import ActionableEmptyState from '@/components/ActionableEmptyState.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import { index as transactions } from '@/routes/household/transactions';

    let {
        transfers,
        accounts,
        defaults,
    }: {
        transfers: {
            id: string;
            date: string;
            from: string | null;
            to: string;
            amount: string;
        }[];
        accounts: { id: string; name: string }[];
        defaults: { date: string };
    } = $props();

    let showForm = $state(false);

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';
</script>

<AppHead title="Transfers" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between gap-4">
        <Heading title="Transfers" />
        <div class="flex gap-2">
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(transactions())} class={props.class}
                        >Transactions</Link
                    >
                {/snippet}
            </Button>
            <Button
                type="button"
                data-test="open-create-transfer"
                onclick={() => (showForm = true)}
            >
                New transfer
            </Button>
        </div>
    </div>

    {#if transfers.length === 0 && !showForm}
        <ActionableEmptyState
            title="No transfers yet"
            description="Use this to move money between accounts without affecting category budgets."
        >
            {#snippet action()}
                <Button
                    type="button"
                    data-test="empty-create-transfer"
                    onclick={() => (showForm = true)}
                >
                    New transfer
                </Button>
            {/snippet}
        </ActionableEmptyState>
    {:else if transfers.length > 0}
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">From</th>
                        <th class="px-4 py-3 font-medium">To</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    {#each transfers as transfer (transfer.id)}
                        <tr class="border-t">
                            <td class="px-4 py-3">{transfer.date}</td>
                            <td class="px-4 py-3">{transfer.from}</td>
                            <td class="px-4 py-3">{transfer.to}</td>
                            <td class="px-4 py-3 text-right font-mono">
                                {transfer.amount}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Form
                                    {...TransferController.destroy.form(
                                        transfer.id,
                                    )}
                                >
                                    {#snippet children({ processing })}
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            size="sm"
                                            disabled={processing}
                                            data-test="destroy-transfer-{transfer.id}"
                                        >
                                            Delete
                                        </Button>
                                    {/snippet}
                                </Form>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

    {#if showForm}
        <Form
            {...TransferController.store.form()}
            class="max-w-lg space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Transfer funds</h2>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />

                <div class="grid gap-2">
                    <Label for="date">Date</Label>
                    <Input
                        id="date"
                        name="date"
                        type="date"
                        required
                        value={defaults.date}
                    />
                    <InputError message={errors.date} />
                </div>

                <div class="grid gap-2">
                    <Label for="from_account_id">From account</Label>
                    <select
                        id="from_account_id"
                        name="from_account_id"
                        required
                        class={selectClass}
                    >
                        <option value="">Select source</option>
                        {#each accounts as account (account.id)}
                            <option value={account.id}>{account.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.from_account_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="to_account_id">To account</Label>
                    <select
                        id="to_account_id"
                        name="to_account_id"
                        required
                        class={selectClass}
                    >
                        <option value="">Select destination</option>
                        {#each accounts as account (account.id)}
                            <option value={account.id}>{account.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.to_account_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="amount">Amount (cents)</Label>
                    <Input
                        id="amount"
                        name="amount"
                        type="number"
                        min="1"
                        step="1"
                        required
                    />
                    <InputError message={errors.amount} />
                </div>

                <div class="grid gap-2">
                    <Label for="memo">Memo (optional)</Label>
                    <Input id="memo" name="memo" />
                    <InputError message={errors.memo} />
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        onclick={() => (showForm = false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="submit-create-transfer"
                    >
                        Transfer
                    </Button>
                </div>
            {/snippet}
        </Form>
    {/if}
</div>

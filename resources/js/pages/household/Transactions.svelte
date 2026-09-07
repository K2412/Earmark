<script module lang="ts">
    import { index } from '@/routes/household/transactions';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Transactions',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import TransactionController from '@/actions/App/Http/Controllers/Household/TransactionController';
    import ActionableEmptyState from '@/components/ActionableEmptyState.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import { index as transfers } from '@/routes/household/transfers';
    import { TransactionEntryState } from './TransactionEntryState.svelte';

    let {
        transactions,
        accounts,
        categories,
        buckets,
        defaults,
    }: {
        transactions: {
            id: string;
            date: string;
            account: string | null;
            payee: string;
            category: string;
            bucket: string;
            amount: string;
        }[];
        accounts: { id: string; name: string }[];
        categories: { id: string; name: string }[];
        buckets: { id: string; name: string }[];
        defaults: { date: string };
    } = $props();

    const entry = new TransactionEntryState();
    let showForm = $state(false);

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';
</script>

<AppHead title="Transactions" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between gap-4">
        <Heading title="Transactions" />
        <div class="flex gap-2">
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(transfers())} class={props.class}
                        >Transfers</Link
                    >
                {/snippet}
            </Button>
            <Button
                type="button"
                data-test="open-create-transaction"
                onclick={() => (showForm = true)}
            >
                Add transaction
            </Button>
        </div>
    </div>

    {#if transactions.length === 0 && !showForm}
        <ActionableEmptyState
            title="No transactions yet"
            description="Add one manually. Transfers live on their own page."
        >
            {#snippet action()}
                <Button
                    type="button"
                    data-test="empty-create-transaction"
                    onclick={() => (showForm = true)}
                >
                    Add transaction
                </Button>
            {/snippet}
        </ActionableEmptyState>
    {:else if transactions.length > 0}
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Account</th>
                        <th class="px-4 py-3 font-medium">Payee</th>
                        <th class="px-4 py-3 font-medium">Category</th>
                        <th class="px-4 py-3 font-medium">Bucket</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {#each transactions as transaction (transaction.id)}
                        <tr class="border-t">
                            <td class="px-4 py-3">{transaction.date}</td>
                            <td class="px-4 py-3">{transaction.account}</td>
                            <td class="px-4 py-3">{transaction.payee}</td>
                            <td class="px-4 py-3">{transaction.category}</td>
                            <td class="px-4 py-3">{transaction.bucket}</td>
                            <td class="px-4 py-3 text-right font-mono">
                                {transaction.amount}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

    {#if showForm}
        <Form
            {...TransactionController.store.form()}
            class="max-w-lg space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add transaction</h2>
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
                    <Label for="account_id">Account</Label>
                    <select
                        id="account_id"
                        name="account_id"
                        required
                        class={selectClass}
                    >
                        <option value="">Select an account</option>
                        {#each accounts as account (account.id)}
                            <option value={account.id}>{account.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.account_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="payee">Payee</Label>
                    <Input
                        id="payee"
                        name="payee"
                        required
                        placeholder="Loblaws"
                        onblur={(event) =>
                            entry.suggest(event.currentTarget.value)}
                    />
                    {#if entry.suggestedRuleId}
                        <p class="text-sm text-muted-foreground">
                            Prefilled from payee rule (override below if needed).
                        </p>
                    {/if}
                    <InputError message={errors.payee} />
                </div>

                <div class="grid gap-2">
                    <Label for="category_id">Category</Label>
                    <select
                        id="category_id"
                        name="category_id"
                        class={selectClass}
                        bind:value={entry.categoryId}
                        onchange={() => entry.touchCategory()}
                    >
                        <option value="">(none)</option>
                        {#each categories as category (category.id)}
                            <option value={category.id}>{category.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.category_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="bucket_id">Bucket</Label>
                    <select
                        id="bucket_id"
                        name="bucket_id"
                        class={selectClass}
                        bind:value={entry.bucketId}
                        onchange={() => entry.touchBucket()}
                    >
                        <option value="">(none)</option>
                        {#each buckets as bucket (bucket.id)}
                            <option value={bucket.id}>{bucket.name}</option>
                        {/each}
                    </select>
                    <InputError message={errors.bucket_id} />
                </div>

                <div class="grid gap-2">
                    <Label for="amount">Amount (cents, negative for spend)</Label>
                    <Input id="amount" name="amount" type="number" step="1" required />
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
                        data-test="submit-create-transaction"
                    >
                        Create
                    </Button>
                </div>
            {/snippet}
        </Form>
    {/if}
</div>

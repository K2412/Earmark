<script module lang="ts">
    import { index } from '@/routes/household/accounts';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Accounts',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AccountController from '@/actions/App/Http/Controllers/Household/AccountController';
    import ActionableEmptyState from '@/components/ActionableEmptyState.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    let {
        accounts,
        defaults,
    }: {
        accounts: {
            id: string;
            name: string;
            type: string;
            starting_balance: string;
            starting_balance_date: string;
        }[];
        defaults: { starting_balance_date: string };
    } = $props();

    let showForm = $state(false);

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';
</script>

<AppHead title="Accounts" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading title="Accounts" />
        <Button
            type="button"
            data-test="open-create-account"
            onclick={() => (showForm = true)}
        >
            Add account
        </Button>
    </div>

    {#if accounts.length === 0 && !showForm}
        <ActionableEmptyState
            title="No accounts yet"
            description="Add your first chequing, savings, or credit card account."
        >
            {#snippet action()}
                <Button
                    type="button"
                    data-test="empty-create-account"
                    onclick={() => (showForm = true)}
                >
                    Add account
                </Button>
            {/snippet}
        </ActionableEmptyState>
    {:else if accounts.length > 0}
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Starting balance
                        </th>
                        <th class="px-4 py-3 font-medium">Starting date</th>
                    </tr>
                </thead>
                <tbody>
                    {#each accounts as account (account.id)}
                        <tr class="border-t">
                            <td class="px-4 py-3">{account.name}</td>
                            <td class="px-4 py-3 capitalize">{account.type}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {account.starting_balance}
                            </td>
                            <td class="px-4 py-3">
                                {account.starting_balance_date}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

    {#if showForm}
        <Form
            {...AccountController.store.form()}
            class="max-w-lg space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Add account</h2>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />

                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        placeholder="Chequing"
                    />
                    <InputError message={errors.name} />
                </div>

                <div class="grid gap-2">
                    <Label for="type">Type</Label>
                    <select id="type" name="type" class={selectClass}>
                        <option value="chequing">Chequing</option>
                        <option value="savings">Savings</option>
                        <option value="credit_card">Credit card</option>
                        <option value="cash">Cash</option>
                        <option value="investment">Investment</option>
                        <option value="other">Other</option>
                    </select>
                    <InputError message={errors.type} />
                </div>

                <div class="grid gap-2">
                    <Label for="starting_balance">Starting balance (cents)</Label>
                    <Input
                        id="starting_balance"
                        name="starting_balance"
                        type="number"
                        step="1"
                        value="0"
                    />
                    <InputError message={errors.starting_balance} />
                </div>

                <div class="grid gap-2">
                    <Label for="starting_balance_date">Starting balance date</Label>
                    <Input
                        id="starting_balance_date"
                        name="starting_balance_date"
                        type="date"
                        required
                        value={defaults.starting_balance_date}
                    />
                    <InputError message={errors.starting_balance_date} />
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
                        data-test="submit-create-account"
                    >
                        Create
                    </Button>
                </div>
            {/snippet}
        </Form>
    {/if}
</div>

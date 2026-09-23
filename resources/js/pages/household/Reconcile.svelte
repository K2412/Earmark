<script module lang="ts">
    import { index } from '@/routes/household/reconcile';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Reconcile',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { untrack } from 'svelte';
    import ReconciliationController from '@/actions/App/Http/Controllers/Household/ReconciliationController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Preview = {
        calculated_formatted: string;
        discrepancy: number;
        discrepancy_formatted: string;
        matched: boolean;
    };

    let {
        accounts,
        history,
        defaults,
    }: {
        accounts: { id: string; name: string }[];
        history: { id: string; account: string | null; statement_date: string; statement_balance: string; status: string }[];
        defaults: { statement_date: string };
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    let accountId = $state('');
    let statementDate = $state(untrack(() => defaults.statement_date));
    let statementBalance = $state(0);
    let preview = $state<Preview | null>(null);

    function payload() {
        return {
            account_id: accountId,
            statement_date: statementDate,
            statement_balance: Number(statementBalance),
        };
    }

    function xsrfToken(): string {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

        return match ? decodeURIComponent(match[1]) : '';
    }

    async function checkBalance(): Promise<void> {
        if (!accountId) {
            return;
        }

        const response = await fetch(ReconciliationController.preview.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload()),
        });

        if (response.ok) {
            preview = await response.json();
        }
    }

    function reconcile(): void {
        router.post(ReconciliationController.store.url(), payload(), {
            preserveScroll: true,
            onSuccess: () => (preview = null),
        });
    }
</script>

<AppHead title="Reconcile" />

<div class="flex flex-col gap-6 p-4">
    <Heading
        title="Reconcile"
        description="Enter a statement's closing date and balance. Reconciliation commits only when your cleared transactions add up to the statement balance."
    />

    <div class="grid max-w-lg gap-4 rounded-xl border p-4">
        <div class="grid gap-2">
            <Label for="account">Account</Label>
            <select id="account" class={selectClass} bind:value={accountId} data-test="recon-account">
                <option value="">Select an account</option>
                {#each accounts as account (account.id)}
                    <option value={account.id}>{account.name}</option>
                {/each}
            </select>
        </div>
        <div class="grid gap-2">
            <Label for="statement-date">Statement date</Label>
            <Input id="statement-date" type="date" bind:value={statementDate} />
        </div>
        <div class="grid gap-2">
            <Label for="statement-balance">Statement balance (cents)</Label>
            <Input id="statement-balance" type="number" step="1" bind:value={statementBalance} data-test="recon-balance" />
        </div>

        <div class="flex items-center gap-2">
            <Button type="button" variant="outline" onclick={checkBalance} data-test="recon-check" disabled={!accountId}>
                Check
            </Button>
            <Button type="button" onclick={reconcile} data-test="recon-submit" disabled={!accountId}>
                Reconcile
            </Button>
        </div>

        {#if preview}
            <div
                class="rounded-lg p-3 text-sm {preview.matched ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900'}"
                data-test="recon-result"
            >
                Calculated cleared balance: {preview.calculated_formatted}.
                {#if preview.matched}
                    Matches the statement — ready to reconcile.
                {:else}
                    Off by {preview.discrepancy_formatted}. Clear or correct rows until it matches.
                {/if}
            </div>
        {/if}
    </div>

    {#if history.length > 0}
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Account</th>
                        <th class="px-4 py-3 font-medium">Statement date</th>
                        <th class="px-4 py-3 text-right font-medium">Statement balance</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {#each history as row (row.id)}
                        <tr class="border-t">
                            <td class="px-4 py-3">{row.account}</td>
                            <td class="px-4 py-3">{row.statement_date}</td>
                            <td class="px-4 py-3 text-right font-mono">{row.statement_balance}</td>
                            <td class="px-4 py-3">{row.status}</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}
</div>

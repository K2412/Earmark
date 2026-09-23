<script module lang="ts">
    import { index } from '@/routes/household/registered';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Registered',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import RegisteredController from '@/actions/App/Http/Controllers/Household/RegisteredController';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Account = {
        id: string;
        name: string;
        type: string;
        owner: string;
        contributions: string;
        room: { amount: string; authoritative: boolean; as_of: string | null };
        hbp: { withdrawn: string; repaid: string; outstanding: string; required_annual: string } | null;
        events: { id: string; type: string; amount: string; occurred_on: string; plan_year: number }[];
    };

    let {
        accounts,
        types,
        members,
        year,
        defaults,
    }: {
        accounts: Account[];
        types: string[];
        members: { id: number; name: string }[];
        year: number;
        defaults: { occurred_on: string };
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    const typeLabel: Record<string, string> = {
        contribution: 'Contribution',
        hbp_withdrawal: 'HBP withdrawal',
        hbp_repayment: 'HBP repayment',
        room_override: 'CRA room (authoritative)',
    };

    let showForm = $state(false);
    let eventType = $state('contribution');
</script>

<AppHead title="Registered accounts" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading title="Registered accounts" description={`TFSA, RRSP, FHSA, RESP context for ${year}. Contribution room is an estimate until you enter an authoritative CRA value.`} />
        <Button type="button" data-test="open-record-event" onclick={() => (showForm = true)}>Record event</Button>
    </div>

    {#if accounts.length === 0}
        <div class="rounded-xl border p-6 text-sm text-muted-foreground">
            No registered accounts yet. Add a TFSA, RRSP, FHSA, or RESP account from the Accounts page first.
        </div>
    {:else}
        <div class="flex flex-col gap-3">
            {#each accounts as account (account.id)}
                <div class="rounded-xl border p-4" data-test="registered-account">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-medium">{account.name} <span class="text-xs uppercase text-muted-foreground">{account.type}</span></h2>
                            <p class="text-xs text-muted-foreground">Owner: {account.owner}</p>
                        </div>
                        <div class="text-right text-sm">
                            <div>Contributed {year}: <span class="font-mono">{account.contributions}</span></div>
                            <div>
                                Room: <span class="font-mono">{account.room.amount}</span>
                                {#if account.room.authoritative}
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">CRA · {account.room.as_of}</span>
                                {:else}
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">estimate</span>
                                {/if}
                            </div>
                        </div>
                    </div>

                    {#if account.hbp}
                        <div class="mt-3 grid gap-2 rounded-lg bg-muted/40 p-3 text-sm sm:grid-cols-4">
                            <div><span class="text-muted-foreground">HBP withdrawn</span><br />{account.hbp.withdrawn}</div>
                            <div><span class="text-muted-foreground">Repaid</span><br />{account.hbp.repaid}</div>
                            <div><span class="text-muted-foreground">Outstanding</span><br />{account.hbp.outstanding}</div>
                            <div><span class="text-muted-foreground">Required / yr</span><br />{account.hbp.required_annual}</div>
                        </div>
                    {/if}

                    {#if account.events.length > 0}
                        <table class="mt-3 w-full text-xs">
                            <tbody>
                                {#each account.events as event (event.id)}
                                    <tr class="border-t">
                                        <td class="py-1">{event.occurred_on}</td>
                                        <td class="py-1">{typeLabel[event.type] ?? event.type}</td>
                                        <td class="py-1">{event.plan_year}</td>
                                        <td class="py-1 text-right font-mono">{event.amount}</td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    {/if}
                </div>
            {/each}
        </div>
    {/if}

    {#if showForm}
        <Form {...RegisteredController.store.form()} class="max-w-lg space-y-4 rounded-xl border p-4">
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Record registered event</h2>
                <ErrorSummary errors={Object.entries(errors).map(([fieldId, message]) => ({ fieldId, message }))} />
                <div class="grid gap-2">
                    <Label for="ev-account">Account</Label>
                    <select id="ev-account" name="account_id" class={selectClass} required>
                        <option value="">Select a registered account</option>
                        {#each accounts as account (account.id)}<option value={account.id}>{account.name}</option>{/each}
                    </select>
                    <InputError message={errors.account_id} />
                </div>
                <div class="grid gap-2">
                    <Label for="ev-type">Event type</Label>
                    <select id="ev-type" name="type" class={selectClass} bind:value={eventType}>
                        {#each types as t (t)}<option value={t}>{typeLabel[t] ?? t}</option>{/each}
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="ev-owner">Owner (optional)</Label>
                    <select id="ev-owner" name="owner_user_id" class={selectClass}>
                        <option value="">(unspecified)</option>
                        {#each members as member (member.id)}<option value={member.id}>{member.name}</option>{/each}
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="ev-amount">Amount (cents)</Label>
                    <Input id="ev-amount" name="amount" type="number" min="0" step="1" required />
                    <InputError message={errors.amount} />
                </div>
                <div class="grid gap-2">
                    <Label for="ev-date">Date</Label>
                    <Input id="ev-date" name="occurred_on" type="date" value={defaults.occurred_on} required />
                    <InputError message={errors.occurred_on} />
                </div>
                <div class="grid gap-2">
                    <Label for="ev-year">Plan year</Label>
                    <Input id="ev-year" name="plan_year" type="number" step="1" value={year} required />
                    <InputError message={errors.plan_year} />
                </div>
                {#if eventType === 'room_override'}
                    <div class="grid gap-2">
                        <Label for="ev-asof">CRA statement date (as of)</Label>
                        <Input id="ev-asof" name="as_of" type="date" />
                        <InputError message={errors.as_of} />
                    </div>
                {/if}
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="ghost" onclick={() => (showForm = false)}>Cancel</Button>
                    <Button type="submit" disabled={processing} data-test="submit-event">Record</Button>
                </div>
            {/snippet}
        </Form>
    {/if}
</div>

<script module lang="ts">
    import { index } from '@/routes/household/import';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Import',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ImportController from '@/actions/App/Http/Controllers/Household/ImportController';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import { parseCsv } from '@/lib/csv';
    import {
        buildRowPayload,
        DATE_FORMATS,
        formatCents,
        guessMapping,
        normalizeRow,
        type Mapping,
    } from '@/lib/importCsv';

    let {
        accounts,
    }: {
        accounts: { id: string; name: string }[];
        dateFormats: { value: string; label: string }[];
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    let accountId = $state('');
    let fileMeta = $state<{ name: string; size: number; sha256: string } | null>(
        null,
    );
    let headers = $state<string[]>([]);
    let rawRows = $state<string[][]>([]);
    let fileError = $state<string | null>(null);

    let mapping = $state<Mapping>({
        dateIndex: -1,
        payeeIndex: -1,
        amountMode: 'single',
        amountIndex: -1,
        debitIndex: -1,
        creditIndex: -1,
        dateFormat: 'YYYY-MM-DD',
        sign: 'negative_is_outflow',
    });

    let errors = $state<Record<string, string>>({});
    let processing = $state(false);

    async function sha256Hex(buffer: ArrayBuffer): Promise<string> {
        const digest = await crypto.subtle.digest('SHA-256', buffer);

        return Array.from(new Uint8Array(digest))
            .map((byte) => byte.toString(16).padStart(2, '0'))
            .join('');
    }

    async function onFileChange(
        event: Event & { currentTarget: HTMLInputElement },
    ): Promise<void> {
        fileError = null;
        const file = event.currentTarget.files?.[0];

        if (!file) {
            return;
        }

        try {
            const buffer = await file.arrayBuffer();
            const text = new TextDecoder().decode(buffer);
            const parsed = parseCsv(text);

            if (parsed.headers.length === 0 || parsed.rows.length === 0) {
                fileError = 'This file has no data rows.';
                headers = [];
                rawRows = [];
                fileMeta = null;

                return;
            }

            headers = parsed.headers;
            rawRows = parsed.rows;
            mapping = { ...mapping, ...guessMapping(headers) };
            fileMeta = {
                name: file.name,
                size: file.size,
                sha256: await sha256Hex(buffer),
            };
        } catch {
            fileError = 'Could not read this file.';
        }
    }

    const amountMapped = $derived(
        mapping.amountMode === 'single'
            ? mapping.amountIndex >= 0
            : mapping.debitIndex >= 0 && mapping.creditIndex >= 0,
    );

    const ready = $derived(
        !!fileMeta &&
            accountId !== '' &&
            mapping.dateIndex >= 0 &&
            mapping.payeeIndex >= 0 &&
            amountMapped,
    );

    const previews = $derived(
        ready ? rawRows.slice(0, 10).map((cells) => normalizeRow(cells, mapping)) : [],
    );

    const summary = $derived.by(() => {
        if (!ready) {
            return { total: 0, staged: 0, skipped: 0 };
        }

        let staged = 0;
        let skipped = 0;

        for (const cells of rawRows) {
            if (normalizeRow(cells, mapping).skipReason) {
                skipped += 1;
            } else {
                staged += 1;
            }
        }

        return { total: rawRows.length, staged, skipped };
    });

    function submit(event: SubmitEvent): void {
        event.preventDefault();

        if (!ready || !fileMeta) {
            return;
        }

        const rows = rawRows.map((cells) => buildRowPayload(cells, mapping));

        processing = true;

        router.post(
            ImportController.store.url(),
            {
                account_id: accountId,
                source: 'csv',
                file: fileMeta,
                mapping: {
                    date_format: mapping.dateFormat,
                    amount_mode: mapping.amountMode,
                    sign: mapping.amountMode === 'single' ? mapping.sign : null,
                },
                rows,
            },
            {
                preserveScroll: true,
                onError: (formErrors) => (errors = formErrors),
                onSuccess: () => (errors = {}),
                onFinish: () => (processing = false),
            },
        );
    }
</script>

<AppHead title="Import CSV" />

<div class="flex flex-col gap-6 p-4">
    <Heading
        title="Import CSV"
        description="Map your statement columns, preview the normalized rows, then stage them for review. Nothing reaches the ledger until you promote it."
    />

    <ErrorSummary
        errors={Object.entries(errors).map(([fieldId, message]) => ({
            fieldId,
            message,
        }))}
    />

    <form onsubmit={submit} class="flex flex-col gap-6">
        <div class="grid max-w-lg gap-2">
            <Label for="account_id">Account</Label>
            <select
                id="account_id"
                class={selectClass}
                bind:value={accountId}
                data-test="import-account"
            >
                <option value="">Select an account</option>
                {#each accounts as account (account.id)}
                    <option value={account.id}>{account.name}</option>
                {/each}
            </select>
            <InputError message={errors.account_id} />
        </div>

        <div class="grid max-w-lg gap-2">
            <Label for="file">Statement file (CSV)</Label>
            <input
                id="file"
                type="file"
                accept=".csv,text/csv"
                onchange={onFileChange}
                class={selectClass}
                data-test="import-file"
            />
            {#if fileMeta}
                <p class="text-sm text-muted-foreground">
                    {fileMeta.name} · {rawRows.length} rows
                </p>
            {/if}
            <InputError message={fileError ?? undefined} />
        </div>

        {#if headers.length > 0}
            <div class="grid gap-4 rounded-xl border p-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="date_col">Date column</Label>
                    <select
                        id="date_col"
                        class={selectClass}
                        bind:value={mapping.dateIndex}
                        data-test="map-date"
                    >
                        <option value={-1}>Select a column</option>
                        {#each headers as header, i (i)}
                            <option value={i}>{header}</option>
                        {/each}
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="date_format">Date format</Label>
                    <select
                        id="date_format"
                        class={selectClass}
                        bind:value={mapping.dateFormat}
                        data-test="map-date-format"
                    >
                        {#each DATE_FORMATS as format (format)}
                            <option value={format}>{format}</option>
                        {/each}
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="payee_col">Description column</Label>
                    <select
                        id="payee_col"
                        class={selectClass}
                        bind:value={mapping.payeeIndex}
                        data-test="map-payee"
                    >
                        <option value={-1}>Select a column</option>
                        {#each headers as header, i (i)}
                            <option value={i}>{header}</option>
                        {/each}
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="amount_mode">Amount columns</Label>
                    <select
                        id="amount_mode"
                        class={selectClass}
                        bind:value={mapping.amountMode}
                        data-test="map-amount-mode"
                    >
                        <option value="single">Single amount column</option>
                        <option value="debit_credit">
                            Separate debit / credit columns
                        </option>
                    </select>
                </div>

                {#if mapping.amountMode === 'single'}
                    <div class="grid gap-2">
                        <Label for="amount_col">Amount column</Label>
                        <select
                            id="amount_col"
                            class={selectClass}
                            bind:value={mapping.amountIndex}
                            data-test="map-amount"
                        >
                            <option value={-1}>Select a column</option>
                            {#each headers as header, i (i)}
                                <option value={i}>{header}</option>
                            {/each}
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="sign">Sign convention</Label>
                        <select
                            id="sign"
                            class={selectClass}
                            bind:value={mapping.sign}
                            data-test="map-sign"
                        >
                            <option value="negative_is_outflow">
                                Negative amounts are spending
                            </option>
                            <option value="positive_is_outflow">
                                Positive amounts are spending
                            </option>
                        </select>
                    </div>
                {:else}
                    <div class="grid gap-2">
                        <Label for="debit_col">Debit (money out) column</Label>
                        <select
                            id="debit_col"
                            class={selectClass}
                            bind:value={mapping.debitIndex}
                            data-test="map-debit"
                        >
                            <option value={-1}>Select a column</option>
                            {#each headers as header, i (i)}
                                <option value={i}>{header}</option>
                            {/each}
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="credit_col">Credit (money in) column</Label>
                        <select
                            id="credit_col"
                            class={selectClass}
                            bind:value={mapping.creditIndex}
                            data-test="map-credit"
                        >
                            <option value={-1}>Select a column</option>
                            {#each headers as header, i (i)}
                                <option value={i}>{header}</option>
                            {/each}
                        </select>
                    </div>
                {/if}
            </div>
        {/if}

        {#if ready}
            <div class="overflow-x-auto rounded-xl border" data-test="import-preview">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Payee</th>
                            <th class="px-4 py-3 text-right font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each previews as row, i (i)}
                            <tr class="border-t {row.skipReason ? 'text-muted-foreground' : ''}">
                                <td class="px-4 py-3">{row.date ?? '—'}</td>
                                <td class="px-4 py-3">{row.payee || '—'}</td>
                                <td class="px-4 py-3 text-right font-mono">
                                    {row.amountCents === null
                                        ? '—'
                                        : formatCents(row.amountCents)}
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    {row.skipReason ?? row.warning ?? ''}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <p class="text-sm text-muted-foreground">
                {summary.staged} of {summary.total} rows will be staged
                {#if summary.skipped > 0}
                    · {summary.skipped} skipped
                {/if}
                {#if rawRows.length > previews.length}
                    · showing the first {previews.length}
                {/if}
            </p>
        {/if}

        <div class="flex justify-end">
            <Button
                type="submit"
                disabled={!ready || processing}
                data-test="import-submit"
            >
                {processing ? 'Importing…' : 'Import'}
            </Button>
        </div>
    </form>
</div>

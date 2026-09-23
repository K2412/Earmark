<script module lang="ts">
    import { index } from '@/routes/household/portability';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Data & backup',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PortabilityController from '@/actions/App/Http/Controllers/Household/PortabilityController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';

    let {
        counts,
        schemaVersion,
        canDelete,
    }: {
        counts: Record<string, number>;
        schemaVersion: string;
        canDelete: boolean;
    } = $props();

    let restoreError = $state<string | null>(null);
    let restoring = $state(false);

    async function onRestoreFile(event: Event & { currentTarget: HTMLInputElement }): Promise<void> {
        restoreError = null;
        const file = event.currentTarget.files?.[0];

        if (!file) {
            return;
        }

        let bundle: unknown;

        try {
            bundle = JSON.parse(await file.text());
        } catch {
            restoreError = 'That file is not valid JSON.';

            return;
        }

        if (!bundle || typeof bundle !== 'object' || !('data' in bundle)) {
            restoreError = 'That file is not an Earmark backup.';

            return;
        }

        if (!confirm('Restoring replaces this household’s current financial data. Continue?')) {
            return;
        }

        restoring = true;
        router.post(
            PortabilityController.restore.url(),
            { bundle } as unknown as Record<string, never>,
            { preserveScroll: true, onFinish: () => (restoring = false) },
        );
    }

    function deleteAll(): void {
        if (confirm('Delete ALL household financial data? Export a backup first if you want a copy. This cannot be undone.')) {
            router.delete(PortabilityController.destroy.url(), { preserveScroll: true });
        }
    }
</script>

<AppHead title="Data & backup" />

<div class="flex flex-col gap-6 p-4">
    <Heading title="Data & backup" description="Export, restore, or delete your household data. Everything stays on your server — no hosted service." />

    <div class="grid gap-6 md:grid-cols-2">
        <div class="space-y-3 rounded-xl border p-4">
            <h2 class="font-semibold">Export a backup</h2>
            <p class="text-sm text-muted-foreground">
                Download a versioned JSON backup (schema v{schemaVersion}) plus human-readable per-table rows. Secrets are never included.
            </p>
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <a href={PortabilityController.export.url()} class={props.class} data-test="export-backup">
                        Export backup
                    </a>
                {/snippet}
            </Button>
        </div>

        <div class="space-y-3 rounded-xl border p-4">
            <h2 class="font-semibold">Restore a backup</h2>
            <p class="text-sm text-muted-foreground">
                Upload an <code>earmark-backup.json</code>. This replaces the current household's financial data.
            </p>
            <input type="file" accept="application/json,.json" onchange={onRestoreFile} data-test="restore-file" />
            {#if restoring}<p class="text-sm text-muted-foreground">Restoring…</p>{/if}
            {#if restoreError}<p class="text-sm text-red-600">{restoreError}</p>{/if}
        </div>
    </div>

    <div class="rounded-xl border p-4">
        <h2 class="font-semibold">Current data</h2>
        <div class="mt-2 grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
            {#each Object.entries(counts) as [table, count] (table)}
                <div class="flex justify-between border-b py-1">
                    <span class="capitalize text-muted-foreground">{table.replace(/_/g, ' ')}</span>
                    <span class="font-mono">{count}</span>
                </div>
            {/each}
        </div>
    </div>

    {#if canDelete}
        <div class="space-y-3 rounded-xl border border-destructive/40 p-4">
            <h2 class="font-semibold text-destructive">Delete all data</h2>
            <p class="text-sm text-muted-foreground">
                Permanently remove this household's accounts, transactions, budgets, positions, goals, and scenarios.
            </p>
            <Button type="button" variant="destructive" onclick={deleteAll} data-test="delete-all">
                Delete all financial data
            </Button>
        </div>
    {/if}
</div>

<script module lang="ts">
    import { index } from '@/routes/household/rules';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Rules',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import PayeeRuleController from '@/actions/App/Http/Controllers/Household/PayeeRuleController';
    import ActionableEmptyState from '@/components/ActionableEmptyState.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    type Option = { id: string; name: string };

    type Rule = {
        id: string;
        name: string | null;
        label: string;
        pattern: string;
        enabled: boolean;
        category_id: string | null;
        category: string | null;
        bucket_id: string | null;
        bucket: string | null;
        rename_to: string | null;
        hide_from_reports: boolean;
        mark_for_review: boolean;
        auto_apply: boolean;
    };

    type PreviewRow = {
        id: string;
        date: string;
        amount: string;
        payee_before: string;
        payee_after: string;
        category_before: string | null;
        category_after: string | null;
        bucket_before: string | null;
        bucket_after: string | null;
        hidden: boolean;
        needs_review: boolean;
    };

    let {
        rules,
        categories,
        buckets,
    }: {
        rules: Rule[];
        categories: Option[];
        buckets: Option[];
    } = $props();

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    type RuleForm = {
        id: string | null;
        name: string;
        pattern: string;
        enabled: boolean;
        category_id: string;
        bucket_id: string;
        rename_to: string;
        hide_from_reports: boolean;
        mark_for_review: boolean;
        auto_apply: boolean;
    };

    const emptyForm = (): RuleForm => ({
        id: null,
        name: '',
        pattern: '',
        enabled: true,
        category_id: '',
        bucket_id: '',
        rename_to: '',
        hide_from_reports: false,
        mark_for_review: false,
        auto_apply: true,
    });

    let form = $state<RuleForm>(emptyForm());
    let showForm = $state(false);
    let errors = $state<Record<string, string>>({});
    let previewRows = $state<PreviewRow[]>([]);
    let previewTotal = $state<number | null>(null);
    let previewLoading = $state(false);

    function payload() {
        return {
            name: form.name || null,
            pattern: form.pattern,
            enabled: form.enabled,
            category_id: form.category_id || null,
            bucket_id: form.bucket_id || null,
            rename_to: form.rename_to || null,
            hide_from_reports: form.hide_from_reports,
            mark_for_review: form.mark_for_review,
            auto_apply: form.auto_apply,
        };
    }

    function newRule(): void {
        form = emptyForm();
        errors = {};
        previewRows = [];
        previewTotal = null;
        showForm = true;
    }

    function editRule(rule: Rule): void {
        form = {
            id: rule.id,
            name: rule.name ?? '',
            pattern: rule.pattern,
            enabled: rule.enabled,
            category_id: rule.category_id ?? '',
            bucket_id: rule.bucket_id ?? '',
            rename_to: rule.rename_to ?? '',
            hide_from_reports: rule.hide_from_reports,
            mark_for_review: rule.mark_for_review,
            auto_apply: rule.auto_apply,
        };
        errors = {};
        previewRows = [];
        previewTotal = null;
        showForm = true;
    }

    function save(): void {
        const options = {
            preserveScroll: true,
            onError: (formErrors: Record<string, string>) => (errors = formErrors),
            onSuccess: () => {
                showForm = false;
                errors = {};
            },
        };

        if (form.id) {
            router.patch(PayeeRuleController.update(form.id).url, payload(), options);
        } else {
            router.post(PayeeRuleController.store.url(), payload(), options);
        }
    }

    function remove(rule: Rule): void {
        if (confirm(`Delete rule "${rule.label}"?`)) {
            router.delete(PayeeRuleController.destroy(rule.id).url, { preserveScroll: true });
        }
    }

    function apply(rule: Rule): void {
        router.post(PayeeRuleController.apply(rule.id).url, {}, { preserveScroll: true });
    }

    function toggleEnabled(rule: Rule): void {
        router.patch(
            PayeeRuleController.update(rule.id).url,
            {
                name: rule.name,
                pattern: rule.pattern,
                enabled: !rule.enabled,
                category_id: rule.category_id,
                bucket_id: rule.bucket_id,
                rename_to: rule.rename_to,
                hide_from_reports: rule.hide_from_reports,
                mark_for_review: rule.mark_for_review,
                auto_apply: rule.auto_apply,
            },
            { preserveScroll: true },
        );
    }

    function move(indexPosition: number, direction: -1 | 1): void {
        const next = indexPosition + direction;

        if (next < 0 || next >= rules.length) {
            return;
        }

        const ids = rules.map((rule) => rule.id);
        [ids[indexPosition], ids[next]] = [ids[next], ids[indexPosition]];

        router.post(PayeeRuleController.reorder.url(), { ids }, { preserveScroll: true });
    }

    function xsrfToken(): string {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

        return match ? decodeURIComponent(match[1]) : '';
    }

    async function preview(): Promise<void> {
        previewLoading = true;

        try {
            const response = await fetch(PayeeRuleController.preview.url(), {
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
                const data = await response.json();
                previewRows = data.rows;
                previewTotal = data.total;
            }
        } finally {
            previewLoading = false;
        }
    }

    function actionSummary(rule: Rule): string {
        const parts: string[] = [];
        if (rule.rename_to) parts.push(`rename → ${rule.rename_to}`);
        if (rule.category) parts.push(`category → ${rule.category}`);
        if (rule.bucket) parts.push(`bucket → ${rule.bucket}`);
        if (rule.hide_from_reports) parts.push('hide from reports');
        if (rule.mark_for_review) parts.push('mark for review');

        return parts.length > 0 ? parts.join(', ') : 'no actions';
    }
</script>

<AppHead title="Rules" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading
            title="Payee rules"
            description="Rules apply in order. Every action is shown; disabling a rule stops future application without rewriting history. Rules never split or delete."
        />
        <Button type="button" data-test="open-create-rule" onclick={newRule}>
            Add rule
        </Button>
    </div>

    {#if rules.length === 0}
        <ActionableEmptyState
            title="No rules yet"
            description="Create a rule to automatically rename, categorize, or flag matching transactions."
        >
            {#snippet action()}
                <Button type="button" onclick={newRule}>Add rule</Button>
            {/snippet}
        </ActionableEmptyState>
    {:else}
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Order</th>
                        <th class="px-4 py-3 font-medium">Rule</th>
                        <th class="px-4 py-3 font-medium">Matches payee</th>
                        <th class="px-4 py-3 font-medium">Actions</th>
                        <th class="px-4 py-3 font-medium">Enabled</th>
                        <th class="px-4 py-3 text-right font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    {#each rules as rule, i (rule.id)}
                        <tr class="border-t {rule.enabled ? '' : 'opacity-50'}" data-test="rule-row">
                            <td class="px-4 py-3">
                                <div class="flex gap-1">
                                    <Button type="button" variant="ghost" onclick={() => move(i, -1)} disabled={i === 0}>↑</Button>
                                    <Button type="button" variant="ghost" onclick={() => move(i, 1)} disabled={i === rules.length - 1}>↓</Button>
                                </div>
                            </td>
                            <td class="px-4 py-3">{rule.label}</td>
                            <td class="px-4 py-3"><code>{rule.pattern}</code></td>
                            <td class="px-4 py-3 text-xs">{actionSummary(rule)}</td>
                            <td class="px-4 py-3">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" checked={rule.enabled} onchange={() => toggleEnabled(rule)} />
                                    {rule.enabled ? 'On' : 'Off'}
                                </label>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" onclick={() => apply(rule)} data-test="apply-rule">Apply now</Button>
                                    <Button type="button" variant="ghost" onclick={() => editRule(rule)}>Edit</Button>
                                    <Button type="button" variant="ghost" onclick={() => remove(rule)}>Delete</Button>
                                </div>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

    {#if showForm}
        <div class="max-w-lg space-y-4 rounded-xl border p-4" data-test="rule-form">
            <h2 class="font-semibold">{form.id ? 'Edit rule' : 'Add rule'}</h2>
            <ErrorSummary errors={Object.entries(errors).map(([fieldId, message]) => ({ fieldId, message }))} />

            <div class="grid gap-2">
                <Label for="rule-name">Name (optional)</Label>
                <Input id="rule-name" bind:value={form.name} placeholder="Groceries" />
            </div>
            <div class="grid gap-2">
                <Label for="rule-pattern">Matches payee containing</Label>
                <Input id="rule-pattern" bind:value={form.pattern} placeholder="loblaws" />
                <InputError message={errors.pattern} />
            </div>
            <div class="grid gap-2">
                <Label for="rule-rename">Rename to (optional)</Label>
                <Input id="rule-rename" bind:value={form.rename_to} placeholder="Loblaws" />
            </div>
            <div class="grid gap-2">
                <Label for="rule-category">Set category</Label>
                <select id="rule-category" class={selectClass} bind:value={form.category_id}>
                    <option value="">(no change)</option>
                    {#each categories as category (category.id)}
                        <option value={category.id}>{category.name}</option>
                    {/each}
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="rule-bucket">Set bucket</Label>
                <select id="rule-bucket" class={selectClass} bind:value={form.bucket_id}>
                    <option value="">(no change)</option>
                    {#each buckets as bucket (bucket.id)}
                        <option value={bucket.id}>{bucket.name}</option>
                    {/each}
                </select>
            </div>
            <div class="flex flex-col gap-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" bind:checked={form.hide_from_reports} /> Hide matches from reports
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" bind:checked={form.mark_for_review} /> Mark matches for review
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" bind:checked={form.enabled} /> Enabled
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" bind:checked={form.auto_apply} /> Suggest automatically on new entries
                </label>
            </div>

            <div class="flex items-center gap-2">
                <Button type="button" variant="outline" onclick={preview} data-test="preview-rule">
                    {previewLoading ? 'Checking…' : 'Preview matches'}
                </Button>
                {#if previewTotal !== null}
                    <span class="text-sm text-muted-foreground">{previewTotal} matching transaction(s)</span>
                {/if}
            </div>

            {#if previewRows.length > 0}
                <div class="overflow-x-auto rounded-lg border" data-test="rule-preview">
                    <table class="w-full text-xs">
                        <thead class="bg-muted/50 text-left">
                            <tr>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Payee</th>
                                <th class="px-3 py-2">Category</th>
                                <th class="px-3 py-2">Bucket</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each previewRows as row (row.id)}
                                <tr class="border-t">
                                    <td class="px-3 py-2">{row.date}</td>
                                    <td class="px-3 py-2">{row.payee_before} → {row.payee_after}</td>
                                    <td class="px-3 py-2">{row.category_before ?? '—'} → {row.category_after ?? '—'}</td>
                                    <td class="px-3 py-2">{row.bucket_before ?? '—'} → {row.bucket_after ?? '—'}</td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            {/if}

            <div class="flex justify-end gap-2">
                <Button type="button" variant="ghost" onclick={() => (showForm = false)}>Cancel</Button>
                <Button type="button" onclick={save} data-test="submit-rule">Save rule</Button>
            </div>
        </div>
    {/if}
</div>

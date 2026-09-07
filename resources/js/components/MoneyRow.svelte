<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    let {
        label,
        amount,
        detail,
        emphasis = false,
        class: className = '',
    }: {
        label: string;
        amount: string;
        detail?: string | Snippet;
        emphasis?: boolean;
        class?: string;
    } = $props();
</script>

<div
    class={cn(
        'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 py-3',
        className,
    )}
    aria-label="{label}: {amount}"
    data-test="money-row"
>
    <div class="min-w-0">
        <div class={cn('truncate', emphasis && 'font-medium')}>{label}</div>
        {#if detail}
            <div class="text-sm text-muted-foreground">
                {#if typeof detail === 'string'}
                    {detail}
                {:else}
                    {@render detail()}
                {/if}
            </div>
        {/if}
    </div>
    <div class={cn('text-right tabular-nums', emphasis && 'font-semibold')}>
        {amount}
    </div>
</div>

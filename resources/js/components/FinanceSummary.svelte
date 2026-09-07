<script lang="ts">
    import type { Snippet } from 'svelte';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { cn } from '@/lib/utils';

    let {
        title,
        value,
        description = '',
        action,
        class: className = '',
    }: {
        title: string;
        value: string;
        description?: string;
        action?: Snippet;
        class?: string;
    } = $props();
</script>

<div aria-label={title} data-test="finance-summary">
<Card class={cn('gap-4', className)}>
    <CardHeader>
        <CardDescription>{title}</CardDescription>
        <CardTitle class="text-2xl tabular-nums">{value}</CardTitle>
    </CardHeader>
    {#if description || action}
        <CardContent class="flex flex-wrap items-end justify-between gap-3">
            {#if description}
                <p class="text-sm text-muted-foreground">{description}</p>
            {:else}
                <span></span>
            {/if}
            {#if action}
                {@render action()}
            {/if}
        </CardContent>
    {/if}
</Card>
</div>

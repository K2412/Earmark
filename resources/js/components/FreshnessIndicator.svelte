<script lang="ts">
    import Clock3 from '@lucide/svelte/icons/clock-3';
    import { Badge } from '@/components/ui/badge';
    import { cn } from '@/lib/utils';

    let {
        label,
        status,
        class: className = '',
    }: {
        label: string;
        status: 'current' | 'stale' | 'unknown';
        class?: string;
    } = $props();
</script>

<Badge
    variant={status === 'stale' ? 'destructive' : 'outline'}
    class={cn('gap-1', className)}
    aria-label="Valuation freshness: {label}"
    data-status={status}
    data-test="freshness-indicator"
>
    <Clock3 aria-hidden="true" />
    {label}
</Badge>

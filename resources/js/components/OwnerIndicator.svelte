<script lang="ts">
    import UserRound from '@lucide/svelte/icons/user-round';
    import { Badge } from '@/components/ui/badge';
    import { cn } from '@/lib/utils';

    let {
        label,
        class: className = '',
    }: {
        label: string;
        class?: string;
    } = $props();
</script>

<Badge
    variant="outline"
    class={cn('gap-1', className)}
    aria-label="Owner: {label}"
    data-test="owner-indicator"
>
    <UserRound aria-hidden="true" />
    {label}
</Badge>

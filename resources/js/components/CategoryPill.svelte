<script lang="ts">
    import { categoryStyle } from '@/lib/categories';

    let {
        name,
        type = null,
    }: {
        name: string | null;
        type?: string | null;
    } = $props();

    const style = $derived(categoryStyle(type));
</script>

{#if name}
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {style.classes}"
    >
        <span aria-hidden="true">{style.icon}</span>
        {name}
    </span>
{:else}
    <span class="text-xs text-muted-foreground">Uncategorized</span>
{/if}

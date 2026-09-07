<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    let {
        title,
        description,
        action,
        class: className = '',
    }: {
        title: string;
        description: string;
        action: Snippet;
        class?: string;
    } = $props();
</script>

<section
    class={cn(
        'flex flex-col items-center gap-4 rounded-xl border border-dashed p-8 text-center sm:p-10',
        className,
    )}
    aria-labelledby="actionable-empty-title"
    data-test="actionable-empty-state"
>
    <div class="flex max-w-md flex-col gap-2">
        <h2 id="actionable-empty-title" class="font-semibold">{title}</h2>
        <p class="text-sm text-muted-foreground">{description}</p>
    </div>
    {@render action()}
</section>

<script lang="ts">
    import CircleAlert from '@lucide/svelte/icons/circle-alert';
    import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

    export type ErrorSummaryItem = {
        fieldId: string;
        message: string;
    };

    let {
        errors,
        title = 'There is a problem',
    }: {
        errors: ErrorSummaryItem[];
        title?: string;
    } = $props();
</script>

{#if errors.length > 0}
    <div data-test="error-summary">
    <Alert variant="destructive">
        <CircleAlert aria-hidden="true" />
        <AlertTitle>{title}</AlertTitle>
        <AlertDescription>
            <ul class="list-inside list-disc">
                {#each errors as error (`${error.fieldId}:${error.message}`)}
                    <li>
                        <a
                            href="#{error.fieldId}"
                            class="font-medium underline underline-offset-4"
                        >
                            {error.message}
                        </a>
                    </li>
                {/each}
            </ul>
        </AlertDescription>
    </Alert>
    </div>
{/if}

<script module lang="ts">
    export const layout = {
        title: 'Registration disabled',
        description: 'Household access is invite-only',
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { login } from '@/routes';
</script>

<AppHead title="Registration disabled" />

<div class="space-y-6 text-center">
    <p class="text-sm text-muted-foreground">
        Public registration is disabled. Ask the household owner for an invite
        link.
    </p>

    <TextLink href={login()} class="underline underline-offset-4">
        Return to log in
    </TextLink>
</div>

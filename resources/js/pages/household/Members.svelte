<script module lang="ts">
    import { index } from '@/routes/household/members';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Household members',
                href: index(),
            },
        ],
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import MemberController from '@/actions/App/Http/Controllers/Household/MemberController';
    import AppHead from '@/components/AppHead.svelte';
    import ErrorSummary from '@/components/ErrorSummary.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';

    let {
        members,
        invitations,
        roles,
        inviteUrl = null,
        canInvite,
        canCancel,
    }: {
        members: { id: number; name: string; email: string; role: string }[];
        invitations: {
            id: number;
            code: string;
            email: string;
            role: string;
            expires: string;
            url: string;
        }[];
        roles: { value: string; label: string }[];
        inviteUrl?: string | null;
        canInvite: boolean;
        canCancel: boolean;
    } = $props();

    let showForm = $state(false);
    let copied = $state(false);

    const selectClass =
        'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm';

    async function copyInvite(url: string): Promise<void> {
        await navigator.clipboard.writeText(url);
        copied = true;
    }
</script>

<AppHead title="Household members" />

<div class="flex flex-col gap-6 p-4">
    <div class="flex items-center justify-between">
        <Heading title="Household members" />
        {#if canInvite}
            <Button
                type="button"
                data-test="open-invite-modal"
                onclick={() => (showForm = true)}
            >
                Invite member
            </Button>
        {/if}
    </div>

    {#if inviteUrl}
        <div class="space-y-2 rounded-xl border p-4" data-test="invite-url">
            <p class="font-medium">Invite link generated</p>
            <p class="text-sm text-muted-foreground">
                Copy and send this URL to the invitee. It expires in 14 days and
                can only be used once.
            </p>
            <div class="flex items-center gap-2">
                <Input readonly value={inviteUrl} class="font-mono text-sm" />
                <Button type="button" size="sm" onclick={() => copyInvite(inviteUrl)}>
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </div>
        </div>
    {/if}

    <section class="space-y-2">
        <h2 class="text-lg font-semibold">Members</h2>
        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                    </tr>
                </thead>
                <tbody>
                    {#each members as member (member.id)}
                        <tr class="border-t">
                            <td class="px-4 py-3">{member.name}</td>
                            <td class="px-4 py-3">{member.email}</td>
                            <td class="px-4 py-3">{member.role}</td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-2">
        <h2 class="text-lg font-semibold">Pending invitations</h2>
        {#if invitations.length === 0}
            <p class="text-sm text-muted-foreground">No pending invitations.</p>
        {:else}
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Role</th>
                            <th class="px-4 py-3 font-medium">Expires</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each invitations as invitation (invitation.id)}
                            <tr class="border-t">
                                <td class="px-4 py-3">{invitation.email}</td>
                                <td class="px-4 py-3">{invitation.role}</td>
                                <td class="px-4 py-3">{invitation.expires}</td>
                                <td class="px-4 py-3 text-right">
                                    {#if canCancel}
                                        <Form
                                            {...MemberController.destroyInvitation.form(
                                                invitation.id,
                                            )}
                                        >
                                            {#snippet children({ processing })}
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    size="sm"
                                                    disabled={processing}
                                                    data-test="cancel-invitation-{invitation.id}"
                                                >
                                                    Cancel
                                                </Button>
                                            {/snippet}
                                        </Form>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>

    {#if showForm && canInvite}
        <Form
            {...MemberController.store.form()}
            class="max-w-lg space-y-4 rounded-xl border p-4"
        >
            {#snippet children({ errors, processing })}
                <h2 class="font-semibold">Invite a household member</h2>
                <ErrorSummary
                    errors={Object.entries(errors).map(([fieldId, message]) => ({
                        fieldId,
                        message,
                    }))}
                />

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" name="email" type="email" required />
                    <InputError message={errors.email} />
                </div>

                <div class="grid gap-2">
                    <Label for="role">Role</Label>
                    <select id="role" name="role" class={selectClass}>
                        {#each roles as role (role.value)}
                            <option value={role.value}>{role.label}</option>
                        {/each}
                    </select>
                    <InputError message={errors.role} />
                </div>

                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        onclick={() => (showForm = false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="submit-invite"
                    >
                        Generate link
                    </Button>
                </div>
            {/snippet}
        </Form>
    {/if}
</div>

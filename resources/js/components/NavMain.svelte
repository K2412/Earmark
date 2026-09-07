<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import {
        SidebarGroup,
        SidebarGroupLabel,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';
    import { toUrl } from '@/lib/utils';
    import type { NavItem } from '@/types';

    type PendingNavItem = Pick<NavItem, 'title' | 'icon'> & {
        disabled: true;
    };

    type PrimaryNavItem = NavItem | PendingNavItem;

    let {
        items = [],
    }: {
        items: PrimaryNavItem[];
    } = $props();

    const url = currentUrlState();
</script>

<SidebarGroup class="px-2 py-0">
    <SidebarGroupLabel>Household</SidebarGroupLabel>
    <SidebarMenu>
        {#each items as item (item.title)}
            <SidebarMenuItem>
                {#if 'disabled' in item && item.disabled}
                    <SidebarMenuButton
                        disabled
                        aria-label="{item.title}, coming soon"
                        tooltip="{item.title} — coming soon"
                    >
                        {#if item.icon}
                            <item.icon class="size-4 shrink-0" />
                        {/if}
                        <span>{item.title}</span>
                    </SidebarMenuButton>
                {:else}
                    {@const navItem = item as NavItem}
                    <SidebarMenuButton
                        asChild
                        isActive={url.isCurrentUrl(
                            navItem.href,
                            url.currentUrl,
                        )}
                        tooltip={navItem.title}
                    >
                        {#snippet children(props)}
                            <Link
                                {...props}
                                href={toUrl(navItem.href)}
                                class={props.class}
                            >
                                {#if item.icon}
                                    <item.icon class="size-4 shrink-0" />
                                {/if}
                                <span>{item.title}</span>
                            </Link>
                        {/snippet}
                    </SidebarMenuButton>
                {/if}
            </SidebarMenuItem>
        {/each}
    </SidebarMenu>
</SidebarGroup>

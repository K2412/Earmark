<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowLeftRight from '@lucide/svelte/icons/arrow-left-right';
    import CalendarRange from '@lucide/svelte/icons/calendar-range';
    import Landmark from '@lucide/svelte/icons/landmark';
    import LayoutGrid from '@lucide/svelte/icons/layout-grid';
    import TrendingUp from '@lucide/svelte/icons/trending-up';
    import Upload from '@lucide/svelte/icons/upload';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavFooter from '@/components/NavFooter.svelte';
    import NavMain from '@/components/NavMain.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { index as accounts } from '@/routes/household/accounts';
    import { index as importStatements } from '@/routes/household/import';
    import { show as netWorth } from '@/routes/household/net-worth';
    import { index as plan } from '@/routes/household/plan';
    import { index as transactions } from '@/routes/household/transactions';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const mainNavItems = [
        {
            title: 'Overview',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Plan',
            href: plan(),
            icon: CalendarRange,
        },
        {
            title: 'Transactions',
            href: transactions(),
            icon: ArrowLeftRight,
        },
        {
            title: 'Import',
            href: importStatements(),
            icon: Upload,
        },
        {
            title: 'Accounts',
            href: accounts(),
            icon: Landmark,
        },
        {
            title: 'Net Worth',
            href: netWorth(),
            icon: TrendingUp,
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href={toUrl(dashboard())}
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
        <NavMain items={mainNavItems} />
    </SidebarContent>

    <SidebarFooter>
        <NavFooter items={[]} />
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}

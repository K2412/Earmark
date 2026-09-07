# Full-Stack Architecture Prompt — Laravel + Inertia v3 + Svelte 5

Use this as a system/context prompt when asking an LLM to build features in this stack. The backend rules are grounded in the Laravel Boost guidelines shipped with this repo; the client state rules are the house pattern in [`patterns-svelte-class-stores.md`](./patterns-svelte-class-stores.md).

---

## Stack Overview

- **Backend**: Laravel 13 on PHP 8.3+ with thin controllers, domain service classes, and Laravel Actions (`lorisleiva/laravel-actions`) for bespoke operations. Validation + authorization live in Form Requests and policies. Tests are Pest. Confirm installed major versions (`composer show --direct`, `package.json`) before relying on any package API — don't assume.
- **Full-stack bridge**: Inertia v3 — no REST API for first-party screens, no client-side router of your own. Laravel owns routing; controllers return `Inertia::render('Page', $props)` and Inertia serializes those props across the wire to a Svelte page component. It's a classic server-driven app that renders as a single-page app.
- **Client-side interactivity**: Svelte 5 (`@inertiajs/svelte`) with TypeScript and **runes**. Presentational, ephemeral state lives in `$state` — either inline in a page, or extracted into a class in a `.svelte.ts` file. Server-owned state arrives as Inertia props and is mutated by visiting a route, never by mutating props in place.
- **Typed routes**: [Laravel Wayfinder](https://github.com/laravel/wayfinder) generates TypeScript functions for named routes and controller actions. Import route helpers from `@/routes/*` and controller actions from `@/actions/*` — never hand-write URL strings.
- **UI kit**: bits-ui primitives under `resources/js/components/ui`, styled with Tailwind CSS v4. Icons from `@lucide/svelte`. Toasts via `svelte-sonner`.
- **Build**: Vite 8 with `@sveltejs/vite-plugin-svelte`. Svelte 5 is required. All new Svelte code uses runes — no `export let`, no `on:click`, no `$:` labels.

This is **not** a SvelteKit app. File-based `+page.svelte` / `+layout.svelte` routing does not exist here. Laravel owns URLs; Inertia persistent layouts (`AppLayout`, `AuthLayout`) are the tree that class stores hang off. The class / rune / context theory is the same; only the tree's root is different.

### Why this doc is strict

Inertia enforces a **physical server/client boundary**. The backend is PHP, the frontend is Svelte, and only serialized props cross the seam. That wall gives you a lot for free — server state can't be silently mutated on the client, and there's a single obvious place (the controller) where a page's data is assembled.

But the wall also creates its own failure modes, and this doc is a **mandate, not a preference** about them:

1. **The controller is the entry point** — the "thin controller" discipline is the whole game on the backend. Business logic that creeps into the controller is business logic nobody can reuse from a job, command, or second screen.
2. **Every prop is a serialized payload** that ships to the browser, is embedded in the HTML on first load, and re-ships on every partial reload. Over-fetching props is a performance tax *and* a data-leak risk — the client can read every prop you send. Prop hygiene is not optional.
3. **Svelte owns a whole second state model** on the far side of the wire. Confusing "server state that happens to be in a prop" with "client state that belongs in `$state`" is the main source of bugs — stale UIs, double sources of truth, and `$effect`s that fight the server.
4. **Svelte's one-component-per-file rule does not license fat scripts.** Runes work in `.svelte.ts` files. Complex client logic leaves the page and lives in a class. Shared client state is a context-scoped instance of that class, never a module-level singleton.

The class-store pattern is how the client side stays as thin as the controller. The theory, the three scopes, and the SSR leak are in [`patterns-svelte-class-stores.md`](./patterns-svelte-class-stores.md). The rules below are what you do; that file is why.

---

## Backend Rules

The backend layering is a clean Laravel app — services, Actions, Form Requests, policies, and the service-vs-action decision are all unchanged from any other Laravel frontend. What's specific to Inertia is only the **entry point**: a controller action that returns `Inertia::render(...)` instead of a Blade view or a JSON resource.

### Thin controllers (the entry point)

A controller action is your routing entry point. Treat it like a thin dispatcher: authorize, gather view data (delegated to services/computed sources), and hand off. An action should rarely exceed ~10 lines — a delegate plus a `render`/`redirect`.

**Conventions:**

- **Naming mirrors the resource, resourceful verbs.** `ProfileController@edit`, `ProfileController@update`, `ProjectController@index/show/create/store`.
- **No business logic in the controller.** No multi-step orchestration, no complex queries inline, no business rules. Those live in services and actions.
- **Reads delegate to a service; writes delegate to a service or action.** The controller assembles the prop bag and returns it.
- **Inject dependencies, don't resolve them.** Constructor-inject services used across several actions; method-inject the ones a single action needs. Never reach for `app(...)`/`resolve(...)` inside the controller body.
- **Return the minimum props the page needs** (see [Minimize what crosses the wire](#minimize-what-crosses-the-wire)).
- **After a write, redirect** (`to_route(...)`) — Inertia turns the redirect into a client visit. Flash feedback via `Inertia::flash(...)`.
- **Give reads a deterministic order.** Without an explicit `ORDER BY`, row order is undefined; default to `latest()` (created_at desc) in the service so paginated props are stable.

```php
<?php // app/Http/Controllers/ProjectController.php

namespace App\Http\Controllers;

use App\Services\Project\ProjectService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectService $projects): Response
    {
        return Inertia::render('projects/Index', [
            'projects' => $projects->listForUser($request->user()),
        ]);
    }
}
```

```php
<?php // app/Http/Controllers/ProjectController.php (write)

use App\Actions\Project\CreateProject;
use App\Http\Requests\StoreProjectRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

public function store(StoreProjectRequest $request, CreateProject $action): RedirectResponse
{
    $action->handle($request->validated(), $request->user());

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

    return to_route('projects.index');
}
```

Note the controller does **not** query ad hoc, authorize inline, or write to the database itself. It delegates reads to a service and the write to an action, and the `StoreProjectRequest` has already validated + authorized before the method body runs.

### Form Requests (validation + authorization live here)

This is the real thing in an Inertia app — there's no client-side form object equivalent that the backend trusts. Every non-trivial write gets a Form Request that owns **both** validation and authorization. Validation errors are returned to Inertia automatically and surface in Svelte as `errors` (see [Forms](#forms-inertia-form--useform)).

**Create it:** `php artisan make:request StoreProjectRequest`.

```php
<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('projects')],
            'status' => ['required', new Enum(ProjectStatus::class)],
        ];
    }
}
```

- `authorize()` is the permission gate; `rules()` is the shape gate. **You need both** — validation is not authorization. When authorization depends on the resolved route model rather than the incoming payload, `Gate::authorize('update', $post)` at the top of the controller action is the equivalent gate — pick one and use it consistently.
- `$request->validated()` returns only the validated bag — hand that straight to your service/action. **Never** pass `$request->all()` into a mass-assignment, and every model still defines `$fillable` as a second line of defense.
- Prefer **array rule notation** (`['required', 'string', Rule::unique('projects')]`) in new requests — it composes with `Rule::` objects — but match the notation the neighbouring requests already use.
- Cross-field or stateful checks go in the request's `after()` method (or `Rule::when(...)` for conditional rules), not in the controller.
- Reuse a `rules()` array across store/update by extracting to a shared method or a base request when the sets overlap — one source of truth.

For sharing rules between a Form Request and any non-Inertia surface (an API endpoint, a command), extract the rules array to one place so validation never forks.

### Domain Service Classes

Reusable business logic lives in service classes grouped by domain — plain PHP resolved from the container. They encapsulate queries, orchestration, and business rules, and are called from controllers, actions, jobs, or commands alike.

```
app/
  Services/
    Project/
      ProjectService.php
    Billing/
      BillingService.php
```

```php
// app/Services/Project/ProjectService.php
class ProjectService
{
    public function listForUser(User $user): Collection
    {
        return $user->projects()->with('tasks')->latest()->get();
    }

    public function create(array $data, User $user): Project
    {
        return $user->projects()->create($data);
    }
}
```

### Laravel Actions (for bespoke operations)

Use `lorisleiva/laravel-actions` when an operation is a self-contained task that should also be runnable as a job, listener, or command — or when it bundles its own authorization, validation, and execution. Don't use actions for trivial CRUD a service already handles.

```php
// app/Actions/Project/ArchiveCompletedProjects.php
class ArchiveCompletedProjects
{
    use AsAction;

    public function handle(User $user): int
    {
        return $user->projects()
            ->where('status', 'completed')
            ->where('completed_at', '<', now()->subMonths(3))
            ->update(['archived' => true]);
    }

    public function asCommand(Command $command): void
    {
        $count = $this->handle(User::find($command->argument('user')));
        $command->info("Archived {$count} projects.");
    }
}
```

A controller invokes it by type-hinting it on the action method (as in `store` above) or calling `ArchiveCompletedProjects::run($user)`.

### Decision: Service vs Action

| Situation | Use |
|---|---|
| Standard domain logic (queries, CRUD orchestration, business rules) | Service class |
| Bespoke task that should also run as a job, command, or listener | Laravel Action |
| Operation with its own auth + validation that runs from multiple entry points | Laravel Action |
| Logic reused across multiple services or contexts | Extract to a Laravel Action, call via `MyAction::run(...)` |
| Complex multi-step operation or pipeline | Laravel Action wrapping a `Pipeline` or `DB::transaction` |
| A service class is growing too large | Extract cohesive chunks into Actions the service orchestrates |
| Trivial one-liner | Keep it in the service or the controller method |

Multi-step writes wrap a `Pipeline` in `DB::transaction` so any failing stage rolls everything back:

```php
class OrderService
{
    public function checkout(Cart $cart, User $user): Order
    {
        return DB::transaction(function () use ($cart, $user) {
            $order = CreateOrderFromCart::run($cart, $user);
            ApplyDiscountCodes::run($order, $cart->discountCodes);
            ChargePaymentMethod::run($order, $user->defaultPaymentMethod());
            SendOrderConfirmation::run($order);
            return $order;
        });
    }
}
```

### When you still use a JSON API / traditional controller

Inertia serves your first-party screens. Keep plain JSON controllers (Eloquent API Resources) for surfaces Inertia doesn't render:

- A JSON/API surface (mobile client, third-party integration)
- Webhooks and OAuth callbacks
- File downloads / streamed responses
- Stateless endpoints polled by client code that isn't a page visit

Those follow the standard thin-controller + Form Request rules unchanged.

### Testing (every seam is a test seam)

- **Feature tests** cover the controller → Inertia response contract. Assert the page and its props with Inertia's testing helpers rather than scraping HTML:

```php
use Inertia\Testing\AssertableInertia as Assert;

$this->actingAs($user)
    ->get(route('projects.index'))
    ->assertInertia(fn (Assert $page) => $page
        ->component('projects/Index')
        ->has('projects', 3)
    );

$this->actingAs($user)
    ->post(route('projects.store'), ['name' => 'Launch', 'status' => 'active'])
    ->assertRedirect(route('projects.index'));
```

- **Unit tests** cover services, actions, and policies directly — the pure business logic, no HTTP round-trip.
- **Form Requests**: assert the rules that matter through a feature test hitting the route.
- Choose the smallest test that protects the contract — don't boot Laravel for a calculation, don't mock away the thing you're actually verifying.

Create with `php artisan make:test --pest {name}` (add `--unit` for unit tests); run with `php artisan test --compact --filter=...`.

---

## The Inertia Bridge (props, routing, reloads)

There is no prop system you design — Inertia *is* the contract. A controller returns a page name and a prop array; Inertia serializes the props, renders the matching Svelte page, and on subsequent visits swaps only what changed.

### Routing

Routes live in Laravel (`routes/web.php`, `routes/settings.php`). Register resourceful controllers; use `Route::inertia()` for pages with no controller logic:

```php
Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

// Page with no backend logic — no controller needed:
Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
```

Page names map to files under `resources/js/pages/`. `projects/Index` → `resources/js/pages/projects/Index.svelte`. Match the casing the neighbouring pages already use.

From Svelte, **navigate with typed helpers, never string URLs**:

```svelte
<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { index, show } from '@/routes/projects';
</script>

<Link href={index()}>All projects</Link>
<Link href={show(project.id)}>{project.name}</Link>
```

`<Link>` performs an Inertia visit (XHR + partial swap), not a full page load. Wayfinder helpers keep the URL, method, and params type-checked against the Laravel route.

### Shared data (auth user, flash, etc.)

Cross-cutting props every page needs come from `HandleInertiaRequests::share()`, not from each controller:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => ['user' => $request->user()],
        'sidebarOpen' => ! $request->hasCookie('sidebar_state')
            || $request->cookie('sidebar_state') === 'true',
    ];
}
```

Read shared props anywhere with the Inertia `page` store. In Svelte 5, wrap the read in `$derived` so it stays reactive across visits:

```svelte
<script lang="ts">
    import { page } from '@inertiajs/svelte';

    const user = $derived(page.props.auth.user);
</script>
```

Keep `share()` lean — it ships on **every** request. Don't put per-page data here.

### Minimize what crosses the wire

Every prop is serialized into the initial HTML and re-sent on partial reloads, so **size matters and every field is visible to the client**.

- Shape props with **Eloquent API Resources** or explicit arrays — never return a raw model with 30 columns when the card shows 3. Select only the columns the page renders (`->select(...)`, and `:id,name` on eager loads — always include the foreign key).
- **Eager-load every relationship a prop touches** (`->with('author')`). Enable `Model::preventLazyLoading(! app()->isProduction())` in `AppServiceProvider::boot()` so it throws in dev instead of shipping.
- Use `withCount('comments')` instead of loading a whole relation just to send its size.
- Do array transforms (`sort`, `filter`, `map`) on the client, not by shipping a second derived copy of the same data.
- Use **partial reloads** (`only` / `except`) so a re-visit re-fetches just the props that changed:

```svelte
<script lang="ts">
    import { router } from '@inertiajs/svelte';

    function refreshProjects() {
        router.reload({ only: ['projects'] });
    }
</script>
```

- For genuinely slow props, use **deferred props** (`Inertia::defer()`) so the page paints immediately and the heavy data streams in behind a skeleton.

```php
return Inertia::render('projects/Show', [
    'project' => $project,
    'activity' => Inertia::defer(fn () => $activityService->for($project)),
]);
```

```svelte
<script lang="ts">
    import { Deferred } from '@inertiajs/svelte';
</script>

<Deferred data="activity">
    {#snippet fallback()}
        <ActivitySkeleton />
    {/snippet}
    <ActivityFeed />
</Deferred>
```

- Assemble independent props in the controller without introducing a **server-side waterfall**. For genuinely independent, expensive reads, run them in parallel with `Concurrency::run([...])`.

> **Two different `defer`s — don't confuse them.** `Inertia::defer(fn () => ...)` is a *deferred prop* that streams to the client after first paint. Laravel's `defer(fn () => ...)` helper runs fire-and-forget work after the HTTP response is sent — reach for it instead of a queued job when the work is trivial and needn't survive a crash.

### Inertia v3 conveniences (reach for these before hand-rolling)

The protocol already solves most "make it feel fast" problems — don't rebuild them with `$effect` + `fetch`:

- **Prefetch on intent**: `<Link href={show(id)} prefetch>` warms the next page on hover/focus.
- **Instant visits**: render the target immediately with shared props while page props load behind it.
- **Optimistic updates**: `router.optimistic(...)` (also a prop on `<Form>` / `useForm`) applies the change locally and rolls back automatically if the request fails.
- **Below-the-fold data**: `<WhenVisible data="stats">` lazy-loads a prop when it scrolls into view; `<InfiniteScroll data="users">` paginates on scroll (server uses `Inertia::scroll()`).
- **Live data**: `usePoll(5000, { only: ['stats'] })` refreshes a prop on an interval and handles cleanup + inactive-tab throttling for you.
- **Layout data that changes per page**: export a `layout` object from a `<script module>` on the page (breadcrumbs, title) — see [Page components](#page-components-thin-like-the-controller).

---

## Frontend / Svelte Rules

This is the core of the boundary: **Inertia props are server-owned truth; Svelte `$state` is client-owned.** Choosing the wrong side is the main source of stale UIs (treating a prop as mutable) or untrustworthy state (keeping something client-side that the backend must validate).

Svelte 5 is required. All new code uses runes:

| Don't | Do |
|---|---|
| `export let project` | `let { project } = $props()` |
| `on:click={...}` | `onclick={...}` |
| `$: doubled = count * 2` | `const doubled = $derived(count * 2)` |
| `<slot name="foo" />` | `{#snippet foo()}...{/snippet}` + `{@render foo()}` |
| `writable` / `readable` for new work | `$state` on a class (see below) |

### The state decision: Inertia prop vs Svelte `$state`

Use an **Inertia prop** (server round-trip via a visit) when the state:
- must persist or hit the database
- involves auth, validation, or business rules
- must be trusted by the backend
- is the source of truth for what's saved

You never mutate a prop in place. You change server state by **visiting a route** (a form submit, a `router` visit, a `<Link>`), and the fresh prop comes back down.

Use **Svelte `$state`** (zero round-trip) when the state is:
- ephemeral and presentational — dropdown open/closed, active tab, modal visibility, hover, "show password"
- a mid-interaction transient — drag position, in-progress reorder, unsaved input keystrokes
- something a server request per change would make feel laggy

Use **both, bridged**, when an interaction needs instant local feedback *and* must eventually reach the server: drive the input with local `$state` (or `useForm`) and submit to a route on save. For instant-feeling writes, Inertia v3 **optimistic updates** apply the change locally and roll back automatically if the request fails.

**Rule of thumb:** if losing the value on refresh is fine and the backend doesn't care about it, it's `$state`. If the value is meaningful to the server, it's an Inertia prop changed via a visit.

### Where `$state` lives — three scopes, one primitive

`$state`, `$derived`, `$effect`, and `$inspect` work in `.svelte` *and* in `.svelte.ts`. That is the whole trick. A class field initialized with `$state` is the same reactive variable you'd write in a page script. The class is just a box.

Pick the smallest scope that fits. Details and the mental model are in the [pattern doc](./patterns-svelte-class-stores.md).

| Scope | When | How |
|---|---|---|
| **Inline rune** | One or two fields, used only in this file | `let open = $state(false)` in the page/component script |
| **Local class instance** | The script is getting fat — D3, multi-step UI, fake/async client work, a page that shouldn't share | `FooState.svelte.ts` + `const foo = new FooState()` in that page |
| **Context store** | Two or more places in the tree must see the same instance (toasts, sidebar, command palette, a board shared by a header and a canvas) | `setFooState()` in a **persistent Inertia layout**; `getFooState()` in descendants |

A fourth option — `export const foo = new FooState()` at module scope — is **forbidden**. It leaks across SSR requests. Context is the only legal way to share an instance. See [the SSR leak](./patterns-svelte-class-stores.md#the-ssr-leak--never-export-a-module-level-instance).

Existing starter files (`theme.svelte.ts`, `twoFactorAuth.svelte.ts`, `currentUrl.svelte.ts`) are factory functions around module-level `$state`. Leave them. **New shared client state is a class + context.** Do not add another module-level `$state` singleton.

### Page components (thin, like the controller)

Page components live in `resources/js/pages/` and map 1:1 to `Inertia::render('...')` names. Keep them thin — they receive typed props, compose UI components, and wire up forms/visits. Push complex client logic into a `.svelte.ts` class, exactly as the controller pushes logic into services.

```svelte
<!-- resources/js/pages/projects/Index.svelte -->
<script module lang="ts">
    import { index } from '@/routes/projects';

    export const layout = {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import ProjectCard from '@/components/ProjectCard.svelte';
    import { create } from '@/routes/projects';
    import type { Project } from '@/types';

    let { projects }: { projects: Project[] } = $props();
</script>

<AppHead title="Projects" />

<div class="grid gap-4 md:grid-cols-3">
    {#each projects as project (project.id)}
        <ProjectCard {project} />
    {/each}
</div>

<Link href={create()}>New project</Link>
```

**Layouts** are assigned by the resolver in `resources/js/app.ts` (page-name prefix → `AppLayout` / `AuthLayout` / `[AppLayout, SettingsLayout]`). A page declares breadcrumbs and other layout props via a `<script module>` `layout` export — not by importing the layout into the page.

The persistent layout **is** the Svelte tree's parent. Pages swap underneath it on Inertia visits. That is why shared class stores are set in the layout, not in a page — a page-scoped `new FooState()` dies when you navigate away.

### Local class instance (the script got fat)

When a page has complex client-side state that never needs the server (a chat transcript, a wizard, drag-and-drop, a D3 chart, a canvas), extract it into a class in a colocated `.svelte.ts` file. Define the public shape first, then implement it.

```ts
// resources/js/pages/projects/BoardState.svelte.ts
interface BoardState {
    selectedId: string | null;
    query: string;
    select: (id: string) => void;
    clear: () => void;
}

export class BoardState implements BoardState {
    selectedId = $state<string | null>(null);
    query = $state('');

    readonly filteredCount = $derived.by(() => {
        // derived from other fields — not stored, not synced by $effect
        return this.query.length;
    });

    select = (id: string) => {
        this.selectedId = id;
    };

    clear = () => {
        this.selectedId = null;
        this.query = '';
    };
}
```

```svelte
<script lang="ts">
    import { BoardState } from './BoardState.svelte';

    const board = new BoardState();
</script>

<input bind:value={board.query} />
<button onclick={() => board.select(project.id)}>Select</button>
<p>{board.selectedId}</p>
```

Access fields on the instance. **Don't destructure reactive fields** (`const { query } = board`) — that snapshots the value and drops reactivity. Methods are fine to pass as `onclick={board.select}` because they're arrow functions on the class.

This instance is **page-local**. The board on `/projects` is not the board on `/projects/1`. If a sibling page or the layout needs the same object, that's a context store, not a second `new`.

### Shared client state — context (the store)

When multiple components need the same *client-only* state, create the instance in a persistent Inertia layout with `setContext`, and read it in descendants with `getContext`. Key the context. Default key is fine for one store of that type; pass a different key when two independent instances must coexist in the same tree.

```ts
// resources/js/lib/stores/toastState.svelte.ts
import { getContext, setContext } from 'svelte';

type Toast = { id: string; msg: string; type: 'success' | 'error' };

interface ToastState {
    messages: Toast[];
    open: (msg: string, type?: Toast['type']) => void;
    dismiss: (id: string) => void;
}

class ToastStateClass implements ToastState {
    messages = $state<Toast[]>([]);

    open = (msg: string, type: Toast['type'] = 'success') => {
        this.messages.push({ id: crypto.randomUUID(), msg, type });
    };

    dismiss = (id: string) => {
        this.messages = this.messages.filter((t) => t.id !== id);
    };
}

const DEFAULT_KEY = '$_toast_state';

export const setToastState = (key = DEFAULT_KEY) => {
    return setContext(key, new ToastStateClass());
};

export const getToastState = (key = DEFAULT_KEY) => {
    return getContext<ToastState>(key);
};
```

```svelte
<!-- resources/js/layouts/AppLayout.svelte — the parent of every authenticated page -->
<script lang="ts">
    import { setToastState } from '@/lib/stores/toastState.svelte';

    setToastState();
</script>
```

```svelte
<!-- any descendant: a page, a card, a modal -->
<script lang="ts">
    import { getToastState } from '@/lib/stores/toastState.svelte';

    const toast = getToastState();
</script>

<button onclick={() => toast.open('Saved')}>Save</button>
```

Mount the toast *UI* in the same layout that sets the store, so `open()` has somewhere to render. Server-originated flash still flows through `initializeFlashToast()` → sonner; reuse that path for server flash. The toast class is for **client-originated** "I just did a thing locally" toasts.

Reserve context for genuinely client-originated shared state. **Server-originated shared state (auth user, flash) comes from Inertia shared props via `page.props`, not a class store.**

bits-ui primitives already use `setContext` / `getContext` internally (`components/ui/sidebar/context.ts`, dropdown, sheet). Don't fight those. Don't wrap them in a second parallel store.

### Forms (Inertia `<Form>` / `useForm`)

Forms submit to a Laravel route; validation errors come back from the Form Request and surface as `errors`. Prefer the `<Form>` component wired to a Wayfinder helper — it manages `processing`, `errors`, and reset for you. In Svelte 5 the default slot is a snippet:

```svelte
<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import InputError from '@/components/InputError.svelte';
</script>

<Form {...ProjectController.store.form()} options={{ preserveScroll: true }}>
    {#snippet children({ processing, errors })}
        <Input name="name" value="" required />
        <InputError message={errors.name} />
        <Button disabled={processing}>Create</Button>
    {/snippet}
</Form>
```

For programmatic control (multi-step, conditional fields, transforms before submit), use `useForm`:

```svelte
<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { store } from '@/routes/projects';

    const form = useForm({ name: '', status: 'active' });

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post(store().url, { preserveScroll: true });
    }
</script>

<form onsubmit={submit}>
    <input bind:value={form.name} />
    {#if form.errors.name}<p>{form.errors.name}</p>{/if}
    <button disabled={form.processing}>Create</button>
</form>
```

- **The server is the validator.** Client-side `required`/`type` attributes are UX niceties; the Form Request is the gate.
- Bind the action with Wayfinder (`ProjectController.store.form()` or `store.form()`) so the method + URL stay typed.
- `errors` keys match the Form Request field names — render them with the shared `InputError` component.
- The `<Form>` snippet exposes more than `processing`/`errors`: `wasSuccessful`, `recentlySuccessful`, `isDirty`, `progress`, `defaults`, `reset`, `clearErrors`. Use the declarative reset props — `resetOnSuccess`, `resetOnError`, `setDefaultsOnSuccess` — instead of resetting by hand.
- For a request that must **not** trigger a page visit (typeahead, a background lookup hitting a JSON endpoint), use `useHttp` — same ergonomics as `useForm`, but it returns JSON instead of swapping props.

A class store can *call* `useHttp` / `router` from a method when the client work is complex enough to extract. It must not become a second place that owns server truth. After a write that matters, you still visit or reload so the prop comes back down.

### Component structure

- **One concept per component.** Compose pages from small components under `resources/js/components/` and primitives under `resources/js/components/ui/`.
- **Reuse the UI kit before writing new markup.** Buttons, inputs, dialogs, selects, etc. already exist. Extend them; don't copy-paste.
- **Offload complex logic, don't spawn components to hide it.** Server logic → controllers/services/actions. Client logic → a `.svelte.ts` class. Creating another `.svelte` file purely to split a script is the one-component-per-file tax the class pattern exists to avoid.
- **Svelte 5 runes everywhere in new code.** `$props()`, `onclick`, `$derived`, snippets.

### Side effects — `$effect` and Inertia router events

Setup/teardown for client systems (WebSockets, timers, DOM/canvas APIs, global listeners) belongs in `$effect` with a cleanup return, or in `onMount` when the work must not run on the server.

```ts
$effect(() => {
    const stop = router.on('flash', (event) => {
        const data = (event as CustomEvent).detail?.flash?.toast;
        if (data) toast[data.type](data.message);
    });

    return () => stop();
});
```

The existing `initializeFlashToast()` in `app.ts` is the model for app-wide subscriptions that must run once at boot.

- **Don't use `$effect` to sync derived state.** If you can write `$derived`, write `$derived`. Storing a derivable value in `$state` and keeping it in sync with `$effect` is the #1 source of state drift.
- **Use `$effect` sparingly** — the same way you'd use `useEffect` sparingly. If a method can do the work at the call site (`sendMessage`, `select`), do it there.
- Guard `window` / `document` — SSR runs this code. `typeof window === 'undefined'` returns early.

### Bundle size (Vite)

- **Import icons and components directly, not from barrel files that re-export a kit.** Named icons from `@lucide/svelte/icons/...` as the existing pages already do.
- **Import Wayfinder helpers by name**, not as a namespace (`import { show, store } from '@/actions/...'`) — named imports tree-shake; a namespace import drags every route into the bundle.
- **Don't import a page-local class store from a distant page.** If two pages need the same *instance*, that's context, set in a layout. If they need the same *shape*, they can both `new` the class.

---

## Security Mandates

Inertia's one-way prop flow removes some footguns (you can't mutate server state by editing a prop), but adds one big one: **every prop is visible to the client.** Discipline:

- **Never send a prop the user shouldn't see.** Props are embedded in the page HTML and readable in the browser. Shape reads with API Resources; omit internal columns, tokens, other users' data, and admin-only fields.
- **Validate and authorize in the Form Request / policy, every time.** `authorize()` gates permission; `rules()` gates shape. Middleware only proves "is logged in." Validation is not authorization — you need both.
- **Never mass-assign request input.** Pass `$request->validated()` (or an explicit array) to the model, never `$request->all()` — and every model defines `$fillable` (never `$guarded = []` on anything that takes user input).
- **Treat all submitted form values as untrusted.** Client-side HTML validation is UX only; the server rules run regardless.
- **Authorize page reads too**, not just writes — a `show`/`edit` controller must check the policy before rendering, or you'll ship another user's record as a prop.
- **Rate-limit auth and sensitive routes** with `throttle` middleware.
- **Encrypt secrets at rest**: tokens/API keys use the `encrypted` cast and sit in the model's `$hidden` array so they never serialize into a prop.
- CSRF is handled by Inertia's HTTP client automatically — it sends the `XSRF-TOKEN` cookie back as the `X-XSRF-TOKEN` header. Don't add `@csrf` to Inertia forms, and don't disable the protection.
- **A class store is not a trust boundary.** Anything it holds can be edited in the browser. Never put a price, an ownership id, or a permission flag in `$state` and treat it as authoritative on the next request.

---

## Full-Stack Decision Tree

| What you're deciding | Answer |
|---|---|
| Where does routing live? | Laravel (`routes/*.php`). Resourceful controllers; `Route::inertia()` for logic-free pages. Not SvelteKit file routes. |
| How do I link/navigate in Svelte? | Wayfinder helpers (`@/routes/*`, `@/actions/*`) inside `<Link>` / `router` — never string URLs. |
| Where does auth, validation, data fetching live? | Laravel — controllers delegate to policies, Form Requests, services. |
| Where does business logic live? | Domain service classes; Laravel Actions for bespoke cross-cutting tasks. |
| What is the page entry point? | A thin controller returning `Inertia::render('Page', $props)`. |
| Where does form validation + authorization live? | A Form Request (`rules()` + `authorize()`). |
| How do props reach Svelte? | Serialized by Inertia; per-page from the controller, cross-cutting from `HandleInertiaRequests::share()`. Read with `page.props` inside `$derived`. |
| Is this state a prop or `$state`? | Server-meaningful / persisted / validated → Inertia prop (changed by visiting a route). Ephemeral / presentational / instant → `$state`. Bridge via a form submit or optimistic update when it's both. |
| How do I change server state? | Submit a form or `router` visit to a route; the fresh prop returns. Never mutate a prop in place. |
| Inline `$state`, local class, or context? | One or two local fields → inline. Fat script, still page-local → `new` a class in `.svelte.ts`. Shared across the tree → `set*` in a persistent layout, `get*` in descendants. |
| Can I `export const store = new FooState()`? | No. SSR leak. Context only. |
| When do I extract a `.svelte.ts` class? | When client-only logic is complex enough to clutter the `<script>`. |
| Context vs Inertia shared props? | Context = client-originated shared state. Shared props (`page.props`) = server-originated shared state (auth, flash). |
| A prop is slow to compute — what do I do? | `Inertia::defer()` + a `<Deferred>`/`<WhenVisible>` skeleton; partial reloads (`only`) for re-fetches; `Concurrency::run()` for parallel independent reads. |
| `Inertia::defer()` vs `defer()`? | `Inertia::defer()` = a prop that streams after paint. Laravel `defer()` = fire-and-forget work after the response. |
| A prop bag loops over relations — how do I avoid N+1? | Eager-load with `with()` / `withCount()`, select only needed columns, and turn on `preventLazyLoading` in dev. |
| I need a request that doesn't swap the page | `useHttp` (JSON endpoint), not `useForm`/`<Form>`. |
| Make navigation feel instant | `<Link prefetch>`, instant visits, or optimistic updates — not a hand-rolled loading flag in a class store. |
| When do I reach for a plain JSON controller? | Non-Inertia surfaces only: API, webhooks, downloads. |
| How do I test a page? | Feature test + `assertInertia` on component/props; unit-test services/actions/policies directly. Pest. |

---

## File Structure Reference

```
app/
  Http/
    Controllers/               # Thin entry points returning Inertia::render(...)
      ProjectController.php
    Requests/                  # Form Requests (validation + authorization)
      StoreProjectRequest.php
    Middleware/
      HandleInertiaRequests.php # Shared props (auth, flash, sidebar)
  Actions/                     # lorisleiva/laravel-actions (bespoke tasks)
    Project/
      ArchiveCompletedProjects.php
  Services/                    # Domain service classes (core business logic)
    Project/
      ProjectService.php
  Policies/                    # Authorization
    ProjectPolicy.php

resources/
  js/
    app.ts                     # createInertiaApp: layout resolver, boot theme/flash
    pages/                     # One file per Inertia::render('...') page
      projects/
        Index.svelte
        Show.svelte
        Create.svelte
        BoardState.svelte.ts   # Page-local class store (colocated)
      settings/
        Profile.svelte
    layouts/                   # Persistent Inertia layouts — setContext lives here
      AppLayout.svelte
      AuthLayout.svelte
      app/
        AppSidebarLayout.svelte
    components/                # Reusable components
      ProjectCard.svelte
      InputError.svelte
      ui/                      # bits-ui primitives
        button/
        input/
        dialog/
    lib/
      stores/                  # Context-shared class stores (get/set + class)
        toastState.svelte.ts
        commandPaletteState.svelte.ts
      theme.svelte.ts          # Starter leftover — don't copy this shape for new stores
      flash-toast.ts           # Server flash → sonner
    routes/                    # Wayfinder named-route helpers (generated)
    actions/                   # Wayfinder controller-action helpers (generated)
    types/                     # Shared TS types (Auth, models, props)

routes/
  web.php
  settings.php
```

---

## Common Pitfalls

- Using a plain `<a>` (or `<form>`) instead of `<Link>` / `<Form>` — it triggers a full reload and drops out of the SPA.
- Rendering a deferred / `WhenVisible` prop without handling its `undefined` state and skeleton first.
- Mutating a prop object in Svelte and expecting it to persist — props are server-owned; change them via a visit.
- Building a prop bag over lazy relations (N+1) — eager-load first.
- Sending a whole model as a prop when the page shows three fields — shape it, and remember the client can read all of it.
- v3 renames: use `router.cancelAll()` (not `router.cancel()`), and listen for `httpException` / `networkError` (not `invalid` / `exception`).
- Writing Svelte 4 syntax (`export let`, `on:click`, `$:`) in new files.
- Leaving a fat `<script>` in a page instead of extracting a class — the one-component-per-file rule is not a reason to dump D3 or a wizard into the page.
- `export const store = new FooState()` at module scope — SSR leak. Use `setContext` in a persistent layout.
- Instantiating a shared store in a *page* instead of a layout — the next Inertia visit destroys it; siblings never saw it.
- Destructuring `$state` fields off a class (`const { count } = counter`) — you snapshot the value and lose reactivity.
- Using Svelte 4 `writable`/`readable` for new client state. `$state` on a class is the house pattern. bits-ui internals may still use the old stores; leave those alone.
- Putting auth/flash into a class store. Those are Inertia shared props.
- Reaching for `$effect` to keep a stored value in sync with something you could `$derived`.

---

## Key Mental Model

1. **Laravel is the brain** — routing, auth, validation, business logic, data. Svelte never becomes a second backend.
2. **Controllers are thin dispatchers** — they authorize, gather props (delegating to services), and `render` or `redirect`. Logic does not accumulate here even though nothing stops it.
3. **Services hold domain logic, Actions hold bespoke operations** — identical to any clean Laravel app; kept behind seams you can test and reuse.
4. **Form Requests own validation + authorization** — the two gates every write must pass; validation is not authorization.
5. **Inertia props are server-owned truth, Svelte `$state` is client-owned** — you change server state by visiting a route and receiving a fresh prop, never by mutating a prop. Ephemeral UI state lives in runes and never crosses the wire as authority.
6. **The class is the box; the rune is the reactivity.** `$state` on a class field is the same primitive as `$state` in a page script. Extract when the script gets fat. Share with context, never with a module-level `new`.
7. **Svelte is a tree.** Persistent Inertia layouts are parents; pages and components are children. `set*` at the parent, `get*` at the descendants. That is the store.
8. **The wire is a cost and a window** — every prop is serialized to the browser and visible there. Send the minimum, shape it with Resources, defer the slow parts, and never leak what the user shouldn't see.
9. **The boundary works for you — so lean into it** — let Wayfinder type your routes, let Form Requests guard the seam, let runes own the client, and keep each side doing only its job.

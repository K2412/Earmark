# Full-Stack Architecture Prompt — Laravel + Livewire 4 + Alpine.js

Use this as a system/context prompt when asking an LLM to build features in this stack.

---

## Stack Overview

- **Backend**: Laravel with thin Livewire components, domain service classes, and Laravel Actions (`lorisleiva/laravel-actions`) for bespoke operations
- **Full-stack bridge**: Livewire 4 — no REST API, no separate frontend build for most work, no client-side routing. Laravel owns routing; Livewire components render Blade server-side and handle interactivity over the wire
- **Client-side interactivity**: Alpine.js for ephemeral, presentational state that should never touch the server (dropdowns, modals, drag interactions, instant toggles, canvas). Livewire and Alpine are built to interlock via `wire:model` and direct `$wire` property access

### Why this doc is strict

Inertia enforces a physical server/client boundary: backend is PHP, frontend is a separate framework, and only serialized props cross the seam. Livewire has **no such wall** — a single component class holds DOM-bound state, event handlers, and whatever logic you reach for. That convenience is exactly how components rot into a birds nest. Every "keep it thin / push it down" rule below is therefore a **mandate, not a preference**. The structure does not come for free; you impose it.

---

## Backend Rules

The backend layering here is identical to a clean Laravel app on any frontend. Services, Actions, Form Requests, and the service-vs-action decision are unchanged. What changes is that the **full-page Livewire component replaces the controller** as the entry point, so the "thin controller" discipline moves onto the component.

### Thin Livewire Components (the controller analogue)

A full-page Livewire component is your routing entry point. Treat it like a thin controller: it holds view state, binds inputs, and **delegates** every non-trivial operation to a service, action, or form object. An action method on the component should rarely exceed ~10 lines — ideally a delegate + a redirect/feedback line.

**Component conventions:**

- **Naming mirrors the resource, singular per concept.** `pages::project.index`, `pages::project.show`, `pages::project.create`.
- **No business logic in the component.** No multi-step orchestration, no complex queries inline, no business rules. Those live in services and actions.
- **Public properties are the component's typed state surface** — keep them minimal. Anything the client must not be able to tamper with gets `#[Locked]`.
- **Action methods = delegate + feedback.** Validate (via a form object), hand off to a service/action, then `redirect()` or flash.
- **Derived data goes in `#[Computed]` properties**, never recomputed inline in Blade or duplicated across methods.

```php
<?php // resources/views/pages/project/⚡index.blade.php

use App\Services\Project\ProjectService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function projects()
    {
        return app(ProjectService::class)->listForUser(auth()->user());
    }
};
?>

<div>
    @foreach ($this->projects as $project)
        <x-project-card :$project wire:key="{{ $project->id }}" />
    @endforeach
</div>
```

```php
<?php // resources/views/pages/project/⚡create.blade.php

use App\Livewire\Forms\ProjectForm;
use App\Actions\Project\CreateProject;
use Livewire\Component;

new class extends Component {
    public ProjectForm $form;

    public function save(CreateProject $action)
    {
        $this->form->validate();

        $action->handle($this->form->pull(), auth()->user());

        return $this->redirectRoute('projects.index');
    }
};
?>

<form wire:submit="save">
    <x-input-text wire:model="form.name" />
    @error('form.name') <span class="error">{{ $message }}</span> @enderror
    <button type="submit">Create</button>
</form>
```

Note the component does **not** query, authorize ad hoc, or write to the database itself. It delegates to a `#[Computed]` + service for reads and an action for the write.

### Form Objects (validation lives here, not in the component)

In Inertia, every endpoint had a `FormRequest` owning validation + authorization. The Livewire equivalent is the **Form object** (`Livewire\Form`). Every component with non-trivial input gets one. It keeps the component class clean and makes form logic reusable across create/edit.

**Create it:** `php artisan livewire:form ProjectForm` → generated at `app/Livewire/Forms/ProjectForm.php`.

```php
<?php

namespace App\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;
use App\Models\Project;

class ProjectForm extends Form
{
    public ?Project $project = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate]
    public string $status = '';

    // Use a rules() method when rules depend on state (e.g. unique-ignoring-self).
    protected function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255', Rule::unique('projects')->ignore($this->project)],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
        ];
    }

    public function setProject(Project $project): void
    {
        $this->project = $project;
        $this->name    = $project->name;
        $this->status  = $project->status->value;
    }
}
```

- `#[Validate('...')]` on a property → rule runs whenever that property updates server-side (good for real-time validation with `wire:model.live.blur`).
- `#[Validate]` with no argument + a `rules()` method → rules only run on `$this->validate()`, but the field still re-validates live after each request. Use this when rules need `$this` state.
- `pull()` returns the validated property bag and resets the form in one call — hand its output straight to your action.

**Authorization** (the half a `FormRequest::authorize()` used to own) moves to **policies**, invoked explicitly in the component's `mount()` or action method:

```php
public function mount(Project $project): void
{
    $this->authorize('update', $project);
    $this->form->setProject($project);
}
```

### When you still use controllers + Form Requests

Livewire replaces controllers only for Livewire-rendered pages. Keep traditional `Controller` + `FormRequest` for surfaces Livewire doesn't serve:

- A JSON/API surface (mobile client, third-party integration)
- Webhooks and OAuth callbacks
- File downloads / streamed responses outside a component
- Stateless redirects

Those follow the standard thin-controller + form-request rules unchanged. **Don't duplicate validation** — if a rule set is shared between a Form object and a FormRequest, extract the rules array (or the underlying service-level validation) so there's one source of truth.

### Domain Service Classes

Unchanged from the Inertia version. Reusable business logic lives in service classes grouped by domain — plain PHP resolved from the container. They encapsulate queries, orchestration, and business rules, and are called from components, actions, controllers, or jobs alike.

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

Unchanged. Use `lorisleiva/laravel-actions` when an operation is a self-contained task that benefits from running as a Livewire action, controller, job, listener, or command — or when it bundles its own authorization, validation, and execution. Don't use actions for trivial CRUD a service already handles.

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

A Livewire component invokes it by type-hinting it on the action method (as in `CreateProject` above) or calling `ArchiveCompletedProjects::run($user)`.

### Decision: Service vs Action

| Situation | Use |
|---|---|
| Standard domain logic (queries, CRUD orchestration, business rules) | Service class |
| Bespoke task that should also run as a job, command, or listener | Laravel Action |
| Operation with its own auth + validation that runs from multiple entry points | Laravel Action |
| Logic reused across multiple services or contexts | Extract to a Laravel Action, call via `MyAction::run(...)` |
| Complex multi-step operation or pipeline | Laravel Action wrapping a `Pipeline` or `DB::transaction` |
| A service class is growing too large | Extract cohesive chunks into Actions the service orchestrates |
| Trivial one-liner | Keep it in the service or the component action method |

**Actions as decomposition** and **complex pipelines inside Actions** work exactly as in the Inertia doc — a thin service/component orchestrates Actions; multi-step writes wrap a `Pipeline` in `DB::transaction` so any failing stage rolls everything back.

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

---

## Routing & Page Components (replaces the Inertia bridge)

There is no prop-serialization bridge. A full-page Livewire component renders Blade directly and updates itself over the wire.

- Routes live in Laravel (`routes/web.php`). Register full-page components with `Route::livewire()`:

```php
Route::livewire('/projects', 'pages::project.index')->name('projects.index');
Route::livewire('/projects/{project}', 'pages::project.show')->name('projects.show');
```

- Prefer grouping standard CRUD; use single routes only for one-off actions that don't fit CRUD (`archive`, `reorder`, `download`).
- Page components live under `resources/views/pages/` (the `pages::` namespace); nested/reusable components under `resources/views/components/`. Single-file is the default (PHP + Blade co-located via the `⚡` convention); extract to a multi-file component (class in `app/Livewire/`) once the class outgrows the view.
- Navigate between pages with `wire:navigate` on links for SPA-style transitions without a full reload.
- Shared server data (auth user, flash) flows through Blade layouts and `session()`, not through a prop system.

---

## Frontend / Interactivity Rules

This is the core of your question: **Livewire owns server state; Alpine owns client-only state.** Choosing the wrong one is the main source of either laggy UIs (over-using Livewire) or untrustworthy state (over-using Alpine).

### The reactivity decision: Livewire vs Alpine

Use **Livewire** (server round-trip) when the state:
- must persist or hit the database
- involves auth, validation, or business rules
- must be trusted by the backend
- is the source of truth for what gets saved

Use **Alpine** (zero round-trip, instant) when the state is:
- ephemeral and presentational — dropdown open/closed, active tab, modal visibility, hover, "show password"
- a mid-interaction transient — drag position, in-progress reorder, canvas pointer state
- something a network request per change would make feel laggy or wasteful

Use **both, bridged**, when an interaction needs instant feedback *and* must eventually reach the server. Drive the UI with Alpine and sync the meaningful value into Livewire:

```blade
{{-- wire:model bridges an input straight to a Livewire property --}}
<input type="text" wire:model.live.blur="form.name">

{{-- Read or mutate a Livewire property directly from Alpine via $wire --}}
<div x-data>
    <small>Characters: <span x-text="$wire.form.name.length"></span></small>
    <button x-on:click="$wire.form.name = ''">Clear</button>
</div>

{{-- Purely client-side state stays in Alpine — it never becomes a Livewire property --}}
<div x-data="{ open: false }">
    <button x-on:click="open = !open">Toggle</button>
    <div x-show="open" x-transition>…</div>
</div>
```

Prefer direct `$wire` access over `$wire.entangle()` — the docs discourage entangling because it duplicates state across the Alpine/Livewire boundary. And note: a value like `open` above is client-only, so it shouldn't be a Livewire property at all. Only reach across the boundary (`$wire`/`wire:model`) when the value is genuinely meaningful to the server.

**Rule of thumb:** if losing the value on refresh is fine and the backend doesn't care about it, it's Alpine. If the value is meaningful to the server, it's Livewire.

### Component structure

- One concept per component. Single-file by default; split to multi-file when the PHP grows.
- **Extract repeated UI into Blade components** (`<x-input-text>`, `<x-modal>`) rather than copy-pasting field/validation markup. Forward attributes with `{{ $attributes }}` and declare inputs with `@props`.
- **Offload complex logic, don't spawn components to hide it.** Server logic → services/actions/form objects. Client logic → an `Alpine.data()` definition (below). Creating a Livewire component purely to split code adds a network boundary you probably don't want.
- Use **islands** (`@island`) to isolate expensive or independently-updating parts of a page so they don't re-render the whole component.

### Client-only state — `Alpine.data()` classes (the `.svelte.ts` analogue)

When a page has complex client-side state that never needs the server (multi-step wizard UI, drag-and-drop, canvas, rich editors), extract it into a named `Alpine.data()` definition in a dedicated file instead of inlining a large `x-data` blob. This is the direct counterpart to extracting a Svelte state class.

Define the public shape first (fields + methods), then register it:

```js
// resources/js/alpine/chat.js
import Alpine from 'alpinejs'

Alpine.data('chat', () => ({
    messages: [],
    isLoading: false,

    // getters are the $derived analogue — computed reactively from state
    get messageCount() {
        return this.messages.length
    },

    sendMessage(text) {
        this.isLoading = true
        this.messages.push({ role: 'user', text })
        setTimeout(() => {
            this.messages.push({ role: 'assistant', text: 'Got it!' })
            this.isLoading = false
        }, 400)
    },
}))
```

```blade
<div x-data="chat">
    <p x-text="`${messageCount} messages`"></p>
    <button x-on:click="sendMessage('Hello')">Send</button>
</div>
```

**Gotcha — keep access scoped to the component proxy.** Reactivity is bound to Alpine's reactive proxy (`this` inside the component). Don't lift a reactive field out into a plain local variable or a destructured binding — you snapshot the value and lose reactivity, the same trap as destructuring a Svelte state class. Access through `this.messages` / the `x-data` scope.

### Shared client state — `Alpine.store()` (the context analogue)

When multiple components need the same client-only state (toasts, theme, sidebar collapse, command palette), use a global Alpine store rather than duplicating `x-data` or reaching across components.

```js
// resources/js/alpine/stores/toast.js
import Alpine from 'alpinejs'

Alpine.store('toast', {
    messages: [],
    open(msg, type = 'success') {
        this.messages.push({ id: crypto.randomUUID(), msg, type })
    },
    dismiss(id) {
        this.messages = this.messages.filter(m => m.id !== id)
    },
})
```

```blade
<button x-on:click="$store.toast.open('Saved')">Save</button>

<template x-for="t in $store.toast.messages" :key="t.id">
    <div x-text="t.msg"></div>
</template>
```

Reserve stores for genuinely client-originated shared state. Server-originated shared state (auth user, flash) still comes from Blade/session, not a store.

### Cross-component coordination — Livewire events

When a *server-side* component must react to something another component did, use Livewire events, not Alpine. Dispatch from one, listen with `#[On]` on another.

```php
// emitting component
$this->dispatch('project-created', id: $project->id);

// listening component
use Livewire\Attributes\On;

#[On('project-created')]
public function refreshList(int $id): void
{
    unset($this->projects); // bust the #[Computed] cache so it re-queries
}
```

To trigger a client store from a server event, listen in Blade/Alpine: `Livewire.on('project-created', () => Alpine.store('toast').open('Project created'))`.

### Side effects — Alpine lifecycle + Livewire hooks

The `$effect` setup/teardown pattern maps to Alpine's `init()` / `destroy()` for client systems (WebSockets, timers, DOM/canvas APIs):

```js
// resources/js/alpine/presence.js
import Alpine from 'alpinejs'

Alpine.data('presence', (channelName) => ({
    onlineUsers: [],
    channel: null,

    init() {
        this.channel = Echo.join(channelName)
            .here(users => { this.onlineUsers = users })
            .joining(user => { this.onlineUsers.push(user) })
            .leaving(user => { this.onlineUsers = this.onlineUsers.filter(u => u !== user) })
    },

    destroy() {
        this.channel?.leave() // cleanup when the element is removed
    },
}))
```

For **server-driven** side effects (load data on mount, react to a property change), use Livewire lifecycle hooks — `mount()`, `boot()`, `updated()` — not Alpine. Use `updated()` sparingly, the same way you'd use `$effect` sparingly.

---

## Security Mandates (Livewire-specific)

Inertia handled several of these structurally because state only flowed one way. Livewire rehydrates component state from the client between requests, so a few footguns need active discipline. Since these have no analogue in your Inertia doc, they're called out explicitly:

- **`#[Locked]` on any property the client must not change** — IDs, ownership keys, prices, status fields set server-side. Without it, a public property can be tampered with in the browser and trusted on rehydration.
- **Never bind a public property straight into a mass-assignment** unless every field is intended to be user-editable. Pull validated data via the form object's `pull()`/`only()` and pass an explicit array to the model.
- **Authorize in the component, every time** — `$this->authorize(...)` in `mount()` for page access and again in any action method that mutates. Middleware only covers "is logged in".
- **Validation is not authorization.** A Form object validates shape; a policy decides permission. You need both.
- **Treat `wire:model` values as untrusted input** — they are. Validation rules run server-side regardless of any client-side checks.

---

## Full-Stack Decision Tree

| What you're deciding | Answer |
|---|---|
| Where does routing live? | Laravel (`routes/web.php`), via `Route::livewire(...)`. Prefer grouped CRUD; single routes for one-off actions. |
| Where does auth, validation, data fetching live? | Laravel — components delegate to policies, form objects, services. |
| Where does business logic live? | Domain service classes; Laravel Actions for bespoke cross-cutting tasks. |
| What is the page entry point? | A full-page Livewire component (thin — delegates like a controller). |
| Where does form state + validation live? | A `Livewire\Form` object, with `#[Validate]` / `rules()`. |
| Where does authorization live? | Policies, invoked explicitly in `mount()` and mutating actions. |
| When do I reach for a traditional controller? | Non-Livewire surfaces only: API, webhooks, downloads, stateless redirects. |
| Is this state Livewire or Alpine? | Server-meaningful / persisted / validated → Livewire. Ephemeral / presentational / instant → Alpine. Both, bridged via `wire:model` or direct `$wire` access, when it needs both. |
| When do I extract an `Alpine.data()` definition? | When client-only logic is complex enough to clutter `x-data`. |
| When do I use an Alpine store? | When client-only state is shared across components (toasts, theme, sidebar). |
| Store vs Livewire event? | Store = client-originated shared state. Livewire event = coordinating server-side components. |
| How do components talk server-side? | `$this->dispatch(...)` + `#[On(...)]`. |

---

## File Structure Reference

```
app/
  Livewire/
    Forms/                     # Livewire Form objects (validation + form state)
      ProjectForm.php
    Project/                   # Multi-file component classes (when extracted from SFC)
      ProjectBoard.php
  Actions/                     # lorisleiva/laravel-actions (bespoke tasks)
    Project/
      ArchiveCompletedProjects.php
  Services/                    # Domain service classes (core business logic)
    Project/
      ProjectService.php
  Policies/                    # Authorization
    ProjectPolicy.php
  Http/
    Controllers/               # Only non-Livewire surfaces (API, webhooks)
    Requests/                  # FormRequests for those controller surfaces

resources/
  views/
    pages/                     # Full-page Livewire components (pages:: namespace)
      project/
        ⚡index.blade.php
        ⚡show.blade.php
        ⚡create.blade.php
    components/                # Nested/reusable Livewire + Blade components
      ⚡project-board.blade.php
      input-text.blade.php
      modal.blade.php
    layouts/
      app.blade.php
  js/
    alpine/                    # Extracted client-only logic
      chat.js                  # Alpine.data() definitions
      presence.js
      stores/
        toast.js               # Alpine.store() shared client state
```

---

## Key Mental Model

1. **Laravel is the brain** — routing, auth, validation, business logic, data.
2. **Livewire components are thin dispatchers with a view** — they hold minimal state, bind inputs, and delegate everything non-trivial to services, actions, and form objects. They are *not* where logic accumulates, even though nothing stops you putting it there. That restraint is the whole game.
3. **Services hold domain logic, Actions hold bespoke operations** — identical to any clean Laravel app.
4. **Form objects own form state + validation; policies own authorization** — the two halves the old `FormRequest` used to carry.
5. **Livewire owns server state, Alpine owns client state** — round-trip when the backend cares, stay on the client when it doesn't, and bridge with `wire:model` / direct `$wire` access when it's both.
6. **The boundary is not enforced for you** — Livewire will happily let you collapse all of this into one fat component. The structure exists only because you keep imposing it.

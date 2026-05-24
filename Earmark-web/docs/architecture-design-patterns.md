# Full-Stack Architecture Prompt — Laravel + Inertia.js + Svelte 5

Use this as a system/context prompt when asking an LLM to build features in this stack.

---

## Stack Overview

- **Backend**: Laravel with thin controllers, domain service classes, and Laravel Actions (`lorisleiva/laravel-actions`) for bespoke operations
- **Bridge**: Inertia.js — no REST API, no client-side routing. Laravel owns routing and controllers; Inertia renders Svelte pages with server-provided props
- **Frontend**: Svelte 5 with runes (`$state`, `$derived`, `$effect`) and TypeScript

---

## Backend Rules

### Thin Controllers

Controllers are routing glue only. They receive the request, delegate to a service or action, and return an Inertia response. A controller method should rarely exceed ~10 lines — ideally just a delegate + return.

**Controller conventions:**

- **Naming is singular.** `ProjectController`, `UserController`, `ActivityController` — never `ProjectsController`.
- **Parameter injection order is fixed:**
  1. The `FormRequest` subclass (typed — never a raw `Request`). It brings validated data and owns authorization.
  2. Route-bound models (e.g. `Project $project` for route `/projects/{project}`).
  3. The `Action` or `Service` that performs the work.
- **Always declare a return type** (`RedirectResponse`, `\Inertia\Response`, `JsonResponse`).
- **Body = delegate + return.** Grab validated data via `$request->validated()` and pass it straight to the action/service. No authorization checks, no validation, no business logic — those live in the form request and the action/service.

```php
// app/Http/Controllers/ProjectController.php
class ProjectController extends Controller
{
    public function index(ProjectService $service): \Inertia\Response
    {
        return inertia('projects/Index', [
            'projects' => $service->listForUser(auth()->user()),
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProject $action): RedirectResponse
    {
        $action->handle($request->validated(), $request->user());
        return redirect()->route('projects.index');
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $action): RedirectResponse
    {
        $action->handle($project, $request->validated());
        return redirect()->route('projects.show', $project);
    }
}
```

### Form Requests

Every non-trivial endpoint has a dedicated `FormRequest` subclass. Authorization and validation live there, not in the controller. The controller becomes a one-liner because the request has already produced clean, typed, authorized data by the time the controller method runs.

**When to create one:** any endpoint with validation rules, any endpoint with authorization beyond "user is logged in" (which middleware already covers), and any endpoint whose rules might be reused elsewhere (e.g. a matching API controller).

**Create it:** `php artisan make:request StorePostRequest --no-interaction`. Generated under `app/Http/Requests/`. Name as `Store{Model}Request`, `Update{Model}Request`, `Delete{Model}Request`, or a verb-based name for bespoke actions (`ArchiveProjectRequest`).

**The two required methods:**

```php
// app/Http/Requests/StorePostRequest.php
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    public function rules(): array
    {
        return [
            'title'  => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'text'   => ['required', 'string'],
        ];
    }
}
```

- `authorize(): bool` — return a boolean. Laravel responds with 403 automatically when it's false. Call gates/policies here (`$this->user()->can(...)`). Route-bound resources are accessible via `$this->route('post')`.
- `rules(): array` — validation rules. Prefer array-of-rules syntax so rule objects and custom `Rule::...` helpers slot in cleanly.

**In the controller**, type-hint the form request. Validation and authorization have already run by injection time, and `$request->validated()` returns the validated subset as an array:

```php
public function store(StorePostRequest $request, CreatePost $action): RedirectResponse
{
    $action->handle($request->validated(), $request->user());
    return redirect()->route('posts.index');
}
```

**Cross-field / contextual rules — `after()`.** When a rule depends on multiple fields or on state that isn't a per-field check (e.g. "user hasn't already posted today"), use the `after` method. It returns an array of callables (closures or invokable classes) that receive the `Illuminate\Validation\Validator` and can push errors onto it:

```php
use Illuminate\Validation\Validator;

public function after(): array
{
    return [
        function (Validator $validator) {
            if ($this->user()->posts()->whereDate('created_at', today())->exists()) {
                $validator->errors()->add(
                    'title',
                    'You have already posted today.'
                );
            }
        },
    ];
}
```

Prefer **invokable classes** over closures when the extra rule is reusable or non-trivial — keeps the `after()` array readable:

```php
public function after(): array
{
    return [new EnsureUserHasQuotaRemaining($this->user())];
}
```

**Other useful properties:**

- `protected $stopOnFirstFailure = true;` — stop on the first failed rule instead of collecting all errors.
- `protected $redirect = '/dashboard';` or `protected $redirectRoute = 'dashboard';` — customize where the user is sent on validation failure (defaults to `back()`).

**Reuse across web + API.** The same form request works in both controllers; Inertia receives validation errors via the standard flash-back flow, JSON clients receive a `422` with the errors array. Don't duplicate validation rules per surface — one form request per logical operation.

Docs: `laravel.com/docs/12.x/validation#form-request-validation`.

### Domain Service Classes
Reusable business logic lives in service classes grouped by domain. Services are plain PHP classes resolved from the container. They encapsulate queries, orchestration, and business rules.

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
Use `lorisleiva/laravel-actions` when an operation is a self-contained task that benefits from being runnable as a controller, job, listener, or command — or when it bundles its own authorization, validation, and execution into a single class. Don't use actions for trivial CRUD that a service already handles cleanly.

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

    public function asController(Request $request): RedirectResponse
    {
        $this->handle($request->user());
        return back()->with('success', 'Completed projects archived.');
    }
}
```

### Decision: Service vs Action

| Situation | Use |
|---|---|
| Standard domain logic (queries, CRUD orchestration, business rules) | Service class |
| Bespoke task that should also run as a job, command, or listener | Laravel Action |
| Operation with its own auth + validation that acts as a standalone endpoint | Laravel Action (`asController`) |
| Logic reused across multiple services or contexts | Extract to a Laravel Action, call it from any service via `MyAction::run(...)` |
| Complex multi-step operation or pipeline | Laravel Action wrapping a `Pipeline` (see below) or `DB::transaction` |
| A service class is growing too large | Extract cohesive chunks into Actions that the service calls — the service becomes an orchestrator |
| Trivial one-liner | Keep it in the service or even inline in the controller |

**Actions as service decomposition.** When a service class starts accumulating too many responsibilities, extract discrete operations into Actions. The service then orchestrates by calling those Actions rather than owning every line of logic itself. This keeps services readable while making extracted logic independently testable and reusable elsewhere.

```php
// The service stays thin — it orchestrates Actions
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

**Complex pipelines inside Actions.** For multi-step operations where each step transforms or validates data sequentially, use Laravel's `Pipeline` pattern inside an Action. Wrap the pipeline in `DB::transaction` when the steps involve database writes that should all revert if any step fails.

```php
// app/Actions/Order/ProcessRefund.php
class ProcessRefund
{
    use AsAction;

    public function handle(Order $order, array $data): Refund
    {
        return DB::transaction(function () use ($order, $data) {
            return app(Pipeline::class)
                ->send(new RefundContext($order, $data))
                ->through([
                    ValidateRefundEligibility::class,
                    CalculateRefundAmount::class,
                    ReversePayment::class,
                    RestockItems::class,
                    CreateRefundRecord::class,
                ])
                ->thenReturn()
                ->refund;
        });
    }
}
```

Each pipeline stage is a small, focused invokable class. If any stage throws, the `DB::transaction` rolls everything back.

---

## Inertia Bridge Rules

- Laravel controllers return `inertia('Page/Name', [...props])` — never raw JSON unless building a separate API.
- Page components live in `resources/js/pages/` and are resolved by Inertia automatically. (Top-level dirs lowercase per Laravel's official Inertia-Svelte starter; component files stay PascalCase, e.g. `pages/projects/Index.svelte`.)
- Props are typed on the Svelte side using `$props()`:

```svelte
<!-- resources/js/pages/projects/Index.svelte -->
<script lang="ts">
  import type { Project } from '$lib/types';

  let { projects }: { projects: Project[] } = $props();
</script>
```

- Use Inertia's `router` for navigation and form submission, not fetch/axios:

```ts
import { router } from '@inertiajs/svelte';
router.post('/projects', { name: 'New project' });
```

- Use Inertia's `useForm` for forms with validation error handling.
- Shared data (auth user, flash messages) flows through `HandleInertiaRequests` middleware, not Svelte context.

---

## Frontend Rules

### Reactivity Primitive
Use Svelte 5 runes (`$state`, `$derived`, `$effect`) — never legacy `$:` or `let` reactivity.

### Component Structure
Each `.svelte` file is one component (script + markup + styles). Offload complex client-side logic into classes rather than creating components just to split code.

### Client-Side State — Classes in `.svelte.ts` files
When a page has complex UI state that doesn't come from the server (modals, multi-step forms, drag-and-drop, canvas interactions, realtime updates), extract it into a class using runes.

**Always start with an interface** that describes the public API (fields + methods), then implement the class. This makes the contract explicit and keeps the class focused.

Use `$state` for mutable reactive fields, `$derived` for computed values, and `$effect` (sparingly) for side effects. Together they give you a complete reactive model inside a plain class.

```ts
// resources/js/lib/states/chat-state.svelte.ts

interface IChatState {
  messages: Message[];
  isLoading: boolean;
  messageCount: number;           // derived
  sendMessage: (text: string) => void;
}

export class ChatState implements IChatState {
  messages = $state<Message[]>([]);
  isLoading = $state(false);

  // $derived fields compute reactively from $state fields
  messageCount = $derived(this.messages.length);

  sendMessage(text: string) {
    this.isLoading = true;
    this.messages.push({ role: 'user', text });

    // simulate async response
    setTimeout(() => {
      this.messages.push({ role: 'assistant', text: 'Got it!' });
      this.isLoading = false;
    }, 400);
  }
}
```

```svelte
<script lang="ts">
  import { ChatState } from '$lib/states/chat-state.svelte';
  const chat = new ChatState();
</script>

<p>{chat.messageCount} messages</p>
<button onclick={() => chat.sendMessage('Hello')}>Send</button>
```

**Gotcha — never destructure state classes.** Destructuring breaks reactivity because it snapshots the value at that moment. Always access fields through the class instance:

```ts
// WRONG — loses reactivity
const { messages, sendMessage } = chat;

// RIGHT — stays reactive
chat.messages
chat.sendMessage('Hello')
```

### Shared Client State — Context (not module singletons)
When multiple components need the same reactive client state (toasts, modals, sidebar state, realtime presence), use Svelte context. Never export a module-level class instance — it leaks state during SSR.

```ts
// resources/js/lib/states/toast-state.svelte.ts
import { setContext, getContext } from 'svelte';

const KEY = Symbol('toast');

export class ToastState {
  messages = $state<ToastMessage[]>([]);

  open(msg: string, type: 'success' | 'error' = 'success') {
    this.messages.push({ id: crypto.randomUUID(), msg, type });
  }

  dismiss(id: string) {
    this.messages = this.messages.filter(m => m.id !== id);
  }
}

export const setToastState = () => setContext(KEY, new ToastState());
export const getToastState = () => getContext<ToastState>(KEY);
```

- Call `set` in a layout (e.g., the root `Layout.svelte` used by Inertia).
- Call `get` in any descendant page or component.
- Use keyed Symbols if you need multiple independent instances of the same state class in the same tree.

### Using `$effect` in State Classes
Use `$effect` sparingly and only for syncing reactive state with non-reactive external systems (timers, WebSockets, DOM APIs). Effects run whenever their dependencies change and auto-cleanup when the component unmounts. If you need setup/teardown logic (e.g., a WebSocket connection), put it in the class and initialize it via `$effect` in the component:

```ts
// resources/js/lib/states/presence-state.svelte.ts
export class PresenceState {
  onlineUsers = $state<string[]>([]);
  private channel: any;

  connect(channelName: string) {
    this.channel = Echo.join(channelName)
      .here((users: string[]) => { this.onlineUsers = users; })
      .joining((user: string) => { this.onlineUsers.push(user); })
      .leaving((user: string) => {
        this.onlineUsers = this.onlineUsers.filter(u => u !== user);
      });
  }

  disconnect() {
    this.channel?.leave();
  }
}
```

```svelte
<script lang="ts">
  import { PresenceState } from '$lib/states/presence-state.svelte';
  const presence = new PresenceState();

  $effect(() => {
    presence.connect('project.1');
    return () => presence.disconnect(); // cleanup on unmount
  });
</script>
```

---

## Full-Stack Decision Tree

| What you're deciding | Answer |
|---|---|
| Where does routing live? | Laravel (`routes/web.php`). Inertia handles client-side page transitions, not routing. Prefer `Route::resource` (with `->only()`, `->shallow()`, `->parameters()`) over individual route definitions. Use single routes only for one-off actions that don't fit CRUD (e.g., `archive`, `reorder`, `download`). |
| Where does auth, validation, data fetching live? | Laravel — controllers, form requests, middleware, services. |
| Where does business logic live? | Domain service classes. Laravel Actions for bespoke cross-cutting tasks. |
| What does a controller return? | `inertia('Page', $props)` or `redirect()`. |
| What does a Svelte page receive? | Server props via `$props()`. |
| When do I create a `.svelte.ts` state class? | When client-side UI logic is complex enough to clutter the component script. |
| When do I use Svelte context? | When client state must be shared across components (toasts, modals, sidebar). |
| When do I use a Svelte context vs Inertia shared data? | Inertia shared data = server-originated (auth user, flash). Svelte context = client-originated (UI state). |

---

## File Structure Reference

```
app/
  Http/Controllers/          # Thin — delegate to services/actions
  Actions/                   # lorisleiva/laravel-actions (bespoke tasks)
    Project/
      ArchiveCompletedProjects.php
  Services/                  # Domain service classes (core business logic)
    Project/
      ProjectService.php

resources/js/
  pages/                     # Inertia page components (resolved by name). Subdirs lowercase, files PascalCase.
    projects/
      Index.svelte
      Show.svelte
  components/                # Reusable Svelte UI components (files PascalCase)
    Toast.svelte
    Modal.svelte
  layouts/                   # Inertia persistent layouts
    AppLayout.svelte
  lib/
    states/                  # Svelte 5 reactive state classes (kebab-case filenames)
      toast-state.svelte.ts
      sidebar-state.svelte.ts
    types/                   # Shared TypeScript types
      index.ts
```

---

## Key Mental Model

1. **Laravel is the brain** — routing, auth, validation, business logic, data. Svelte never fetches data on its own; it receives props from Inertia.
2. **Controllers are thin dispatchers** — they delegate to domain services (or actions for bespoke tasks) and hand props to Inertia.
3. **Services hold domain logic, Actions hold bespoke operations** — services for everyday business rules, actions when you need the same logic runnable as controller + job + command + listener.
4. **Inertia is the bridge** — it sends props down and handles navigation/forms. No API layer needed.
5. **Svelte pages are renderers with local intelligence** — they receive server props and manage UI state. Complex UI state goes into `.svelte.ts` classes using runes.
6. **Context for shared client state, Inertia shared data for shared server state** — never confuse the two. Never use module-level singletons for state.

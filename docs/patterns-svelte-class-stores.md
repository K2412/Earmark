# Pattern: Svelte 5 class stores

The house pattern for client reactivity in this app. Architecture rules that consume it live in [`architecture-svelte.md`](./architecture-svelte.md).

This is the theory behind those rules — the mental model, the three scopes, the tree, and the one leak you must not create. It is adapted from [Ben Davis's walkthrough of classes, runes, and context](https://github.com/bmdavis419/Svelte-Stores-Streams-Effect) (the chat class, the counter context, the toast-in-the-root-layout example) and restated for **Laravel + Inertia**, not SvelteKit.

---

## The problem the pattern solves

Svelte is one component per file. A `.svelte` file has one `<script>`, one markup tree, and one `<style>`. Before runes, anything you wanted to split out of that script became another component, which meant another file, which meant another network of props. That is a bad tax for "the script got long."

Svelte 5 runes — `$state`, `$derived`, `$effect`, `$inspect` — are not a `.svelte`-only privilege. They work in `.svelte.ts` files. A class field initialized with `$state` is the same reactive variable you would have written in the page script. The class is a box you can import. The page keeps the markup. The logic leaves.

That is the whole pattern. Everything else is about *where the box is created*, because that decides who can see it.

---

## The primitive

These four runes are the ones that matter:

| Rune | Role |
|---|---|
| `$state` | A reactive field. Mutate it; anything that read it updates. |
| `$derived` / `$derived.by` | A value computed from `$state`. Not stored. Not synced by `$effect`. |
| `$effect` | A side effect with setup/teardown. Use sparingly. |
| `$inspect` | Dev-only peek at a reactive value. |

They are identical in a page `<script>` and in a `.svelte.ts` class. There is no second reactivity system. You are not wrapping a `writable`. You are not returning a store contract. You write `messages = $state<Message[]>([])` on the class and `chat.messages` in the markup, and it just updates.

Svelte 4 `readable` / `writable` / `derived` are the old syntax. bits-ui internals still use them; leave those alone. **New client state is `$state` on a class.**

---

## Always start with the shape

Define the public surface as an interface. Then implement it. The interface is the contract the rest of the app programs against; the class is the one place the mutations live.

```ts
type Message = {
    role: 'user' | 'assistant';
    content: string;
    id: string;
};

interface ChatState {
    messages: Message[];
    isLoading: boolean;
    sendMessage: (message: string) => void;
}

export class ChatStateClass implements ChatState {
    messages = $state<Message[]>([
        {
            role: 'assistant',
            content: 'Hello! How can I help you today?',
            id: crypto.randomUUID(),
        },
    ]);

    isLoading = $state(false);

    sendMessage = (message: string) => {
        this.isLoading = true;
        this.messages.push({
            role: 'user',
            content: message,
            id: crypto.randomUUID(),
        });

        setTimeout(() => {
            this.messages.push({
                role: 'assistant',
                content: 'Got it.',
                id: crypto.randomUUID(),
            });
            this.isLoading = false;
        }, 400);
    };
}
```

Why an interface first:

- The page only needs to know `messages` / `isLoading` / `sendMessage`. It does not need to know about fake responses, timers, or a later `useHttp` call.
- `getContext<ChatState>(key)` is typed against the interface, not the class. You can swap the implementation without touching consumers.
- Methods that mutate `$state` are arrow functions on the class (`sendMessage = (...) => { ... }`) so `onclick={chat.sendMessage}` does not lose `this`.

Access fields on the instance. **Do not destructure reactive fields:**

```ts
// wrong — snapshots the current array, loses reactivity
const { messages } = chat;

// right — read through the instance in markup
chat.messages
```

A method *reference* is fine (`onclick={chat.sendMessage}`) because it is an arrow function. A field binding is not.

---

## The three scopes

Same class. Three places you are allowed to `new` it. The scope is a decision about *who shares the instance*, not about how reactivity works.

```
                    ┌─────────────────────────────────────┐
                    │  inline $state in the page script   │  one or two fields, this file only
                    └─────────────────────────────────────┘
                                      │
                                      │ script got fat
                                      ▼
                    ┌─────────────────────────────────────┐
                    │  const chat = new ChatStateClass()  │  page-local. dies with the page
                    │  inside a page / component script   │
                    └─────────────────────────────────────┘
                                      │
                                      │ two places need the same object
                                      ▼
                    ┌─────────────────────────────────────┐
                    │  setChatState() in a persistent     │  one instance for the subtree
                    │  layout; getChatState() below       │
                    └─────────────────────────────────────┘
```

There is no fourth scope. `export const chat = new ChatStateClass()` at module top-level is the leak. See [The SSR leak](#the-ssr-leak--never-export-a-module-level-instance).

### 1. Inline rune

```svelte
<script lang="ts">
    let open = $state(false);
</script>

<button onclick={() => (open = !open)}>Toggle</button>
```

Use this when the state is a couple of fields and nothing else needs them. Extracting a class for `open` is ceremony.

### 2. Local class instance

The page script is getting hard to read — D3, a wizard, a chat, canvas pointer state, a bunch of client-side fetch. Move the logic into `SomethingState.svelte.ts`, import the class, `new` it in the page that owns it.

```svelte
<script lang="ts">
    import { ChatStateClass } from './ChatState.svelte';

    const chat = new ChatStateClass();
    let draft = $state('');
</script>

{#each chat.messages as message (message.id)}
    <p>{message.content}</p>
{/each}

<form
    onsubmit={(e) => {
        e.preventDefault();
        chat.sendMessage(draft);
        draft = '';
    }}
>
    <input bind:value={draft} />
    <button disabled={chat.isLoading}>Send</button>
</form>
```

This instance is **trapped in this component**. A sibling page cannot see it. The layout cannot see it. Navigate away (Inertia swaps the page) and it is gone. That is correct for page-local work.

Colocate the `.svelte.ts` next to the page that owns it (`pages/projects/BoardState.svelte.ts`). If a second page needs the same *shape* but its own instance, it `new`s the same class. If it needs the same *instance*, that is scope 3.

### 3. Context store

Svelte is a tree. A parent provides a value; every descendant can ask for it by key. In SvelteKit that parent is `+layout.svelte`. In this app the parent is a **persistent Inertia layout** (`AppLayout`, `AuthLayout`). Pages swap underneath. Layouts do not. That is why the store is born in the layout: it outlives the page.

```
AppLayout                 ← setCounterState() lives here
├── page A                ← getCounterState()
│   └── Counter           ← getCounterState()   ─┐
│   └── Counter           ← getCounterState()    │  same instance
├── page B                ← getCounterState()    │
│   └── Counter           ← getCounterState()   ─┘
```

Mutate `count` in any of them and every other reader updates, because they all hold the same class instance.

```ts
// resources/js/lib/stores/counterState.svelte.ts
import { getContext, setContext } from 'svelte';

interface CounterState {
    count: number;
    increment: () => void;
    decrement: () => void;
}

class CounterStateClass implements CounterState {
    count = $state(0);

    increment = () => {
        this.count++;
    };

    decrement = () => {
        this.count--;
    };
}

const DEFAULT_KEY = '$_counter_state';

export const setCounterState = (key = DEFAULT_KEY) => {
    const counter = new CounterStateClass();
    return setContext(key, counter);
};

export const getCounterState = (key = DEFAULT_KEY) => {
    return getContext<CounterState>(key);
};
```

```svelte
<!-- layouts/AppLayout.svelte -->
<script lang="ts">
    import { setCounterState } from '@/lib/stores/counterState.svelte';

    setCounterState();
</script>
```

```svelte
<!-- any descendant -->
<script lang="ts">
    import { getCounterState } from '@/lib/stores/counterState.svelte';

    const counter = getCounterState();
</script>

<button onclick={counter.increment}>+</button>
<p>{counter.count}</p>
```

Two functions, one job:

- `setCounterState` **creates** the instance and hangs it on the tree. Call it once, at the parent.
- `getCounterState` **reads** that instance. Call it in any descendant.

This is the React context API with less machinery. No provider component, no hook that throws, no value-identity rerender trap. `setContext` / `getContext` and a class.

### Context is keyed

`getContext` / `setContext` take a key. The default key (`'$_counter_state'`) is the one store of that type. Pass a different key when two independent instances must live in the same tree — two boards on one page, two chats, two counters that must not tick together.

```ts
setCounterState('$_counter_a');
setCounterState('$_counter_b');

// later, in a child that should only see A
const counter = getCounterState('$_counter_a');
```

Call `getCounterState()` with no argument and you get the default. That is the common case. Keys exist so you are not forced to invent a second class when you need a second instance.

---

## The tree, in this app

SvelteKit's tree is `+layout.svelte` → `+page.svelte` → imported components. This app's tree is:

```
createInertiaApp layout resolver   (app.ts)
        │
        ▼
  AppLayout / AuthLayout           ← persistent. setContext belongs here
        │
        ▼
  page (pages/projects/Index.svelte)   ← swaps on every Inertia visit
        │
        ▼
  components
```

Consequences:

- **`set*` in a persistent layout, never in a page**, if the store must survive navigation or be visible to more than one page. A `new` in a page script dies when Inertia unmounts that page.
- **`get*` anywhere below the `set*`.** A component imported into the page can call `getToastState()` because the layout already provided it.
- **A page-local `new` is still correct** when the state should die with the page (a create-form wizard, a one-off canvas).
- **Do not `set*` in a child and expect a parent or sibling to `get*`.** Context flows down. The layout is the parent of every authenticated page; that is the highest legal `set*` for app-wide stores.

The toast example from the source walkthrough is the canonical app-wide store: the root layout calls `setToastState()` *and* mounts the toast UI. Any descendant calls `getToastState().open('Saved')`. One instance, one mount point, no prop drilling.

Server flash is a different path — `Inertia::flash(...)` → `initializeFlashToast()` → sonner. Do not route server flash through the toast class. The class is for client-originated notices.

---

## The SSR leak — never export a module-level instance

This is the one hard rule.

```ts
// forbidden
export const counter = new CounterStateClass();
```

```ts
// also forbidden
export const counter = $state({ count: 0 });
```

Svelte (and this app's Vite SSR build) will evaluate that module on the server. One process serves many requests. A module-level instance is shared across those requests. User A's increment becomes user B's count. It looks fine in `pnpm dev` on one tab and is a Heisenbug in production.

Context creates the instance **during render of a specific tree**, per request, per user. That is the point.

| Legal | Illegal |
|---|---|
| `const chat = new ChatStateClass()` inside a component script | `export const chat = new ChatStateClass()` in a `.ts` / `.svelte.ts` module |
| `setCounterState()` inside a layout script | `export const counter = setCounterState()` at module scope (there is no tree yet) |
| A factory that *returns* a new instance, called from a component | A factory that caches the instance in a module-level `let` |

Existing starter files (`theme.svelte.ts`, `twoFactorAuth.svelte.ts`) are module-level `$state` wrapped in a factory. They work because they are boot-time / client-only and were written before this pattern. **Do not copy that shape.** New shared state is a class plus `set*` / `get*`. Leave the starter files alone unless you are migrating one on purpose.

---

## What a class store is allowed to do

A class store owns **client** state. It may:

- Hold `$state` / `$derived` fields
- Expose methods that mutate those fields
- Talk to the browser (`setTimeout`, `localStorage`, canvas, D3, WebSocket)
- Call `useHttp` or `router` from a method when the *client* choreography is complex (optimistic list, multi-step local machine that later posts)

It may not:

- Be the source of truth for anything the server must trust (price, ownership, permission, "is this saved")
- Replace a Form Request, a policy, or an Inertia visit
- Cache an Inertia prop and treat the cache as fresher than the next `page.props`

After a write that matters, visit or reload so the prop comes back down. The class can make the click feel instant; it cannot become a second backend.

`$derived` is for values you can compute from `$state` (`messageCount`, `isFirst`, `filtered`). `$effect` is for the outside world (subscribe, listen, connect). If you are writing `$effect(() => { this.x = this.y + 1 })`, you wanted `$derived`.

---

## Decision

```
Is the backend the authority on this value?
  yes → Inertia prop. Change it by visiting a route.
  no  → $state.

Who else needs this $state?
  nobody, and it's one or two fields     → inline in the page script
  nobody, but the script is getting fat  → class in a colocated .svelte.ts, `new` in the page
  other components / other pages         → class + set* in a persistent layout + get* below

Are you about to write `export const x = new ...`?
  stop. that is the leak. use context.
```

---

## Naming and placement

| Kind | File | Exports |
|---|---|---|
| Page-local class | `resources/js/pages/<area>/<Name>State.svelte.ts` | `export class <Name>State` |
| Shared store | `resources/js/lib/stores/<name>State.svelte.ts` | class (unexported or exported) + `set<Name>State` + `get<Name>State` |
| Context key | `'$_<name>_state'` | string constant next to the get/set pair |

Match neighbouring casing (`toastState.svelte.ts`, not `toast-state.svelte.ts`). The `.svelte.ts` suffix is required — that is what lets the compiler see `$state` outside a `.svelte` file.

`set*` / `get*` names are the API. Do not invent `useToast` / `provideToast` / `toastStore`. This is not React and it is not a Svelte 4 store.

---

## What this is not

- **Not a Svelte 4 store.** No `subscribe`, no `$counter` auto-subscription, no `writable`. The `$` in `$state` is a rune, not a store prefix.
- **Not SvelteKit.** There is no `+layout.svelte`. The persistent Inertia layout is the parent.
- **Not a global.** Context is scoped to the subtree under the `set*`. A component outside that tree cannot see it — which is what you want, and why Auth pages do not inherit the app toast store unless `AuthLayout` also sets it.
- **Not a replacement for Inertia shared props.** `auth.user` and flash stay on `page.props`.
- **Not a reason to extract everything.** A `let open = $state(false)` in a modal is correct. The class exists for when the script stops being simple.

---

## Source

The runnable examples this pattern is distilled from:

- [bmdavis419/Svelte-Stores-Streams-Effect](https://github.com/bmdavis419/Svelte-Stores-Streams-Effect) — `src/routes/store/ChatState.svelte.ts` (local class), `src/routes/context/CounterState.svelte.ts` (keyed get/set + class), `src/routes/context/+layout.svelte` (set at the parent).

Translate `+layout.svelte` → a persistent Inertia layout. Everything else maps one-to-one.

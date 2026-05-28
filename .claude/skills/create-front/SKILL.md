---
name: create-front
description: Generate React 19 / TypeScript Inertia pages and components using Tailwind v4 and shadcn/ui — install shadcn primitives on demand, wire forms to Inertia useForm + Wayfinder helpers, no raw fetch. Use when asked to create a page, add a frontend feature, build a form, scaffold a screen, generate UI, or implement an Inertia page.
---

You are a **senior React / TypeScript engineer**. Job: implement a frontend feature in this Laravel + Inertia + Tailwind v4 + shadcn/ui codebase. Reach for shadcn primitives **first**; compose with Tailwind utilities second; write raw HTML elements only when no primitive fits.

Tests are out of scope. Use Inertia for **all** server interaction — never a raw `fetch()` to a Laravel route.

## 0. Confirm what's being built (briefly)

Before scaffolding, pin:
- **The page or component** — Inertia page (lives in `resources/js/pages/...`) or feature component (lives in `resources/js/components/<feature>/`)?
- **The backend contract** — what Action will this hit? What fields does its input `Data` expect? (`app/Data/<Name>Data.php` is the source of truth.) What does the controller return as Inertia props?
- **Form, list, detail, dashboard, modal?** — picks the primitive set.

If any of these is unclear, ask before writing. A page built against the wrong DTO shape is a re-do.

## 1. Initialise shadcn (first time only)

Check if shadcn is already set up:

```bash
ls components.json resources/js/components/ui 2>&1
```

If `components.json` is **missing**, this is the first use — initialise. **This command is interactive — stop and tell the user to run it themselves, then resume:**

```bash
npx shadcn@latest init
```

Recommended answers:
- TypeScript: **yes**
- Style: **new-york**
- Base color: **neutral** (works with the existing slate palette).
- CSS variables: **yes** (Tailwind v4 with CSS-first config).
- Path for components: `resources/js/components`.
- Path for utils: `resources/js/lib/utils.ts` (already exists with `cn()` — keep it).
- React Server Components: **no** (Inertia SPA).
- Tailwind CSS file: `resources/css/app.css`.

After init, `components.json` exists at the repo root and `resources/js/components/ui/` is the home for primitives.

## 2. Add each shadcn primitive you need

Audit what's already installed:

```bash
ls resources/js/components/ui 2>/dev/null
```

For every UI element you need that isn't there, install on demand:

```bash
npx shadcn@latest add button input label card form dialog
```

**Map the design need to a primitive before writing JSX.** If a primitive exists, use it.

| UI element | shadcn primitive | Install |
|---|---|---|
| Action button | `Button` | `add button` |
| Text input / email / number | `Input` | `add input` |
| Multiline text | `Textarea` | `add textarea` |
| Select | `Select` | `add select` |
| Checkbox / toggle | `Checkbox`, `Switch` | `add checkbox switch` |
| Radio | `RadioGroup` | `add radio-group` |
| Field label | `Label` | `add label` |
| Container with header/body/footer | `Card` | `add card` |
| Modal | `Dialog` | `add dialog` |
| Side panel | `Sheet` | `add sheet` |
| Hover-over | `Tooltip` | `add tooltip` |
| Dropdown | `DropdownMenu` | `add dropdown-menu` |
| Tabs | `Tabs` | `add tabs` |
| Data table | `Table` (+ build columns) | `add table` |
| Inline notification | `Alert` | `add alert` |
| Toast | `Sonner` | `add sonner` |
| Loading placeholder | `Skeleton` | `add skeleton` |
| Status pill | `Badge` | `add badge` |
| Divider | `Separator` | `add separator` |
| Date picker | `Calendar`, `Popover` | `add calendar popover` |
| Combobox / search | `Command`, `Popover` | `add command popover` |

**Never edit files under `resources/js/components/ui/`** — they are managed by `shadcn add` and re-runnable. Customise via Tailwind classes from the caller, or wrap in a feature component.

## 3. File layout

```
resources/js/
├── pages/                    # Inertia pages, one per route (kebab-case)
│   └── <area>/<page>.tsx
├── components/
│   ├── ui/                   # shadcn primitives — DO NOT EDIT
│   └── <feature>/            # your composed components
│       └── <component>.tsx
├── layouts/                  # shared page wrappers
│   └── app-layout.tsx        # (create on first need)
├── lib/
│   └── utils.ts              # cn() — already there
└── types/                    # shared TS types
```

- Page = the thing referenced by `Route::inertia('/path', 'area/page')` or returned by `Inertia::render('area/page', [...])`.
- Feature component = anything reused or anything >50 lines extracted from a page.
- Layout = wraps multiple pages (header, sidebar, toaster).

Import alias: `@/` → `resources/js/`. Use it everywhere — `import { Button } from '@/components/ui/button'`.

## 4. Page skeleton (Inertia)

```tsx
import { Head, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = PageProps<{
    // backend-supplied props, mirror the controller's payload
    items: Array<{ uuid: string; label: string }>;
}>;

export default function MyPage() {
    const { props } = usePage<Props>();

    return (
        <>
            <Head title="Mon écran" />
            <div className="container mx-auto py-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Mon écran</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* content */}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
```

Rules:
- **One default export per page file**, named PascalCase.
- **`Head` always present** with a meaningful title.
- **Props typed via `PageProps<{...}>`** from `@/types` so shared Inertia props (`auth`, `flash`, `errors`) merge in. If `PageProps` doesn't exist yet, add it to `resources/js/types/index.ts`:

  ```ts
  export type PageProps<T = Record<string, unknown>> = T & {
      auth?: { user?: { uuid: string; email: string } | null };
      flash?: { success?: string; error?: string };
  };
  ```

- **Field names that map to a backend `Request` must match exactly** — camelCase here, camelCase there (`firstName`, not `first_name`).

## 5. Form pattern — Inertia `useForm` + shadcn primitives (NOT shadcn's `<Form>`)

shadcn's `<Form>` ships with `react-hook-form`. **Do not use it** here — Inertia's `useForm` is the single source of form state, validation errors and submission lifecycle. Combine Inertia `useForm` with shadcn's atomic primitives (`<Input>`, `<Label>`, `<Button>`, `<Textarea>`, `<Select>`…).

```tsx
import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { store } from '@/actions/App/Http/Controllers/User/CreateUserController';

export default function CreateUser() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        firstName: '',
        lastName: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(store.url(), { onSuccess: () => reset() });
    };

    return (
        <>
            <Head title="Créer un utilisateur" />
            <div className="container mx-auto max-w-md py-8">
                <Card>
                    <CardHeader><CardTitle>Créer un utilisateur</CardTitle></CardHeader>

                    <form onSubmit={submit} noValidate>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    aria-invalid={!!errors.email}
                                    required
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">{errors.email}</p>
                                )}
                            </div>

                            {/* repeat for firstName, lastName */}
                        </CardContent>

                        <CardFooter>
                            <Button type="submit" disabled={processing} className="w-full">
                                {processing ? 'Création…' : 'Créer'}
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}
```

Form rules:
- **`useForm({ ... })` initial keys = backend Request fields** (exact names).
- **Submit URL = Wayfinder action helper**, not a literal string. Import from `@/actions/App/Http/Controllers/...`.
- **Errors render inline next to the field**, sourced from `errors`. No client-side revalidation of what `spatie/laravel-data` already enforces.
- **`processing` drives `disabled`** on the submit button and any related controls.
- **`reset()` on success** unless the page navigates away (Inertia handles redirect).

## 6. API interaction — all paths

| Need | API |
|---|---|
| Form submit (POST/PUT/PATCH/DELETE) | `useForm(...).post(url)` / `.put(url)` / `.patch(url)` / `.delete(url)`. URL via Wayfinder. |
| Link navigation | `<Link href={…}>` from `@inertiajs/react` (preserves SPA behaviour). |
| Imperative navigation | `router.visit(url, { ... })`. |
| Partial reload of one prop | `router.reload({ only: ['filteredItems'] })`. |
| Flash message after redirect | Read `usePage<Props>().props.flash` and render in an `<Alert>` or trigger `toast()` (Sonner). |

**Forbidden:**
- `fetch('/users', { method: 'POST', ... })` — bypasses Inertia (loses CSRF, validation errors, flash). Use `useForm` or `router.*`.
- Hardcoded URL strings — use Wayfinder helpers from `@/routes/...` (for navigation) or `@/actions/...` (for form action URLs). Wayfinder regenerates on `npm run dev` — never edit the generated files.
- Client-side state for data the server owns. Filters/sorts that need fresh data → `router.reload({ only: [...] })`, not local `useState` + fetch.

## 7. Tailwind v4 specifics

- **No `tailwind.config.js`** — CSS-first config in `resources/css/app.css` via `@theme { ... }`.
- **Use design tokens from the theme** (`bg-primary`, `text-destructive`, `border-input`, `bg-muted` etc., once shadcn init creates them) rather than raw colour utilities (`bg-indigo-600`). Tokens stay consistent under dark mode and theme changes.
- **Compose with `cn()`** when a prop conditionally extends classes: `cn('rounded p-2', isActive && 'bg-primary text-primary-foreground', className)`.
- **Dark mode** uses class strategy — wrap the app shell with `class="dark"` toggled by your theme provider, and use `dark:` utility variants in components.
- **Don't write inline `style={{}}`** unless the value is dynamic and no class can express it (computed widths, transforms).

## 8. Loading and error UX

- Submission loading → `processing` on the form's submit button.
- Page-level loading on partial reload → set `router.reload({ only: [...], onStart, onFinish })` or render `<Skeleton>` while data is undefined.
- Server validation errors → already in `errors` (per field).
- Page-level error / success → `<Alert>` reading `flash.error` / `flash.success`, OR a Sonner `toast` if the message is transient.

## 9. Verify

```bash
npm run types:check    # tsc --noEmit
npm run lint:check     # ESLint
npm run format:check   # Prettier (Tailwind class ordering plugin enabled)
npm run build          # production build sanity
```

If `tsc` complains about `Props` shape, the discrepancy is between the Inertia controller payload and the page's typing — fix the type, not the controller, unless the controller is genuinely wrong.

## Anti-patterns to refuse

- **Reaching for raw `<button>` / `<input>` when shadcn primitives exist.** Always start from a primitive; drop down only if the primitive can't express the need.
- **Editing files under `resources/js/components/ui/`.** They get regenerated by `shadcn add`. Customise from outside or wrap in `resources/js/components/<feature>/`.
- **Using shadcn's `<Form>` component.** It pulls in `react-hook-form`. Inertia's `useForm` already owns form state, validation and submission — use atomic primitives, not the `Form` wrapper.
- **Inline raw colours** (`bg-blue-600`, `text-gray-900`). Use design tokens (`bg-primary`, `text-foreground`) so dark mode and theming "just work."
- **Hand-rolled `useMemo` / `useCallback`.** `babel-plugin-react-compiler` is enabled — it memoises automatically. Manual memo is allowed but never required.
- **Manual `fetch()` to Laravel routes.** Always Inertia.
- **Hardcoded route strings.** Wayfinder helpers from `@/routes/...` and `@/actions/...`.
- **A new component that's 80 % a shadcn primitive plus a className tweak.** Just use the primitive with `className=…`.
- **Mixing `react-hook-form` and Inertia's `useForm` in the same page.** Pick one — and in this codebase it's Inertia.
- **Default export from a non-page file.** Pages use `export default`; everything else uses named exports.

## Sources of truth

- `app/Data/<Name>Data.php` — the field names + validation your `useForm` must mirror.
- `resources/js/pages/users/create.tsx` — current reference (will move to shadcn-based on first refactor).
- `resources/js/lib/utils.ts` — `cn()` helper.
- `resources/css/app.css` — Tailwind v4 theme config.
- `resources/js/routes/`, `resources/js/actions/` — Wayfinder-generated, **do not edit**.
- shadcn docs — https://ui.shadcn.com/docs/installation/vite (Tailwind v4 + Vite path) and component reference.

// Shared visual styling for category types, so a category reads the same colored
// pill everywhere (transactions, budget, import review). Class strings are written
// out in full so Tailwind's scanner keeps them in the build.

export type CategoryType =
    | 'income'
    | 'housing'
    | 'transportation'
    | 'food'
    | 'household'
    | 'personal'
    | 'health'
    | 'debt'
    | 'savings'
    | 'fees'
    | 'other';

export type CategoryStyle = { icon: string; classes: string };

const STYLES: Record<CategoryType, CategoryStyle> = {
    income: { icon: '💵', classes: 'bg-emerald-100 text-emerald-800' },
    housing: { icon: '🏠', classes: 'bg-amber-100 text-amber-800' },
    transportation: { icon: '🚗', classes: 'bg-sky-100 text-sky-800' },
    food: { icon: '🍽️', classes: 'bg-orange-100 text-orange-800' },
    household: { icon: '🧺', classes: 'bg-lime-100 text-lime-800' },
    personal: { icon: '🧑', classes: 'bg-violet-100 text-violet-800' },
    health: { icon: '➕', classes: 'bg-rose-100 text-rose-800' },
    debt: { icon: '💳', classes: 'bg-red-100 text-red-800' },
    savings: { icon: '🏦', classes: 'bg-teal-100 text-teal-800' },
    fees: { icon: '⚠️', classes: 'bg-zinc-200 text-zinc-800' },
    other: { icon: '🏷️', classes: 'bg-slate-100 text-slate-800' },
};

const FALLBACK: CategoryStyle = {
    icon: '•',
    classes: 'bg-muted text-muted-foreground',
};

export function categoryStyle(type?: string | null): CategoryStyle {
    return (type && STYLES[type as CategoryType]) || FALLBACK;
}

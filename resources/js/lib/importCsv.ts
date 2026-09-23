// Client-side mirror of the server's CSV normalization (App\Services\Import\CsvImportNormalizer
// and App\Support\Money). Used only to render the pre-submit preview; the server
// remains the authority for the values that are actually staged.

export type AmountMode = 'single' | 'debit_credit';
export type Sign = 'negative_is_outflow' | 'positive_is_outflow';

export const DATE_FORMATS = [
    'YYYY-MM-DD',
    'MM/DD/YYYY',
    'DD/MM/YYYY',
    'YYYY/MM/DD',
    'DD-MM-YYYY',
    'DD Mon YYYY',
] as const;

export type DateFormat = (typeof DATE_FORMATS)[number];

export type Mapping = {
    dateIndex: number;
    payeeIndex: number;
    amountMode: AmountMode;
    amountIndex: number;
    debitIndex: number;
    creditIndex: number;
    dateFormat: DateFormat;
    sign: Sign;
};

export type PreviewRow = {
    date: string | null;
    payee: string;
    amountCents: number | null;
    skipReason: string | null;
    warning: string | null;
};

export type RowPayload = {
    date: string;
    payee: string;
    amount?: string;
    debit?: string;
    credit?: string;
};

const NO_DESCRIPTION = '(no description)';

const DATE_PATTERNS: Record<DateFormat, RegExp> = {
    'YYYY-MM-DD': /^(\d{4})-(\d{2})-(\d{2})$/,
    'MM/DD/YYYY': /^(\d{2})\/(\d{2})\/(\d{4})$/,
    'DD/MM/YYYY': /^(\d{2})\/(\d{2})\/(\d{4})$/,
    'YYYY/MM/DD': /^(\d{4})\/(\d{2})\/(\d{2})$/,
    'DD-MM-YYYY': /^(\d{2})-(\d{2})-(\d{4})$/,
    'DD Mon YYYY': /^(\d{2}) ([A-Za-z]{3}) (\d{4})$/,
};

const MONTHS: Record<string, number> = {
    jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6,
    jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12,
};

export function normalizeDate(raw: string, format: DateFormat): string | null {
    const value = raw.trim();
    const match = value.match(DATE_PATTERNS[format]);

    if (!match) {
        return null;
    }

    let year: number;
    let month: number;
    let day: number;

    if (format === 'YYYY-MM-DD' || format === 'YYYY/MM/DD') {
        [, year, month, day] = match.map(Number);
    } else if (format === 'MM/DD/YYYY') {
        [, month, day, year] = match.map(Number);
    } else if (format === 'DD Mon YYYY') {
        day = Number(match[1]);
        month = MONTHS[match[2].toLowerCase()] ?? 0;
        year = Number(match[3]);

        if (month === 0) {
            return null;
        }
    } else {
        [, day, month, year] = match.map(Number);
    }

    const date = new Date(Date.UTC(year, month - 1, day));

    if (
        date.getUTCFullYear() !== year ||
        date.getUTCMonth() !== month - 1 ||
        date.getUTCDate() !== day
    ) {
        return null;
    }

    const pad = (n: number) => String(n).padStart(2, '0');

    return `${year}-${pad(month)}-${pad(day)}`;
}

export function parseAmountToCents(raw: string): number | null {
    let value = raw.trim();

    if (value === '') {
        return null;
    }

    let negative = false;
    const parenthesized = value.match(/^\((.*)\)$/);

    if (parenthesized) {
        negative = true;
        value = parenthesized[1];
    }

    if (value.includes('-')) {
        negative = true;
    }

    value = value.replace(/[^0-9.,]/g, '');

    if (value === '') {
        return null;
    }

    const decimalPos = Math.max(value.lastIndexOf('.'), value.lastIndexOf(','));

    let integer: string;
    let fraction: string;

    if (decimalPos === -1) {
        integer = value.replace(/\D/g, '');
        fraction = '';
    } else {
        integer = value.slice(0, decimalPos).replace(/\D/g, '');
        fraction = value.slice(decimalPos + 1).replace(/\D/g, '');
    }

    if (integer === '' && fraction === '') {
        return null;
    }

    fraction = (fraction + '00').slice(0, 2);
    const cents = Number(integer || '0') * 100 + Number(fraction);

    return negative ? -cents : cents;
}

function cell(cells: string[], index: number): string {
    return index >= 0 ? (cells[index] ?? '') : '';
}

function normalizeAmount(cells: string[], mapping: Mapping): number | null {
    if (mapping.amountMode === 'debit_credit') {
        const debit = parseAmountToCents(cell(cells, mapping.debitIndex));
        const credit = parseAmountToCents(cell(cells, mapping.creditIndex));

        if (debit !== null && debit !== 0) {
            return -Math.abs(debit);
        }

        if (credit !== null && credit !== 0) {
            return Math.abs(credit);
        }

        return null;
    }

    const amount = parseAmountToCents(cell(cells, mapping.amountIndex));

    if (amount === null) {
        return null;
    }

    return mapping.sign === 'positive_is_outflow' ? -amount : amount;
}

export function normalizeRow(cells: string[], mapping: Mapping): PreviewRow {
    const date = normalizeDate(cell(cells, mapping.dateIndex), mapping.dateFormat);

    if (date === null) {
        return { date: null, payee: '', amountCents: null, skipReason: 'Unrecognized date.', warning: null };
    }

    const amountCents = normalizeAmount(cells, mapping);

    if (amountCents === null) {
        return { date, payee: '', amountCents: null, skipReason: 'No parseable amount.', warning: null };
    }

    const rawPayee = cell(cells, mapping.payeeIndex).trim();
    const payee = rawPayee === '' ? NO_DESCRIPTION : rawPayee.replace(/\s+/g, ' ');
    const warning = rawPayee === '' ? 'Missing description.' : null;

    return { date, payee, amountCents, skipReason: null, warning };
}

export function buildRowPayload(cells: string[], mapping: Mapping): RowPayload {
    const base = {
        date: cell(cells, mapping.dateIndex),
        payee: cell(cells, mapping.payeeIndex),
    };

    if (mapping.amountMode === 'debit_credit') {
        return { ...base, debit: cell(cells, mapping.debitIndex), credit: cell(cells, mapping.creditIndex) };
    }

    return { ...base, amount: cell(cells, mapping.amountIndex) };
}

export function formatCents(cents: number): string {
    const negative = cents < 0;
    const absolute = (Math.abs(cents) / 100).toFixed(2);
    const withThousands = absolute.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `${negative ? '-' : ''}$${withThousands}`;
}

export function guessMapping(headers: string[]): Partial<Mapping> {
    const find = (pattern: RegExp) => headers.findIndex((header) => pattern.test(header.trim()));

    const dateIndex = find(/date/i);
    const payeeIndex = find(/desc|payee|name|memo|detail|narration/i);
    const amountIndex = find(/amount|value/i);
    const debitIndex = find(/debit|withdrawal|out/i);
    const creditIndex = find(/credit|deposit|in\b/i);

    const guess: Partial<Mapping> = {};

    if (dateIndex >= 0) guess.dateIndex = dateIndex;
    if (payeeIndex >= 0) guess.payeeIndex = payeeIndex;

    if (debitIndex >= 0 && creditIndex >= 0) {
        guess.amountMode = 'debit_credit';
        guess.debitIndex = debitIndex;
        guess.creditIndex = creditIndex;
    } else if (amountIndex >= 0) {
        guess.amountMode = 'single';
        guess.amountIndex = amountIndex;
    }

    return guess;
}

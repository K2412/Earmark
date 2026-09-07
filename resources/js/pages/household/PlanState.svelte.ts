export type PlanRow = {
    id: string;
    name: string;
    kind: string;
    obligation: string;
    rolled: string;
    needed: string;
    available: string;
    available_cents: number;
    needed_cents: number;
};

export type PlanStatus = 'Negative' | 'Underfunded' | 'OK';

export class PlanState {
    year = $state(0);
    month = $state(0);
    monthLabel = $state('');
    rows = $state<PlanRow[]>([]);

    constructor(input: {
        year: number;
        month: number;
        monthLabel: string;
        rows: PlanRow[];
    }) {
        this.year = input.year;
        this.month = input.month;
        this.monthLabel = input.monthLabel;
        this.rows = input.rows;
    }

    statusFor(row: PlanRow): PlanStatus {
        if (row.available_cents < 0) {
            return 'Negative';
        }

        if (row.available_cents < row.needed_cents) {
            return 'Underfunded';
        }

        return 'OK';
    }
}

import { useHttp } from '@inertiajs/svelte';
import TransactionController from '@/actions/App/Http/Controllers/Household/TransactionController';

type PayeeSuggestion = {
    rule_id: string | null;
    category_id: string | null;
    bucket_id: string | null;
};

export class TransactionEntryState {
    categoryId = $state('');
    bucketId = $state('');
    suggestedRuleId = $state<string | null>(null);

    #categoryTouched = false;
    #bucketTouched = false;
    #http = useHttp();

    touchCategory(): void {
        this.#categoryTouched = true;
    }

    touchBucket(): void {
        this.#bucketTouched = true;
    }

    async suggest(payee: string): Promise<void> {
        if (payee.trim() === '') {
            this.suggestedRuleId = null;

            return;
        }

        const result = (await this.#http.submit(
            TransactionController.suggest({ query: { payee } }),
        )) as PayeeSuggestion;

        this.suggestedRuleId = result.rule_id;

        if (!this.#categoryTouched && result.category_id) {
            this.categoryId = result.category_id;
        }

        if (!this.#bucketTouched && result.bucket_id) {
            this.bucketId = result.bucket_id;
        }
    }
}

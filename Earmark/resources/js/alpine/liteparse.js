import init, { LiteParse } from '@llamaindex/liteparse-wasm';

let wasmReady = null;

function ensureWasm() {
    if (!wasmReady) {
        wasmReady = init();
    }
    return wasmReady;
}

/**
 * Alpine.data('liteparse') — browser-side PDF parsing.
 * Usage in Blade:
 *
 *   <div x-data="liteparse" x-on:change="parseFile($event.target.files[0])">
 *       <input type="file" accept="application/pdf">
 *       <p x-show="parsing">Parsing…</p>
 *       <p x-show="error" x-text="error" class="text-red-600"></p>
 *       <pre x-show="result" x-text="result?.text"></pre>
 *   </div>
 *
 * Bridge to Livewire: $wire.set('parsed', result) once parsing completes.
 */
export default () => ({
    parsing: false,
    result: null,
    error: null,
    parser: null,

    async parseFile(file) {
        if (!file) {
            return;
        }

        this.parsing = true;
        this.error = null;
        this.result = null;

        try {
            await ensureWasm();
            this.parser ??= new LiteParse({
                outputFormat: 'json',
                ocrEnabled: false,
                quiet: true,
            });

            const bytes = new Uint8Array(await file.arrayBuffer());
            this.result = await this.parser.parse(bytes);
        } catch (e) {
            this.error = e?.message ?? String(e);
        } finally {
            this.parsing = false;
        }
    },
});

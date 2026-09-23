export type ParsedCsv = {
    headers: string[];
    rows: string[][];
};

/**
 * Parse CSV text into a header row and data rows. Handles quoted fields,
 * escaped quotes (""), and both LF and CRLF line endings. Parsing happens in the
 * browser only to drive column mapping and the preview — the server re-parses the
 * mapped cells authoritatively on submit.
 */
export function parseCsv(text: string): ParsedCsv {
    const records = tokenize(text).filter(
        (record) => !(record.length === 1 && record[0].trim() === ''),
    );

    if (records.length === 0) {
        return { headers: [], rows: [] };
    }

    const [headers, ...rows] = records;

    return { headers, rows };
}

function tokenize(text: string): string[][] {
    const normalized = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    const records: string[][] = [];
    let record: string[] = [];
    let field = '';
    let inQuotes = false;

    for (let i = 0; i < normalized.length; i++) {
        const char = normalized[i];

        if (inQuotes) {
            if (char === '"') {
                if (normalized[i + 1] === '"') {
                    field += '"';
                    i++;
                } else {
                    inQuotes = false;
                }
            } else {
                field += char;
            }

            continue;
        }

        if (char === '"') {
            inQuotes = true;
        } else if (char === ',') {
            record.push(field);
            field = '';
        } else if (char === '\n') {
            record.push(field);
            records.push(record);
            record = [];
            field = '';
        } else {
            field += char;
        }
    }

    if (field !== '' || record.length > 0) {
        record.push(field);
        records.push(record);
    }

    return records;
}

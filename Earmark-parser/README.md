# Earmark Parser

Separate Python PDF parser service for Earmark statement imports.

## Boundary

- Laravel lives in sibling `../Earmark-web`.
- This service owns Python parsing only.
- Laravel calls this service over HTTP using `POST /parse`.
- PDFs are processed in request memory and are not durably stored by this service.

## Local contract

```bash
PARSER_SECRET=local-secret python -m uvicorn earmark_parser.app:app --reload
```

Endpoint:

```http
POST /parse
x-parser-secret: local-secret
multipart field: file
```

Response:

```json
{
  "status": "ok",
  "transactions": [],
  "warnings": []
}
```

from __future__ import annotations

import os

from fastapi import FastAPI, File, Header, HTTPException, UploadFile, status

from .parser import parse_pdf_bytes
from .security import is_valid_parser_secret

app = FastAPI(title="Earmark Parser")


@app.post("/parse")
async def parse(
    file: UploadFile = File(...),
    x_parser_secret: str | None = Header(default=None),
) -> dict:
    expected_secret = os.environ.get("PARSER_SECRET", "")

    if not expected_secret or not is_valid_parser_secret(x_parser_secret, expected_secret):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid parser secret")

    pdf_bytes = await file.read()
    response = parse_pdf_bytes(pdf_bytes, file.filename or "statement.pdf")

    return response.to_dict()

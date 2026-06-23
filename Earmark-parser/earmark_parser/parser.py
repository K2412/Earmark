from __future__ import annotations

from .contract import ParseResponse


def parse_pdf_bytes(pdf_bytes: bytes, filename: str) -> ParseResponse:
    if not pdf_bytes.startswith(b"%PDF"):
        return ParseResponse(warnings=[f"{filename} does not start with a PDF header"])

    return ParseResponse(warnings=["No transactions detected by the generic parser yet"])

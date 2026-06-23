from earmark_parser.contract import ParsedTransaction, ParseResponse
from earmark_parser.parser import parse_pdf_bytes
from earmark_parser.security import is_valid_parser_secret


def test_parse_response_contract_serializes_transactions():
    response = ParseResponse(
        transactions=[
            ParsedTransaction(
                date="2026-04-15",
                payee="LOBLAWS #1234",
                amount_cents=-12743,
                raw_text="2026-04-15 LOBLAWS #1234 127.43",
            )
        ],
        warnings=["generic parser used"],
    )

    assert response.to_dict() == {
        "status": "ok",
        "transactions": [
            {
                "date": "2026-04-15",
                "payee": "LOBLAWS #1234",
                "amount_cents": -12743,
                "raw_text": "2026-04-15 LOBLAWS #1234 127.43",
            }
        ],
        "warnings": ["generic parser used"],
    }


def test_generic_parser_returns_warning_for_empty_pdf_contract():
    response = parse_pdf_bytes(b"%PDF-1.7\n", "statement.pdf")

    assert response.to_dict() == {
        "status": "ok",
        "transactions": [],
        "warnings": ["No transactions detected by the generic parser yet"],
    }


def test_parser_secret_uses_constant_time_comparison():
    assert is_valid_parser_secret("expected", "expected") is True
    assert is_valid_parser_secret("wrong", "expected") is False
    assert is_valid_parser_secret(None, "expected") is False

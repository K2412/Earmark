FROM python:3.11-slim

WORKDIR /app

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends build-essential \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY pyproject.toml ./
COPY earmark_parser ./earmark_parser

RUN pip install --no-cache-dir .

EXPOSE 8000

CMD ["uvicorn", "earmark_parser.app:app", "--host", "0.0.0.0", "--port", "8000"]

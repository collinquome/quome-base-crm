FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /app

# Install uv (small, fast, deterministic resolver)
RUN pip install --no-cache-dir uv==0.5.11

# Copy lockfile + project metadata first to maximise layer cache
COPY pyproject.toml uv.lock ./
RUN uv sync --frozen --no-dev

# Copy app source
COPY . .

# Railway sets $PORT
ENV PORT=8000
EXPOSE 8000

CMD ["sh", "-c", "uv run uvicorn main:app --host 0.0.0.0 --port ${PORT}"]

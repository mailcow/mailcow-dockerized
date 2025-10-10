FROM python:3.13.2-alpine3.21

WORKDIR /app

COPY migrate.py /app/migrate.py
RUN pip install --no-cache-dir redis

CMD ["python", "/app/migrate.py"]

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        lftp \
        nodejs \
        npm \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

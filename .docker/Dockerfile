ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    autoconf automake build-essential pkg-config git gdb gdbserver \
    clang bear valgrind ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /workspace
CMD ["sleep", "infinity"]

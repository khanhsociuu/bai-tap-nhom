# Small locally built alternative to the very large official gcc:14 image.
# Only build/use this for local classroom demonstrations. No secrets in context.
FROM alpine:3.21
RUN apk add --no-cache g++ coreutils

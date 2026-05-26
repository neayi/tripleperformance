FROM docker.n8n.io/n8nio/n8n

USER root

# Download and install static pandoc binary
# Using a multi-stage build approach with Alpine to download
FROM alpine:latest AS downloader
RUN apk add --no-cache wget tar && \
    wget https://github.com/jgm/pandoc/releases/download/3.1.11.1/pandoc-3.1.11.1-linux-amd64.tar.gz && \
    tar xvzf pandoc-3.1.11.1-linux-amd64.tar.gz --strip-components 1 -C /usr/local/

# Final image
FROM docker.n8n.io/n8nio/n8n

USER root

# Copy pandoc binary from downloader stage
COPY --from=downloader /usr/local/bin/pandoc /usr/local/bin/pandoc

USER node

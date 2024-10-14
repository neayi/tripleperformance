rm -rf ../.cache/ssl && mkdir ../.cache/ssl
docker run --rm -v $(pwd)/..:/src alpine:3.9 sh -c "/src/engine/traefik/dev/generate-certs.sh && chown -R $(id -u):$(id -g) /src/.cache/ssl"
docker compose up -d --force-recreate traefik

/mnt/c/Windows/System32/certutil.exe  -user -addstore "Root" .cache/ssl/root.crt
/mnt/c/Windows/System32/certutil.exe  -user -f -addstore "Root" .cache/ssl/server.crt

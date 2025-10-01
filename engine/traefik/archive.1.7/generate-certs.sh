#!/usr/bin/env sh

set -ex

CONFIG=/src/engine/traefik/dev
TARGET=/src/.cache/ssl
PASS=foobar

if [ ! -d "${CONFIG}" ]; then
  echo "The ${CONFIG} dir does not exist, are you running this command from a dedicated container ?"
  exit 1
fi

if [ ! -d "${TARGET}" ]; then
  echo "The ${TARGET} dir does not exist, are you running this command from a dedicated container ?"
  exit 1
fi

apk add --no-cache openssl

openssl req -days 825 -x509 -new -keyout ${TARGET}/root.key -out ${TARGET}/root.crt -config ${CONFIG}/root.cnf -passout pass:${PASS}
openssl req -days 825 -nodes -new -keyout ${TARGET}/server.key -out ${TARGET}/server.csr -config ${CONFIG}/server.cnf
openssl x509 -days 825 -req -in ${TARGET}/server.csr -CA ${TARGET}/root.crt -CAkey ${TARGET}/root.key -set_serial 123 -out ${TARGET}/server.crt -extfile ${CONFIG}/server.cnf -extensions x509_ext -passin pass:${PASS}

cat ${TARGET}/server.crt ${TARGET}/server.key > ${TARGET}/server.pem

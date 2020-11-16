#!/usr/bin/env bash

if [ -n "${MSMTP_SECRET}" ]; then
  echo "password $MSMTP_SECRET" >> /etc/msmtprc
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

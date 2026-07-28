#!/bin/sh
# DOCKER-USER (not INPUT): Docker routes traffic for published ports through
# its own FORWARD/NAT rules, bypassing INPUT entirely. DOCKER-USER is the one
# chain Docker guarantees it will never flush/overwrite on restart, and it
# survives being created before or after dockerd starts.
# Idempotent: -C checks first so re-running (e.g. on every boot) never piles
# up duplicate rules.

set -e

block() {
  range="$1"
  if ! iptables -C DOCKER-USER -s "$range" -j DROP 2>/dev/null; then
    iptables -I DOCKER-USER -s "$range" -j DROP
  fi
}

# Alibaba Cloud Singapore datacenter range - source of the SMW scraper
# hammering Special:PageProperty during the 2026-07-28 incident.
block 47.79.0.0/16

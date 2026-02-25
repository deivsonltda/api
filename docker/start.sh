#!/usr/bin/env bash
set -e

mkdir -p /run/nginx

nginx -t

exec supervisord -c /etc/supervisord.conf
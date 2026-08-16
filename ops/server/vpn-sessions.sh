#!/usr/bin/env bash

set -euo pipefail

SSH_PORT="22"
VPN_USER="root"

first=true

printf '{"sessions":['

while IFS= read -r line; do
    [[ -z "${line}" ]] && continue

    state="$(awk '{print $1}' <<< "${line}")"

    if [[ "${state}" != "ESTAB" ]]; then
        continue
    fi

    peer="$(awk '{print $5}' <<< "${line}")"

    pid="$(
        sed -n 's/.*pid=\([0-9][0-9]*\).*/\1/p' <<< "${line}" \
            | head -n 1
    )"

    if [[ -z "${pid}" ]]; then
        continue
    fi

   process_command="$(
       ps -p "${pid}" -o args= 2>/dev/null \
           | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' \
           || true
   )"

    if [[ "${process_command}" != "sshd: ${VPN_USER}" ]]; then
        continue
    fi

    if [[ "${peer}" == \[*\]:* ]]; then
        client_ip="${peer#\[}"
        client_ip="${client_ip%%\]:*}"
        client_port="${peer##*:}"
    else
        client_ip="${peer%:*}"
        client_port="${peer##*:}"
    fi

   elapsed_seconds="$(
       ps -p "${pid}" -o etimes= 2>/dev/null \
           | tr -d '[:space:]' \
           || true
   )"

    if [[ -z "${elapsed_seconds}" ]]; then
        elapsed_seconds=0
    fi

   total_connections="$(
       ss -H -tnp state established 2>/dev/null \
           | grep -F "pid=${pid}," \
           | wc -l \
           | tr -d '[:space:]' \
           || true
   )"

    if [[ -z "${total_connections}" ]]; then
        total_connections=0
    fi

    tunnel_connections=$((total_connections - 1))

    if (( tunnel_connections < 0 )); then
        tunnel_connections=0
    fi

    if [[ "${first}" == false ]]; then
        printf ','
    fi

    first=false

    printf '{'
    printf '"pid":%d,' "${pid}"
    printf '"ssh_user":"%s",' "${VPN_USER}"
    printf '"client_ip":"%s",' "${client_ip}"
    printf '"client_port":%d,' "${client_port}"
    printf '"elapsed_seconds":%d,' "${elapsed_seconds}"
    printf '"active_connections":%d' "${tunnel_connections}"
    printf '}'
done < <(
    ss -H -tnp "sport = :${SSH_PORT}" 2>/dev/null
)

printf ']}'

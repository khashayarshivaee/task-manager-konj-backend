#!/usr/bin/env bash

set -euo pipefail

SSH_PORT="22"
PROMETHEUS_URL="http://127.0.0.1:9090"

declare -A public_tcp_ports=()
declare -A all_client_ips=()
declare -A outline_client_ips=()
declare -A web_client_ips=()

declare -a connections=()
declare -a legacy_sessions=()

inbound_tcp_connections=0
ssh_vpn_sessions=0
ssh_admin_sessions=0
ssh_other_connections=0
ssh_preauth_connections=0
outline_tcp_connections=0
web_connections=0
other_connections=0

endpoint_ip=""
endpoint_port=""

parse_endpoint() {
    local endpoint="$1"

    endpoint_ip=""
    endpoint_port=""

    if [[ "${endpoint}" =~ ^\[(.*)\]:([0-9]+)$ ]]; then
        endpoint_ip="${BASH_REMATCH[1]}"
        endpoint_port="${BASH_REMATCH[2]}"
    elif [[ "${endpoint}" =~ ^(.+):([0-9]+)$ ]]; then
        endpoint_ip="${BASH_REMATCH[1]}"
        endpoint_port="${BASH_REMATCH[2]}"
    fi

    if [[ "${endpoint_ip}" == "::ffff:"* ]]; then
        endpoint_ip="${endpoint_ip#::ffff:}"
    fi
}

is_loopback_address() {
    local address="$1"

    [[
        "${address}" == "127."* ||
        "${address}" == "::1" ||
        "${address}" == "localhost"
    ]]
}

json_escape() {
    local value="$1"

    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    value="${value//$'\n'/\\n}"
    value="${value//$'\r'/\\r}"
    value="${value//$'\t'/\\t}"

    printf '%s' "${value}"
}

prometheus_scalar() {
    local query="$1"
    local response
    local value

    response="$(
        curl \
            --silent \
            --show-error \
            --fail \
            --max-time 2 \
            --get \
            "${PROMETHEUS_URL}/api/v1/query" \
            --data-urlencode "query=${query}" \
            2>/dev/null \
            || true
    )"

    value="$(
        sed -n \
            's/.*"value":\[[^,]*,"\([^"]*\)"\].*/\1/p' \
            <<< "${response}" \
            | head -n 1
    )"

    if [[ ! "${value}" =~ ^-?[0-9]+([.][0-9]+)?$ ]]; then
        printf '0'
        return
    fi

    awk -v value="${value}" 'BEGIN {
        printf "%.0f", value
    }'
}

get_process_command() {
    local pid="$1"

    ps -p "${pid}" -o args= 2>/dev/null \
        | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' \
        || true
}

get_process_name() {
    local pid="$1"

    ps -p "${pid}" -o comm= 2>/dev/null \
        | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' \
        || true
}

get_elapsed_seconds() {
    local pid="$1"
    local elapsed

    elapsed="$(
        ps -p "${pid}" -o etimes= 2>/dev/null \
            | tr -d '[:space:]' \
            || true
    )"

    if [[ ! "${elapsed}" =~ ^[0-9]+$ ]]; then
        elapsed=0
    fi

    printf '%s' "${elapsed}"
}

get_pid_connection_count() {
    local pid="$1"
    local total

    total="$(
        ss -H -tnp state established 2>/dev/null \
            | grep -F "pid=${pid}," \
            | wc -l \
            | tr -d '[:space:]' \
            || true
    )"

    if [[ ! "${total}" =~ ^[0-9]+$ ]]; then
        total=0
    fi

    printf '%s' "${total}"
}

append_connection() {
    local type="$1"
    local protocol="$2"
    local service="$3"
    local client_ip="$4"
    local client_port="$5"
    local server_port="$6"
    local pid="$7"
    local extra_json="${8:-}"
    local json

    printf -v json \
        '{"type":"%s","protocol":"%s","service":"%s","client_ip":"%s","client_port":%d,"server_port":%d,"pid":%d%s}' \
        "$(json_escape "${type}")" \
        "$(json_escape "${protocol}")" \
        "$(json_escape "${service}")" \
        "$(json_escape "${client_ip}")" \
        "${client_port}" \
        "${server_port}" \
        "${pid}" \
        "${extra_json}"

    connections+=("${json}")
}

append_legacy_session() {
    local pid="$1"
    local ssh_user="$2"
    local client_ip="$3"
    local client_port="$4"
    local elapsed_seconds="$5"
    local active_connections="$6"
    local json

    printf -v json \
        '{"pid":%d,"ssh_user":"%s","client_ip":"%s","client_port":%d,"elapsed_seconds":%d,"active_connections":%d}' \
        "${pid}" \
        "$(json_escape "${ssh_user}")" \
        "$(json_escape "${client_ip}")" \
        "${client_port}" \
        "${elapsed_seconds}" \
        "${active_connections}"

    legacy_sessions+=("${json}")
}

print_json_array() {
    local -n items_ref="$1"
    local first=true
    local item

    printf '['

    for item in "${items_ref[@]}"; do
        if [[ "${first}" == false ]]; then
            printf ','
        fi

        first=false
        printf '%s' "${item}"
    done

    printf ']'
}

#
# Discover publicly exposed TCP listener ports.
#

while IFS= read -r line; do
    [[ -z "${line}" ]] && continue

    read -r state recv_q send_q local_endpoint peer_endpoint rest <<< "${line}"

    [[ "${state}" != "LISTEN" ]] && continue

    parse_endpoint "${local_endpoint}"

    [[ -z "${endpoint_port}" ]] && continue

    if is_loopback_address "${endpoint_ip}"; then
        continue
    fi

    public_tcp_ports["${endpoint_port}"]=1
done < <(
    ss -H -lntp 2>/dev/null || true
)

#
# Outline metrics.
#
# TCP connections are read directly from the kernel with ss.
#
# UDP does not have a persistent TCP-style connection, so the current
# Outline NAT-association count is calculated from Prometheus counters.
#

outline_udp_associations="$(
    prometheus_scalar \
        'sum(shadowsocks_udp_nat_entries_added) - sum(shadowsocks_udp_nat_entries_removed)'
)"

outline_keys="$(
    prometheus_scalar \
        'sum(shadowsocks_keys)'
)"

outline_ports="$(
    prometheus_scalar \
        'sum(shadowsocks_ports)'
)"

if (( outline_udp_associations < 0 )); then
    outline_udp_associations=0
fi

#
# Inspect all established TCP connections.
#
# Only connections whose local port is currently exposed by a
# non-loopback listener are considered incoming server connections.
#

while IFS= read -r line; do
    [[ -z "${line}" ]] && continue

    read -r recv_q send_q local_endpoint peer_endpoint process_info <<< "${line}"

    parse_endpoint "${local_endpoint}"

    local_ip="${endpoint_ip}"
    local_port="${endpoint_port}"

    [[ -z "${local_port}" ]] && continue

    if [[ -z "${public_tcp_ports[${local_port}]+x}" ]]; then
        continue
    fi

    parse_endpoint "${peer_endpoint}"

    client_ip="${endpoint_ip}"
    client_port="${endpoint_port}"

    [[ -z "${client_ip}" ]] && continue
    [[ -z "${client_port}" ]] && continue

    if is_loopback_address "${client_ip}"; then
        continue
    fi

    pid="$(
        grep -o 'pid=[0-9][0-9]*' <<< "${process_info}" \
            | head -n 1 \
            | cut -d= -f2 \
            || true
    )"

    if [[ ! "${pid}" =~ ^[0-9]+$ ]]; then
        continue
    fi

    process_name="$(get_process_name "${pid}")"
    process_command="$(get_process_command "${pid}")"

    [[ -z "${process_name}" ]] && continue

    type="other"
    extra_json=""

    #
    # SSH connections
    #

    if (
        [[ "${local_port}" == "${SSH_PORT}" ]] &&
        [[ "${process_name}" == "sshd" ]]
    ); then
        elapsed_seconds="$(get_elapsed_seconds "${pid}")"

        #
        # SSH VPN tunnel
        #

        if [[ "${process_command}" == "sshd: root" ]]; then
            type="ssh_vpn"
            ssh_user="root"

            total_pid_connections="$(
                get_pid_connection_count "${pid}"
            )"

            proxied_connections=$((total_pid_connections - 1))

            if (( proxied_connections < 0 )); then
                proxied_connections=0
            fi

            ssh_vpn_sessions=$((ssh_vpn_sessions + 1))

            printf -v extra_json \
                ',"ssh_user":"%s","elapsed_seconds":%d,"proxied_connections":%d' \
                "$(json_escape "${ssh_user}")" \
                "${elapsed_seconds}" \
                "${proxied_connections}"

            #
            # Keep this old structure temporarily so the current
            # VpnSessionService continues working until it is upgraded.
            #

            append_legacy_session \
                "${pid}" \
                "${ssh_user}" \
                "${client_ip}" \
                "${client_port}" \
                "${elapsed_seconds}" \
                "${proxied_connections}"

        #
        # Interactive SSH administration session.
        #

        elif [[ "${process_command}" == sshd:\ *@pts/* ]]; then
            type="ssh_admin"

            ssh_user="${process_command#sshd: }"
            ssh_user="${ssh_user%@pts/*}"

            ssh_admin_sessions=$((ssh_admin_sessions + 1))

            printf -v extra_json \
                ',"ssh_user":"%s","elapsed_seconds":%d' \
                "$(json_escape "${ssh_user}")" \
                "${elapsed_seconds}"

        #
        # SSH connections that have not completed authentication yet.
        #

        elif (
            [[ "${process_command}" == *"[net]"* ]] ||
            [[ "${process_command}" == *"[priv]"* ]] ||
            [[ "${process_command}" == *"preauth"* ]]
        ); then
            type="ssh_preauth"

            ssh_preauth_connections=$(
                (ssh_preauth_connections + 1)
            )

        #
        # Authenticated or otherwise established SSH connection that
        # does not match one of our known categories.
        #

        else
            type="ssh_other"

            ssh_other_connections=$(
                (ssh_other_connections + 1)
            )
        fi

    #
    # Outline / Shadowsocks
    #

    elif [[ "${process_name}" == "outline-ss-serv" ]]; then
        type="outline"

        outline_tcp_connections=$(
            (outline_tcp_connections + 1)
        )

        outline_client_ips["${client_ip}"]=1

    #
    # Web / API
    #

    elif (
        [[ "${process_name}" == "nginx" ]] &&
        (
            [[ "${local_port}" == "80" ]] ||
            [[ "${local_port}" == "443" ]]
        )
    ); then
        type="web"

        web_connections=$(
            (web_connections + 1)
        )

        web_client_ips["${client_ip}"]=1

    #
    # Other incoming TCP services
    #

    else
        other_connections=$(
            (other_connections + 1)
        )
    fi

    inbound_tcp_connections=$(
        (inbound_tcp_connections + 1)
    )

    all_client_ips["${client_ip}"]=1

    append_connection \
        "${type}" \
        "tcp" \
        "${process_name}" \
        "${client_ip}" \
        "${client_port}" \
        "${local_port}" \
        "${pid}" \
        "${extra_json}"

done < <(
    ss -H -tnp state established 2>/dev/null || true
)

#
# Final JSON output
#

printf '{'

printf '"summary":{'

printf '"inbound_tcp_connections":%d,' \
    "${inbound_tcp_connections}"

printf '"unique_client_ips":%d,' \
    "${#all_client_ips[@]}"

printf '"ssh_vpn_sessions":%d,' \
    "${ssh_vpn_sessions}"

printf '"ssh_admin_sessions":%d,' \
    "${ssh_admin_sessions}"

printf '"ssh_other_connections":%d,' \
    "${ssh_other_connections}"

printf '"ssh_preauth_connections":%d,' \
    "${ssh_preauth_connections}"

printf '"outline_tcp_client_ips":%d,' \
    "${#outline_client_ips[@]}"

printf '"outline_tcp_connections":%d,' \
    "${outline_tcp_connections}"

printf '"outline_udp_associations":%d,' \
    "${outline_udp_associations}"

printf '"outline_keys":%d,' \
    "${outline_keys}"

printf '"outline_ports":%d,' \
    "${outline_ports}"

printf '"web_client_ips":%d,' \
    "${#web_client_ips[@]}"

printf '"web_connections":%d,' \
    "${web_connections}"

printf '"other_connections":%d' \
    "${other_connections}"

printf '},'

#
# Backward compatibility with the existing Laravel service.
#

printf '"sessions":'

print_json_array legacy_sessions

printf ','

#
# Full incoming connection list.
#

printf '"connections":'

print_json_array connections

printf '}'

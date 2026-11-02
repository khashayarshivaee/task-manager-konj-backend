#!/usr/bin/env python3

import json
import os
import subprocess
import sys
import time

from datetime import datetime, timezone



SYSTEMD_SERVICES = {
    "nginx": ("Nginx", "nginx.service"),
    "mariadb": ("MariaDB", "mariadb.service"),
    "named": ("DNS / BIND", "named.service"),
    "postfix": ("Postfix Mail", "postfix.service"),
    "opendkim": ("OpenDKIM", "opendkim.service"),
    "redis": ("Redis", "redis.service"),
    "supervisord": ("Supervisor", "supervisord.service"),
    "cron": ("Cron", "crond.service"),
    "php-fpm": ("PHP-FPM", "php-fpm.service"),
    "php84-php-fpm": ("PHP 8.4 FPM", "php84-php-fpm.service"),
    "firewalld": ("Firewall", "firewalld.service"),
    "sshd": ("SSH", "sshd.service"),
}



def cpu_times():
    with open("/proc/stat", encoding="utf-8") as file:
        values = [
            int(value)
            for value in file.readline().split()[1:]
        ]

    if len(values) < 5:
        raise RuntimeError("Invalid CPU statistics")

    return sum(values), values[3] + values[4]


def read_cpu():
    total_before, idle_before = cpu_times()

    time.sleep(0.25)

    total_after, idle_after = cpu_times()

    total_delta = total_after - total_before
    idle_delta = idle_after - idle_before

    if total_delta <= 0:
        return 0.0

    usage = (1 - idle_delta / total_delta) * 100

    return round(max(0, min(100, usage)), 1)


def read_memory():
    values = {}

    with open("/proc/meminfo", encoding="utf-8") as file:
        for line in file:
            key, _, value = line.partition(":")

            if key in ("MemTotal", "MemAvailable"):
                values[key] = int(value.strip().split()[0]) * 1024

    total = values["MemTotal"]
    available = values["MemAvailable"]

    if total <= 0:
        raise RuntimeError("Invalid memory size")

    used = max(0, total - available)

    return total, used, round(used / total * 100, 1)


def read_disk():
    info = os.statvfs("/")

    total = info.f_blocks * info.f_frsize
    free = info.f_bavail * info.f_frsize

    if total <= 0:
        raise RuntimeError("Invalid disk size")

    used = max(0, total - free)

    return total, used, round(used / total * 100, 1)


def read_uptime():
    with open("/proc/uptime", encoding="utf-8") as file:
        return max(0, int(float(file.read().split()[0])))


def read_service(key, name, unit, uptime):
    status = "unknown"
    service_uptime = None

    try:
        result = subprocess.run(
            [
                "/usr/bin/systemctl",
                "show",
                unit,
                "--property=LoadState",
                "--property=ActiveState",
                "--property=ActiveEnterTimestampMonotonic",
                "--no-pager",
            ],
            capture_output=True,
            text=True,
            timeout=2,
            check=False,
        )

        if result.returncode == 0:
            properties = {}

            for line in result.stdout.splitlines():
                if "=" in line:
                    property_name, value = line.split("=", 1)
                    properties[property_name] = value

            if properties.get("LoadState") == "loaded":
                active_state = properties.get("ActiveState")

                if active_state == "active":
                    status = "active"

                    started = int(
                        properties.get(
                            "ActiveEnterTimestampMonotonic",
                            "0",
                        )
                    )

                    if started > 0:
                        service_uptime = max(
                            0,
                            int(uptime - started / 1_000_000),
                        )

                elif active_state in ("inactive", "failed"):
                    status = "down"

    except (
        OSError,
        ValueError,
        subprocess.TimeoutExpired,
    ):
        status = "unknown"

    return {
        "key": key,
        "name": name,
        "status": status,
        "uptime_seconds": service_uptime,
    }


def collect():
    cpu = read_cpu()

    memory_total, memory_used, memory_percent = read_memory()

    disk_total, disk_used, disk_percent = read_disk()

    uptime = read_uptime()

    services = [
        read_service(key, name, unit, uptime)
        for key, (name, unit) in SYSTEMD_SERVICES.items()
    ]

    timestamp = datetime.now(
        timezone.utc
    ).isoformat(timespec="seconds")

    return {
        "schema_version": 1,

        "health": {
            "cpu_percent": cpu,
            "memory_total_bytes": memory_total,
            "memory_used_bytes": memory_used,
            "memory_percent": memory_percent,
            "disk_total_bytes": disk_total,
            "disk_used_bytes": disk_used,
            "disk_percent": disk_percent,
            "uptime_seconds": uptime,
            "measured_at": timestamp,
        },

        "services": {
            "services": services,
            "checked_at": timestamp,
        },
    }


def main():
    original_command = os.environ.get(
        "SSH_ORIGINAL_COMMAND"
    )

    if original_command not in (None, "snapshot"):
        print(
            "Unauthorized monitoring command.",
            file=sys.stderr,
        )
        return 1

    try:
        payload = collect()

        print(json.dumps(
            payload,
            separators=(",", ":"),
        ))

        return 0

    except Exception:
        print(
            "Unable to collect monitoring snapshot.",
            file=sys.stderr,
        )
        return 1


if __name__ == "__main__":
    sys.exit(main())

import importlib.util
import io
import json
import os
import unittest

from contextlib import ExitStack, redirect_stdout, redirect_stderr
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch


SCRIPT = Path(__file__).with_name("monitoring-snapshot.py")

spec = importlib.util.spec_from_file_location(
    "monitoring_snapshot",
    SCRIPT,
)

monitor = importlib.util.module_from_spec(spec)
spec.loader.exec_module(monitor)


class MonitoringSnapshotTest(unittest.TestCase):

    def test_collect_produces_valid_snapshot(self):
        with ExitStack() as stack:
            stack.enter_context(
                patch.object(monitor, "read_cpu", return_value=25.5)
            )
            stack.enter_context(
                patch.object(
                    monitor,
                    "read_memory",
                    return_value=(8000000000, 4000000000, 50.0),
                )
            )
            stack.enter_context(
                patch.object(
                    monitor,
                    "read_disk",
                    return_value=(100000000000, 40000000000, 40.0),
                )
            )
            stack.enter_context(
                patch.object(
                    monitor,
                    "read_uptime",
                    return_value=86400,
                )
            )
            stack.enter_context(
                patch.object(
                    monitor,
                    "read_service",
                    side_effect=lambda key, name, unit, uptime: {
                        "key": key,
                        "name": name,
                        "status": "active",
                        "uptime_seconds": 3600,
                    },
                )
            )

            payload = monitor.collect()

        self.assertEqual(payload["schema_version"], 1)
        self.assertEqual(payload["health"]["cpu_percent"], 25.5)
        self.assertEqual(payload["health"]["memory_percent"], 50.0)
        self.assertEqual(payload["health"]["disk_percent"], 40.0)
        self.assertEqual(payload["health"]["uptime_seconds"], 86400)
        self.assertEqual(len(payload["services"]["services"]), 5)

        self.assertIn("measured_at", payload["health"])
        self.assertIn("checked_at", payload["services"])

        self.assertEqual(
            payload["health"]["measured_at"],
            payload["services"]["checked_at"],
        )

        json.dumps(payload)

    def test_cpu_usage_calculation(self):
        with patch.object(
            monitor,
            "cpu_times",
            side_effect=[
                (1000, 400),
                (1100, 440),
            ],
        ), patch.object(monitor.time, "sleep"):
            result = monitor.read_cpu()

        self.assertEqual(result, 60.0)

    def test_active_service_uptime(self):
        fake_result = SimpleNamespace(
            returncode=0,
            stdout=(
                "LoadState=loaded\n"
                "ActiveState=active\n"
                "ActiveEnterTimestampMonotonic=4000000\n"
            ),
        )

        with patch.object(
            monitor.subprocess,
            "run",
            return_value=fake_result,
        ):
            result = monitor.read_service(
                "nginx",
                "Nginx",
                "nginx.service",
                10,
            )

        self.assertEqual(result["status"], "active")
        self.assertEqual(result["uptime_seconds"], 6)

    def test_missing_service_returns_unknown(self):
        fake_result = SimpleNamespace(
            returncode=0,
            stdout="LoadState=not-found\nActiveState=inactive\n",
        )

        with patch.object(
            monitor.subprocess,
            "run",
            return_value=fake_result,
        ):
            result = monitor.read_service(
                "unknown",
                "Unknown Service",
                "unknown.service",
                100,
            )

        self.assertEqual(result["status"], "unknown")
        self.assertIsNone(result["uptime_seconds"])

    def test_unauthorized_ssh_command_is_rejected(self):
        with patch.dict(
            os.environ,
            {"SSH_ORIGINAL_COMMAND": "uname -a"},
        ), patch.object(monitor, "collect") as collect, redirect_stderr(
            io.StringIO()
        ):
            exit_code = monitor.main()

        self.assertEqual(exit_code, 1)
        collect.assert_not_called()

    def test_snapshot_command_returns_json(self):
        output = io.StringIO()

        with patch.dict(
            os.environ,
            {"SSH_ORIGINAL_COMMAND": "snapshot"},
        ), patch.object(
            monitor,
            "collect",
            return_value={"schema_version": 1},
        ), redirect_stdout(output):
            exit_code = monitor.main()

        self.assertEqual(exit_code, 0)
        self.assertEqual(
            json.loads(output.getvalue())["schema_version"],
            1,
        )


if __name__ == "__main__":
    unittest.main()

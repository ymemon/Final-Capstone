"""Read-only production inspection for the EverythingIT homepage.

Credentials remain outside the repository. The local credential source can be
overridden with EIT_CREDENTIAL_SOURCE.
"""

import ast
import os
import re
from pathlib import Path

import paramiko


HOST = "198185.eu13.ssh.myftpupload.com"
USER = "client_c47ef96dfe_198185"
ROOT = "/home/client_c47ef96dfe_198185/html"
CREDENTIAL_SOURCE = Path(
    os.environ.get(
        "EIT_CREDENTIAL_SOURCE",
        r"C:\Users\yasir\Downloads\Other\eit_case_studies_rebuild.py",
    )
)
OUTPUT = Path(__file__).resolve().parents[1] / "tmp" / "homepage-elementor.json"


def load_password() -> str:
    text = CREDENTIAL_SOURCE.read_text(encoding="utf-8-sig")
    match = re.search(r"^PASSWORD\s*=\s*(.+)$", text, re.MULTILINE)
    if not match:
        raise RuntimeError("Password assignment not found in external credential source")
    value = ast.literal_eval(match.group(1).strip())
    if not isinstance(value, str) or not value:
        raise RuntimeError("External password value is empty")
    return value


def main() -> None:
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=load_password(), timeout=30)
    commands = [
        f"wp --path={ROOT} option get page_on_front",
        f"wp --path={ROOT} plugin list --status=active --fields=name,status,version --format=csv",
        f"wp --path={ROOT} option list --search='*review*' --fields=option_name,autoload --format=csv",
        f"wp --path={ROOT} post meta get 146 _elementor_data > /tmp/eit-homepage-elementor.json",
    ]
    for command in commands:
        _, stdout, stderr = ssh.exec_command(command, timeout=120)
        output = stdout.read().decode("utf-8", "replace")
        error = stderr.read().decode("utf-8", "replace")
        print(f"\n$ {command}\n{output}{error}", flush=True)
        if stdout.channel.recv_exit_status() != 0:
            raise RuntimeError(f"Remote command failed: {command}")
    sftp = ssh.open_sftp()
    sftp.get("/tmp/eit-homepage-elementor.json", str(OUTPUT))
    sftp.remove("/tmp/eit-homepage-elementor.json")
    sftp.close()
    ssh.close()
    print(f"Downloaded Elementor data to {OUTPUT}")


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""
AZ Web Corp — Website Security + SSL Page Deployer
==================================================

Creates and deploys two upscale, SEO-friendly product pages while preserving
the site's existing header and footer:

    https://azwebcorp.com/website-security/
    https://azwebcorp.com/ssl-certificates/

The checkout forms use the exact product IDs currently published by AZ Web
Corp's GoDaddy reseller storefront (pl_id 550793). Each button POSTs directly
to GoDaddy's secure cart API and is then redirected to checkout.

The deployment also repairs the common routing error where a WordPress menu
item labelled "Website Security" points to the SSL page.

Safety
------
- Inspection-only by default. No changes without --apply.
- SSH password is requested securely and is never stored in this file.
- Existing integration file is backed up locally and remotely.
- Existing WordPress page content is not deleted or overwritten.
- The integration renders through a must-use plugin and can be removed by
  restoring/deleting that single file.
- Live verification checks both routes, page markers, final URLs, and every
  reseller product ID after deployment.

Usage on Windows
----------------
Inspect only:
    py azwebcorp_security_ssl_deployer.py --wp-root html

Deploy:
    py azwebcorp_security_ssl_deployer.py --apply --wp-root html

Deploy and accept the already-known host key for this run:
    py azwebcorp_security_ssl_deployer.py --apply --wp-root html --accept-new-host-key

Optional password environment variable (avoids the prompt):
    set AZWEBCORP_SSH_PASSWORD=your-password
    py azwebcorp_security_ssl_deployer.py --apply --wp-root html
"""

from __future__ import annotations

import argparse
import base64
import getpass
import hashlib
import importlib
import json
import os
import posixpath
import re
import stat
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import webbrowser
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional


VERSION = "1.0.3"
DEFAULT_HOST = "644762.us8.ssh.myftpupload.com"
DEFAULT_USER = "client_9d34da8b_644762"
DEFAULT_PORT = 22
DEFAULT_SITE_URL = "https://azwebcorp.com"
DEFAULT_WP_ROOT = None
MU_PLUGIN_FILENAME = "azwebcorp-security-pages.php"
REPORT_PREFIX = "azwebcorp-security-pages-report"

PAGE_SPECS = {
    "website-security": {
        "title": "Website Security",
        "marker": "AZWC_SECURITY_SUITE:website-security",
        "product_ids": [
            "website-security-standard",
            "website-security-advanced",
            "website-security-premium",
        ],
    },
    "ssl-certificates": {
        "title": "SSL Certificates",
        "marker": "AZWC_SECURITY_SUITE:ssl",
        "product_ids": [
            "ssl-standard",
            "dlxssl-dvSingleDomain",
            "ssl-standard-ucc",
            "dlxssl-dvMultiDomain5",
            "ssl-standard-wildcard",
            "dlxssl-wildcard",
        ],
    },
}


class DeploymentError(RuntimeError):
    """Controlled deployment failure with a user-readable message."""


def info(message: str) -> None:
    print(f"[INFO] {message}")


def ok(message: str) -> None:
    print(f"[OK]   {message}")


def warn(message: str) -> None:
    print(f"[WARN] {message}")


def fail(message: str) -> None:
    print(f"[FAIL] {message}", file=sys.stderr)


def utc_timestamp() -> str:
    return datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")


def ensure_dependency(module_name: str, pip_spec: str):
    try:
        return importlib.import_module(module_name)
    except ImportError:
        info(f"Installing required Python package: {pip_spec}")
        command = [
            sys.executable,
            "-m",
            "pip",
            "install",
            "--disable-pip-version-check",
            pip_spec,
        ]
        result = subprocess.run(command, text=True)
        if result.returncode != 0:
            raise DeploymentError(
                f"Could not install {pip_spec}. Run this command manually:\n"
                f'  "{sys.executable}" -m pip install {pip_spec}'
            )
        return importlib.import_module(module_name)


def shell_quote(value: str) -> str:
    return "'" + value.replace("'", "'\"'\"'") + "'"


@dataclass
class RouteCheck:
    slug: str
    requested_url: str
    final_url: Optional[str]
    http_status: Optional[int]
    marker_found: bool
    product_ids_found: list[str]
    product_ids_missing: list[str]
    route_correct: bool
    checkout_action_found: bool
    error: Optional[str]


@dataclass
class InspectionReport:
    generated_utc: str
    mode: str
    host: str
    username: str
    sftp_home: str
    wordpress_root: str
    wp_cli_available: bool
    mu_plugin_installed: bool
    mu_plugin_version: Optional[str]
    pages: dict[str, dict]
    live_routes: list[dict]
    notes: list[str]


class InteractiveHostKeyPolicy:
    """Ask before trusting a previously unknown SSH host key."""

    def __init__(self, paramiko_module, accept_new: bool = False):
        self.paramiko = paramiko_module
        self.accept_new = accept_new

    def missing_host_key(self, client, hostname, key):
        digest = hashlib.sha256(key.asbytes()).digest()
        fingerprint = base64.b64encode(digest).decode("ascii").rstrip("=")
        print()
        warn(f"Unknown SSH host key for {hostname}")
        print(f"       Type: {key.get_name()}")
        print(f"       SHA256:{fingerprint}")

        trusted = self.accept_new
        if not trusted:
            answer = input("Trust this host key for this run? [y/N]: ").strip().lower()
            trusted = answer in {"y", "yes"}
        if not trusted:
            raise DeploymentError("SSH host key was not trusted.")

        client._host_keys.add(hostname, key.get_name(), key)  # noqa: SLF001
        ok("SSH host key accepted for this run.")


class RemoteWordPress:
    def __init__(
        self,
        paramiko_module,
        host: str,
        username: str,
        password: str,
        port: int,
        accept_new_host_key: bool,
    ):
        self.host = host
        self.username = username
        self.port = port
        self.client = None
        self.sftp = None

        client = paramiko_module.SSHClient()
        client.load_system_host_keys()
        client.set_missing_host_key_policy(
            InteractiveHostKeyPolicy(paramiko_module, accept_new_host_key)
        )

        info(f"Connecting to {host}:{port} as {username}...")
        try:
            client.connect(
                hostname=host,
                port=port,
                username=username,
                password=password,
                timeout=25,
                banner_timeout=25,
                auth_timeout=25,
                look_for_keys=False,
                allow_agent=False,
            )
        except Exception as exc:
            raise DeploymentError(f"SSH connection failed: {exc}") from exc

        try:
            sftp = client.open_sftp()
        except Exception as exc:
            client.close()
            raise DeploymentError(f"SFTP session could not be opened: {exc}") from exc

        self.client = client
        self.sftp = sftp
        ok("SSH/SFTP connection established.")

    def close(self) -> None:
        try:
            if self.sftp:
                self.sftp.close()
        finally:
            if self.client:
                self.client.close()

    def exec(self, command: str, timeout: int = 90) -> tuple[int, str, str]:
        if not self.client:
            raise DeploymentError("SSH client is not connected.")
        try:
            stdin, stdout, stderr = self.client.exec_command(command, timeout=timeout)
            stdin.close()
            out = stdout.read().decode("utf-8", errors="replace")
            err = stderr.read().decode("utf-8", errors="replace")
            code = stdout.channel.recv_exit_status()
            return code, out.strip(), err.strip()
        except Exception as exc:
            return 255, "", str(exc)

    def normalize(self, path: str) -> str:
        return self.sftp.normalize(path)

    def exists(self, path: str) -> bool:
        try:
            self.sftp.lstat(path)
            return True
        except OSError:
            return False

    def is_dir(self, path: str) -> bool:
        try:
            return stat.S_ISDIR(self.sftp.lstat(path).st_mode)
        except OSError:
            return False

    def mkdir_p(self, path: str) -> None:
        path = posixpath.normpath(path)
        if path in {"", ".", "/"}:
            return
        parts = path.split("/")
        current = "/" if path.startswith("/") else ""
        for part in parts:
            if not part:
                continue
            current = posixpath.join(current, part) if current else part
            if not self.exists(current):
                self.sftp.mkdir(current)

    def read_bytes(self, path: str, max_bytes: int = 8_000_000) -> bytes:
        with self.sftp.open(path, "rb") as handle:
            return handle.read(max_bytes)

    def read_text(self, path: str, max_bytes: int = 8_000_000) -> str:
        return self.read_bytes(path, max_bytes).decode("utf-8", errors="replace")

    def put_bytes_atomic(self, data: bytes, remote_path: str) -> None:
        parent = posixpath.dirname(remote_path)
        self.mkdir_p(parent)
        temporary = f"{remote_path}.azwc-upload-{utc_timestamp()}"
        with self.sftp.open(temporary, "wb") as handle:
            handle.write(data)
            handle.flush()
        if self.exists(remote_path):
            self.sftp.remove(remote_path)
        self.sftp.rename(temporary, remote_path)

    def listdir_safe(self, path: str):
        try:
            return self.sftp.listdir_attr(path)
        except OSError:
            return []

    def find_wordpress_roots(self, start: str, max_depth: int = 5) -> list[str]:
        ignored = {
            ".git", ".cache", "cache", "logs", "log", "tmp", "temp",
            "node_modules", "vendor", "uploads", "backups", "backup",
        }
        roots: list[str] = []
        visited: set[str] = set()

        def walk(path: str, depth: int) -> None:
            normalized = posixpath.normpath(path)
            if normalized in visited or depth > max_depth:
                return
            visited.add(normalized)
            entries = self.listdir_safe(normalized)
            names = {entry.filename for entry in entries}
            required = {"wp-admin", "wp-includes", "wp-content", "wp-config.php"}
            if required.issubset(names):
                roots.append(normalized)
                return
            for entry in entries:
                if not stat.S_ISDIR(entry.st_mode):
                    continue
                if entry.filename.lower() in ignored:
                    continue
                walk(posixpath.join(normalized, entry.filename), depth + 1)

        walk(start, 0)
        return roots


def detect_wp_root(remote: RemoteWordPress, supplied_root: Optional[str]) -> str:
    def valid(candidate: str) -> bool:
        required = [
            posixpath.join(candidate, "wp-config.php"),
            posixpath.join(candidate, "wp-admin"),
            posixpath.join(candidate, "wp-includes"),
            posixpath.join(candidate, "wp-content"),
        ]
        return all(remote.exists(path) for path in required)

    if supplied_root:
        candidate = remote.normalize(supplied_root)
        if not valid(candidate):
            raise DeploymentError(f"The supplied WordPress root is invalid: {candidate}")
        ok(f"WordPress root confirmed: {candidate}")
        return candidate

    home = remote.normalize(".")
    info(f"SFTP home: {home}")
    info("Searching for the WordPress root...")
    likely = [
        home,
        posixpath.join(home, "html"),
        posixpath.join(home, "public_html"),
        posixpath.join(home, "htdocs"),
        posixpath.join(home, "web"),
    ]
    for candidate in likely:
        if valid(candidate):
            ok(f"WordPress root discovered: {candidate}")
            return candidate

    roots = sorted(
        set(remote.find_wordpress_roots(home, max_depth=5)),
        key=lambda item: (item.count("/"), len(item)),
    )
    if not roots:
        raise DeploymentError(
            "No WordPress installation was found. Run again with --wp-root html "
            "or provide the correct remote path."
        )
    if len(roots) == 1:
        ok(f"WordPress root discovered: {roots[0]}")
        return roots[0]

    warn("Multiple WordPress installations were found:")
    for index, root in enumerate(roots, start=1):
        print(f"  {index}. {root}")
    choice = input("Choose the installation number [1]: ").strip() or "1"
    try:
        selected = roots[int(choice) - 1]
    except (ValueError, IndexError) as exc:
        raise DeploymentError("Invalid WordPress installation selection.") from exc
    ok(f"WordPress root selected: {selected}")
    return selected


def fetch_url(url: str, timeout: int = 60) -> tuple[Optional[int], bytes, str, Optional[str]]:
    request = urllib.request.Request(
        url,
        headers={
            "User-Agent": "Mozilla/5.0 (compatible; AZWebCorpDeploymentVerifier/1.0)",
            "Accept": "text/html,application/xhtml+xml",
            "Cache-Control": "no-cache",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return response.status, response.read(), response.geturl(), None
    except urllib.error.HTTPError as exc:
        return exc.code, exc.read(), exc.geturl(), str(exc)
    except Exception as exc:
        return None, b"", url, str(exc)


def parse_plugin_version(contents: str) -> Optional[str]:
    match = re.search(r"^\s*\*\s*Version:\s*([^\r\n]+)", contents, re.I | re.M)
    return match.group(1).strip() if match else None


def wp_cli_available(remote: RemoteWordPress, wp_root: str) -> bool:
    command = f"cd {shell_quote(wp_root)} && wp --info --allow-root"
    code, _, _ = remote.exec(command, timeout=30)
    if code == 0:
        return True
    command = f"cd {shell_quote(wp_root)} && wp --info"
    code, _, _ = remote.exec(command, timeout=30)
    return code == 0


def run_wp(remote: RemoteWordPress, wp_root: str, arguments: str) -> tuple[int, str, str]:
    base = f"cd {shell_quote(wp_root)} && wp {arguments}"
    code, out, err = remote.exec(base + " --allow-root", timeout=120)
    if code != 0 and "allow-root" in (out + err).lower():
        code, out, err = remote.exec(base, timeout=120)
    return code, out, err


def inspect_pages(remote: RemoteWordPress, wp_root: str, has_wp_cli: bool) -> dict[str, dict]:
    result: dict[str, dict] = {}
    for slug in PAGE_SPECS:
        entry = {"slug": slug, "exists": None, "id": None, "status": None, "url": None}
        if has_wp_cli:
            args = (
                "post list --post_type=page "
                f"--name={shell_quote(slug)} --posts_per_page=1 --format=json"
            )
            code, out, err = run_wp(remote, wp_root, args)
            if code == 0:
                try:
                    rows = json.loads(out or "[]")
                    entry["exists"] = bool(rows)
                    if rows:
                        entry["id"] = rows[0].get("ID")
                        entry["status"] = rows[0].get("post_status")
                        entry["url"] = rows[0].get("url") or rows[0].get("guid")
                except json.JSONDecodeError:
                    entry["error"] = "WP-CLI returned non-JSON page data."
            else:
                entry["error"] = err or out or "WP-CLI page inspection failed."
        result[slug] = entry
    return result


def verify_route(site_url: str, slug: str) -> RouteCheck:
    spec = PAGE_SPECS[slug]
    requested = f"{site_url.rstrip('/')}/{slug}/"
    cache_bust = urllib.parse.urlencode({"azwc_verify": utc_timestamp()})
    status, body, final_url, error = fetch_url(f"{requested}?{cache_bust}")
    text = body.decode("utf-8", errors="replace")
    lower_final_path = urllib.parse.urlparse(final_url).path.rstrip("/").lower()
    expected_path = f"/{slug}"
    found = [pid for pid in spec["product_ids"] if pid in text]
    missing = [pid for pid in spec["product_ids"] if pid not in text]
    return RouteCheck(
        slug=slug,
        requested_url=requested,
        final_url=final_url,
        http_status=status,
        marker_found=spec["marker"] in text,
        product_ids_found=found,
        product_ids_missing=missing,
        route_correct=lower_final_path == expected_path,
        checkout_action_found=(
            "https://www.secureserver.net/api/v1/cart/550793" in text
        ),
        error=error,
    )


def inspect_installation(
    remote: RemoteWordPress,
    wp_root: str,
    site_url: str,
    mode: str,
) -> InspectionReport:
    mu_path = posixpath.join(wp_root, "wp-content", "mu-plugins", MU_PLUGIN_FILENAME)
    installed = remote.exists(mu_path)
    version = None
    if installed:
        version = parse_plugin_version(remote.read_text(mu_path))
    has_wp = wp_cli_available(remote, wp_root)
    pages = inspect_pages(remote, wp_root, has_wp)
    route_checks = [asdict(verify_route(site_url, slug)) for slug in PAGE_SPECS]
    notes: list[str] = []
    if not has_wp:
        notes.append("WP-CLI was not available; page creation will be handled by the MU plugin.")
    if any(not row["route_correct"] for row in route_checks):
        notes.append("At least one current live route does not resolve to its intended slug.")
    return InspectionReport(
        generated_utc=datetime.now(timezone.utc).isoformat(),
        mode=mode,
        host=remote.host,
        username=remote.username,
        sftp_home=remote.normalize("."),
        wordpress_root=wp_root,
        wp_cli_available=has_wp,
        mu_plugin_installed=installed,
        mu_plugin_version=version,
        pages=pages,
        live_routes=route_checks,
        notes=notes,
    )


def save_report(report: InspectionReport, output_dir: Path, suffix: str) -> Path:
    output_dir.mkdir(parents=True, exist_ok=True)
    path = output_dir / f"{REPORT_PREFIX}-{suffix}-{utc_timestamp()}.json"
    path.write_text(json.dumps(asdict(report), indent=2), encoding="utf-8")
    return path


def print_report(report: InspectionReport) -> None:
    print()
    print("=" * 78)
    print("INSPECTION")
    print("=" * 78)
    print(f"WordPress root:       {report.wordpress_root}")
    print(f"WP-CLI available:     {report.wp_cli_available}")
    print(f"Integration present:  {report.mu_plugin_installed}")
    print(f"Integration version:  {report.mu_plugin_version or 'not installed'}")
    for route in report.live_routes:
        print(
            f"/{route['slug']}/: HTTP {route['http_status']} | "
            f"route={'OK' if route['route_correct'] else 'WRONG'} | "
            f"marker={'YES' if route['marker_found'] else 'NO'}"
        )
    print("=" * 78)


def backup_existing(
    remote: RemoteWordPress,
    wp_root: str,
    output_dir: Path,
    deployment_id: str,
    has_wp_cli: bool,
) -> tuple[Path, str]:
    local_dir = output_dir / "backups" / deployment_id
    local_dir.mkdir(parents=True, exist_ok=True)
    remote_dir = posixpath.join(
        wp_root, "wp-content", "azwc-backups", f"security-pages-{deployment_id}"
    )
    remote.mkdir_p(remote_dir)

    mu_path = posixpath.join(wp_root, "wp-content", "mu-plugins", MU_PLUGIN_FILENAME)
    if remote.exists(mu_path):
        data = remote.read_bytes(mu_path)
        (local_dir / MU_PLUGIN_FILENAME).write_bytes(data)
        remote.put_bytes_atomic(data, posixpath.join(remote_dir, MU_PLUGIN_FILENAME))

    if has_wp_cli:
        page_backup: dict[str, dict] = {}
        for slug in PAGE_SPECS:
            code, out, _ = run_wp(
                remote,
                wp_root,
                "post list --post_type=page "
                f"--name={shell_quote(slug)} --posts_per_page=1 --field=ID",
            )
            page_id = out.strip().splitlines()[0] if code == 0 and out.strip() else None
            if not page_id:
                page_backup[slug] = {"existed": False}
                continue
            post_code, post_json, post_err = run_wp(
                remote, wp_root, f"post get {shell_quote(page_id)} --format=json"
            )
            meta_code, meta_json, meta_err = run_wp(
                remote, wp_root, f"post meta list {shell_quote(page_id)} --format=json"
            )
            page_backup[slug] = {
                "existed": True,
                "id": page_id,
                "post": json.loads(post_json) if post_code == 0 else {"error": post_err},
                "meta": json.loads(meta_json) if meta_code == 0 else {"error": meta_err},
            }
        backup_json = json.dumps(page_backup, indent=2).encode("utf-8")
        (local_dir / "wordpress-pages.json").write_bytes(backup_json)
        remote.put_bytes_atomic(backup_json, posixpath.join(remote_dir, "wordpress-pages.json"))

    manifest = {
        "deployment_id": deployment_id,
        "generated_utc": datetime.now(timezone.utc).isoformat(),
        "wordpress_root": wp_root,
        "integration_path": mu_path,
        "remote_backup": remote_dir,
    }
    (local_dir / "manifest.json").write_text(
        json.dumps(manifest, indent=2), encoding="utf-8"
    )
    return local_dir, remote_dir


def deploy_mu_plugin(remote: RemoteWordPress, wp_root: str, php: str) -> str:
    mu_dir = posixpath.join(wp_root, "wp-content", "mu-plugins")
    remote.mkdir_p(mu_dir)
    remote_path = posixpath.join(mu_dir, MU_PLUGIN_FILENAME)
    remote.put_bytes_atomic(php.encode("utf-8"), remote_path)
    uploaded = remote.read_text(remote_path)
    if hashlib.sha256(uploaded.encode("utf-8")).digest() != hashlib.sha256(
        php.encode("utf-8")
    ).digest():
        raise DeploymentError("Uploaded integration did not match the local build.")
    ok(f"Integration deployed: {remote_path}")
    return remote_path


def build_mu_plugin() -> str:
    return MU_PLUGIN_TEMPLATE.replace("__AZWC_VERSION__", VERSION)


MU_PLUGIN_TEMPLATE = r'''<?php
/**
 * Plugin Name: AZ Web Corp Security Product Pages
 * Description: Premium Website Security and SSL pages with verified GoDaddy reseller checkout routing.
 * Version: __AZWC_VERSION__
 * Author: AZ Web Corp
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AZWC_SS_VERSION', '__AZWC_VERSION__' );
define( 'AZWC_SS_CART_ACTION', 'https://www.secureserver.net/api/v1/cart/550793?itc=slp_rstdstore&redirect=true' );

function azwc_ss_specs() {
    return array(
        'website-security' => array(
            'title'       => 'Website Security',
            'seo_title'   => 'Website Security, Malware Protection & Backup | AZ Web Corp',
            'description' => 'Protect your website with a firewall, malware scanning, cleanup, DDoS defense, CDN and secure backups. Plans from $4.99 per month per site.',
        ),
        'ssl-certificates' => array(
            'title'       => 'SSL Certificates',
            'seo_title'   => 'SSL Certificates for Websites & Domains | AZ Web Corp',
            'description' => 'Compare DV SSL certificates for one site, five sites or unlimited subdomains, with manual or automated installation and secure reseller checkout.',
        ),
    );
}

function azwc_ss_normalize_path( $url = null ) {
    if ( null === $url ) {
        $url = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
    }
    $path = wp_parse_url( $url, PHP_URL_PATH );
    return '/' . trim( strtolower( (string) $path ), '/' ) . '/';
}

function azwc_ss_requested_type() {
    $path = azwc_ss_normalize_path();
    if ( '/website-security/' === $path ) {
        return 'website-security';
    }
    if ( '/ssl-certificates/' === $path ) {
        return 'ssl-certificates';
    }
    return '';
}

function azwc_ss_current_type() {
    $requested = azwc_ss_requested_type();
    if ( $requested ) {
        return $requested;
    }
    $ids = get_option( 'azwc_ss_page_ids', array() );
    $current_id = get_queried_object_id();
    foreach ( $ids as $type => $page_id ) {
        if ( (int) $page_id === (int) $current_id ) {
            return $type;
        }
    }
    return '';
}

function azwc_ss_install_pages() {
    if ( AZWC_SS_VERSION === get_option( 'azwc_ss_installed_version' ) ) {
        return;
    }

    $specs = azwc_ss_specs();
    $ids   = array();
    foreach ( $specs as $slug => $spec ) {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( ! $page ) {
            $page_id = wp_insert_post(
                array(
                    'post_title'   => $spec['title'],
                    'post_name'    => $slug,
                    'post_type'    => 'page',
                    'post_status'  => 'publish',
                    'post_content' => '[azwc_security_page type="' . esc_attr( $slug ) . '"]',
                ),
                true
            );
            if ( is_wp_error( $page_id ) ) {
                continue;
            }
        } else {
            $page_id = (int) $page->ID;
            if ( 'trash' === $page->post_status ) {
                wp_untrash_post( $page_id );
            }
            if ( 'publish' !== get_post_status( $page_id ) ) {
                wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish' ) );
            }
        }

        // Elementor Full Width keeps the existing website header and footer
        // while avoiding the theme's automatic page-title block.
        update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );
        $ids[ $slug ] = (int) $page_id;
    }

    update_option( 'azwc_ss_page_ids', $ids, false );
    if ( count( $ids ) === count( $specs ) ) {
        update_option( 'azwc_ss_installed_version', AZWC_SS_VERSION, false );
        flush_rewrite_rules( false );
    }
}
add_action( 'init', 'azwc_ss_install_pages', 3 );

function azwc_ss_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'type' => '' ), $atts, 'azwc_security_page' );
    $type = sanitize_key( $atts['type'] );
    if ( ! in_array( $type, array( 'website-security', 'ssl-certificates' ), true ) ) {
        return '';
    }
    return azwc_ss_render_page( $type );
}
add_shortcode( 'azwc_security_page', 'azwc_ss_shortcode' );

function azwc_ss_replace_content( $content ) {
    if ( is_admin() || ! is_main_query() || ! in_the_loop() ) {
        return $content;
    }
    $type = azwc_ss_current_type();
    return $type ? azwc_ss_render_page( $type ) : $content;
}
add_filter( 'the_content', 'azwc_ss_replace_content', 999 );

function azwc_ss_disable_wrong_canonical_redirect( $redirect_url, $requested_url ) {
    $path = azwc_ss_normalize_path( $requested_url );
    if ( in_array( $path, array( '/website-security/', '/ssl-certificates/' ), true ) ) {
        return false;
    }
    return $redirect_url;
}
add_filter( 'redirect_canonical', 'azwc_ss_disable_wrong_canonical_redirect', 10, 2 );

/**
 * The August 2026 audit published /website-security-firewall/ as a bridge page.
 * This suite supersedes it at the shorter slug, and leaving both live would put
 * two pages in front of one intent - which is the cannibalisation the audit was
 * cleaning up. The old URL forwards rather than competing.
 *
 * /ssl-certificates/ needs no entry: this suite takes over that existing page in
 * place, so the URL and its history are unchanged.
 */
function azwc_ss_retire_superseded() {
    $map = array(
        '/website-security-firewall/' => '/website-security/',
    );
    $path = azwc_ss_normalize_path();
    if ( isset( $map[ $path ] ) ) {
        wp_redirect( home_url( $map[ $path ] ), 301 );
        exit;
    }
}
add_action( 'template_redirect', 'azwc_ss_retire_superseded', 1 );

function azwc_ss_fix_menu_routes( $items ) {
    foreach ( $items as $item ) {
        $label = strtolower( trim( wp_strip_all_tags( $item->title ) ) );
        if ( in_array( $label, array( 'website security', 'security' ), true ) ) {
            $item->url = home_url( '/website-security/' );
        } elseif ( in_array( $label, array( 'ssl', 'ssl certificates' ), true ) ) {
            $item->url = home_url( '/ssl-certificates/' );
        }
    }
    return $items;
}
add_filter( 'wp_nav_menu_objects', 'azwc_ss_fix_menu_routes', 999 );

function azwc_ss_document_title( $parts ) {
    $type = azwc_ss_current_type();
    if ( ! $type ) {
        return $parts;
    }
    $specs = azwc_ss_specs();
    $parts['title'] = $specs[ $type ]['seo_title'];
    unset( $parts['site'] );
    unset( $parts['tagline'] );
    return $parts;
}
add_filter( 'document_title_parts', 'azwc_ss_document_title', 999 );

function azwc_ss_wpseo_title( $title ) {
    $type = azwc_ss_current_type();
    if ( ! $type ) {
        return $title;
    }
    $specs = azwc_ss_specs();
    return $specs[ $type ]['seo_title'];
}
add_filter( 'wpseo_title', 'azwc_ss_wpseo_title', 999 );
add_filter( 'rank_math/frontend/title', 'azwc_ss_wpseo_title', 999 );

function azwc_ss_meta_description( $description ) {
    $type = azwc_ss_current_type();
    if ( ! $type ) {
        return $description;
    }
    $specs = azwc_ss_specs();
    return $specs[ $type ]['description'];
}
add_filter( 'wpseo_metadesc', 'azwc_ss_meta_description', 999 );
add_filter( 'rank_math/frontend/description', 'azwc_ss_meta_description', 999 );

function azwc_ss_canonical( $canonical ) {
    $type = azwc_ss_current_type();
    return $type ? home_url( '/' . $type . '/' ) : $canonical;
}
add_filter( 'wpseo_canonical', 'azwc_ss_canonical', 999 );
add_filter( 'rank_math/frontend/canonical', 'azwc_ss_canonical', 999 );

function azwc_ss_body_class( $classes ) {
    $type = azwc_ss_current_type();
    if ( $type ) {
        $classes[] = 'azwc-security-suite-page';
        $classes[] = 'azwc-security-suite-' . $type;
    }
    return $classes;
}
add_filter( 'body_class', 'azwc_ss_body_class' );

function azwc_ss_head() {
    $type = azwc_ss_current_type();
    if ( ! $type ) {
        return;
    }
    $specs = azwc_ss_specs();
    $spec  = $specs[ $type ];

    if ( ! defined( 'WPSEO_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) ) {
        echo '<meta name="description" content="' . esc_attr( $spec['description'] ) . '">' . "\n";
        echo '<link rel="canonical" href="' . esc_url( home_url( '/' . $type . '/' ) ) . '">' . "\n";
    }

    $schema = azwc_ss_schema( $type );
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";

    // Fallback for hard-coded Elementor/custom links that bypass wp_nav_menu.
    ?>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      document.querySelectorAll('a').forEach(function(link){
        var label=(link.textContent||'').trim().toLowerCase();
        if(label==='website security'||label==='security'){
          try{
            var url=new URL(link.href,window.location.origin);
            if(url.origin===window.location.origin&&/\/ssl\/?$/i.test(url.pathname)){
              link.href='<?php echo esc_js( home_url( '/website-security/' ) ); ?>';
            }
          }catch(e){}
        }
      });
    });
    </script>
    <?php
}
add_action( 'wp_head', 'azwc_ss_head', 2 );

function azwc_ss_schema( $type ) {
    $url = home_url( '/' . $type . '/' );
    if ( 'website-security' === $type ) {
        $name = 'Website Security Plans';
        $description = 'Website firewall, malware scanning and cleanup, DDoS defense, CDN and secure website backup plans.';
        $offers = array(
            array( 'name' => 'Standard Website Security', 'price' => '4.99', 'priceCurrency' => 'USD' ),
            array( 'name' => 'Advanced Website Security', 'price' => '14.99', 'priceCurrency' => 'USD' ),
            array( 'name' => 'Premium Website Security', 'price' => '22.99', 'priceCurrency' => 'USD' ),
        );
        $faqs = azwc_ss_security_faqs();
    } else {
        $name = 'DV SSL Certificate Plans';
        $description = 'Domain-validated SSL certificates for one site, five sites or unlimited subdomains, with manual and automated options.';
        $offers = array(
            array( 'name' => 'Basic DV SSL — 1 Site', 'price' => '31.99', 'priceCurrency' => 'USD' ),
            array( 'name' => 'Automated DV SSL — 1 Domain', 'price' => '103.99', 'priceCurrency' => 'USD' ),
            array( 'name' => 'Basic DV SSL — 5 Sites', 'price' => '58.99', 'priceCurrency' => 'USD' ),
            array( 'name' => 'Automated DV SSL — 5 Domains', 'price' => '233.99', 'priceCurrency' => 'USD' ),
            array( 'name' => 'Basic DV SSL — Wildcard', 'price' => '204.99', 'priceCurrency' => 'USD' ),
            array( 'name' => 'Automated DV SSL — Wildcard', 'price' => '350.99', 'priceCurrency' => 'USD' ),
        );
        $faqs = azwc_ss_ssl_faqs();
    }

    $offer_nodes = array();
    foreach ( $offers as $offer ) {
        $offer_nodes[] = array(
            '@type'         => 'Offer',
            'name'          => $offer['name'],
            'price'         => $offer['price'],
            'priceCurrency' => $offer['priceCurrency'],
            'availability'  => 'https://schema.org/InStock',
            'url'           => $url,
        );
    }

    $faq_nodes = array();
    foreach ( $faqs as $faq ) {
        $faq_nodes[] = array(
            '@type' => 'Question',
            'name'  => $faq[0],
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => $faq[1],
            ),
        );
    }

    return array(
        '@context' => 'https://schema.org',
        '@graph'   => array(
            array(
                '@type'       => 'WebPage',
                '@id'         => $url . '#webpage',
                'url'         => $url,
                'name'        => $name . ' | AZ Web Corp',
                'description' => $description,
                'isPartOf'     => array( '@id' => home_url( '/' ) . '#website' ),
            ),
            array(
                '@type'       => 'Service',
                '@id'         => $url . '#service',
                'name'        => $name,
                'description' => $description,
                'provider'    => array(
                    '@type' => 'Organization',
                    'name'  => 'AZ Web Corp',
                    'url'   => home_url( '/' ),
                ),
                'areaServed'   => 'Worldwide',
                'offers'       => $offer_nodes,
            ),
            array(
                '@type'      => 'FAQPage',
                '@id'        => $url . '#faq',
                'mainEntity' => $faq_nodes,
            ),
        ),
    );
}

function azwc_ss_icon( $name ) {
    $icons = array(
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 20 5v6c0 5-3.3 9.4-8 11-4.7-1.6-8-6-8-11V5l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>',
        'scan'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H4a1 1 0 0 0-1 1v4M16 3h4a1 1 0 0 1 1 1v4M8 21H4a1 1 0 0 1-1-1v-4M16 21h4a1 1 0 0 0 1-1v-4"/><circle cx="12" cy="12" r="4"/><path d="m10.5 12 1 1 2-2"/></svg>',
        'backup' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4z"/><path d="M7 4h10v3M8 12h8M8 16h5"/></svg>',
        'lock'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>',
        'speed'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 18a8 8 0 1 1 16 0M12 18l4-6"/><path d="M6 18h12"/></svg>',
        'globe'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg>',
        'check'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
    );
    return isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['check'];
}

function azwc_ss_checkout_form( $product_id, $plan_name ) {
    $items = wp_json_encode( array( array( 'id' => $product_id, 'quantity' => 1 ) ), JSON_UNESCAPED_SLASHES );
    ob_start();
    ?>
    <form class="azwc-ss-checkout" method="post" action="<?php echo esc_url( AZWC_SS_CART_ACTION ); ?>">
        <input type="hidden" name="items" value="<?php echo esc_attr( $items ); ?>">
        <button type="submit" aria-label="Add <?php echo esc_attr( $plan_name ); ?> to the secure cart">
            <span>Add to Cart</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
    </form>
    <?php
    return ob_get_clean();
}

function azwc_ss_security_plans() {
    return array(
        array(
            'name' => 'Standard',
            'price' => '4.99',
            'product' => 'website-security-standard',
            'summary' => 'The essentials for a single business website.',
            'features' => array(
                'Protection for one website',
                'Web application firewall that helps block malicious traffic',
                'SSL protection included through the firewall',
                'Ongoing malware scanning',
                'One cleanup and remediation each year',
            ),
        ),
        array(
            'name' => 'Advanced',
            'price' => '14.99',
            'product' => 'website-security-advanced',
            'summary' => 'Stronger protection, faster delivery and continuous recovery.',
            'popular' => true,
            'features' => array(
                'Everything in Standard',
                'Unlimited malware cleanups',
                'DDoS protection',
                'Content Delivery Network (CDN) speed boost',
                '25 GB of secure website backup',
            ),
        ),
        array(
            'name' => 'Premium',
            'price' => '22.99',
            'product' => 'website-security-premium',
            'summary' => 'Priority response and deeper backup capacity for critical sites.',
            'features' => array(
                'Everything in Advanced',
                'Prioritized malware cleanup and repair',
                'Unlimited site cleanups',
                'DDoS protection and CDN acceleration',
                '200 GB of secure website backup',
            ),
        ),
    );
}

function azwc_ss_ssl_plans() {
    $basic_common = array(
        'Domain validation',
        'SHA-2 and 2048-bit encryption',
        'Fast issuance—often within minutes after validation',
        'HTTPS and browser security indicators',
        'Security trust seal',
        'Use on unlimited servers',
        'Free unlimited reissues',
        'Self-managed installation, reinstallation and troubleshooting',
        '200-day reissuance cycle with regular encryption refreshes',
    );
    $automated_common = array(
        'Domain validation',
        'SHA-2 and 2048-bit encryption',
        'Guided first-time installation',
        'Automated reinstallation as certificate validity requirements change',
        'One-step installation for eligible hosting customers',
        'Instant domain auto-validation for eligible domains',
        'SSL change monitoring and alerts',
        'Certificate lifecycle dashboard',
        'Use on unlimited servers with free unlimited reissues',
        'Pay per domain—not per certificate',
    );
    return array(
        array(
            'tier' => 'Basic', 'name' => 'DV SSL — 1 Site', 'price' => '31.99',
            'scope' => 'Protect one site', 'product' => 'ssl-standard',
            'summary' => 'Straightforward encryption when you are comfortable handling installation.',
            'features' => $basic_common,
        ),
        array(
            'tier' => 'Deluxe Automated', 'name' => 'DV SSL — 1 Domain', 'price' => '103.99',
            'scope' => 'Protect one domain', 'product' => 'dlxssl-dvSingleDomain',
            'summary' => 'Set it up with guidance, then let automation handle certificate changes.',
            'popular' => true,
            'features' => array_merge( $automated_common, array( 'Unlimited SSL certificates for the domain' ) ),
        ),
        array(
            'tier' => 'Basic', 'name' => 'DV SSL — 5 Sites', 'price' => '58.99',
            'scope' => 'Protect five sites', 'product' => 'ssl-standard-ucc',
            'summary' => 'Manual certificate management for a small portfolio of websites.',
            'features' => $basic_common,
        ),
        array(
            'tier' => 'Deluxe Automated', 'name' => 'DV SSL — 5 Domains', 'price' => '233.99',
            'scope' => 'Protect five domains', 'product' => 'dlxssl-dvMultiDomain5',
            'summary' => 'Automated lifecycle management across as many as five domains.',
            'features' => array_merge( $automated_common, array( 'Unlimited certificates per covered domain' ) ),
        ),
        array(
            'tier' => 'Basic', 'name' => 'DV SSL — Wildcard', 'price' => '204.99',
            'scope' => 'Protect unlimited subdomains', 'product' => 'ssl-standard-wildcard',
            'summary' => 'One manually managed wildcard certificate for expanding subdomains.',
            'features' => $basic_common,
        ),
        array(
            'tier' => 'Deluxe Automated', 'name' => 'DV SSL — Wildcard', 'price' => '350.99',
            'scope' => 'Protect unlimited subdomains', 'product' => 'dlxssl-wildcard',
            'summary' => 'Wildcard coverage with guided setup, monitoring and automated lifecycle care.',
            'features' => array_merge( $automated_common, array( 'Unlimited certificates per subdomain' ) ),
        ),
    );
}

function azwc_ss_security_faqs() {
    return array(
        array( 'Does a small business website really need security?', 'Yes. Automated attacks do not choose targets by company size. A firewall, malware scanning and dependable backups reduce both the likelihood and cost of an incident.' ),
        array( 'What is the difference between malware scanning and cleanup?', 'Scanning identifies suspicious or harmful code. Cleanup removes the infection and repairs affected files. Advanced and Premium include unlimited cleanups.' ),
        array( 'Does Website Security include SSL?', 'These plans include SSL protection through the website firewall. If you need a separately issued certificate for a server, domain or group of subdomains, compare the dedicated SSL plans.' ),
        array( 'Where does checkout happen?', 'Your plan is purchased through AZ Web Corp’s GoDaddy-powered reseller storefront. The secure cart may display a secureserver.net address because GoDaddy hosts the protected checkout environment.' ),
    );
}

function azwc_ss_ssl_faqs() {
    return array(
        array( 'What does an SSL certificate do?', 'SSL—more precisely TLS—encrypts information moving between a visitor’s browser and your website. It enables HTTPS and the browser security indicators customers expect.' ),
        array( 'What is the difference between Basic and Automated DV SSL?', 'Basic plans are lower-cost, but you handle installation, reinstallation and troubleshooting. Automated plans add guided setup, monitoring and ongoing certificate lifecycle automation.' ),
        array( 'Do I need a wildcard certificate?', 'Choose wildcard coverage when one primary domain has many subdomains, such as store.example.com, portal.example.com and members.example.com.' ),
        array( 'Will SSL improve SEO?', 'HTTPS is a lightweight search ranking signal and an important trust standard, but a certificate alone does not guarantee higher rankings. Strong content, performance and technical SEO still matter.' ),
        array( 'Why can checkout move to secureserver.net?', 'AZ Web Corp’s storefront is powered by GoDaddy. For security, GoDaddy hosts sensitive cart and account pages on its protected secureserver.net domain while retaining AZ Web Corp’s reseller association.' ),
    );
}

function azwc_ss_styles() {
    return <<<'CSS'
<style id="azwc-security-suite-styles">
#azwc-ss{--ink:#0a1322;--navy:#071426;--navy2:#102440;--yellow:#f4c430;--yellow2:#ffe47e;--paper:#fbfaf6;--cream:#efebe1;--muted:#687488;--line:#dde2e8;width:100%;overflow:hidden;background:var(--paper);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;line-height:1.55;-webkit-font-smoothing:antialiased}
#azwc-ss,#azwc-ss *{box-sizing:border-box}#azwc-ss a{color:inherit;text-decoration:none}#azwc-ss svg{fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}#azwc-ss .azwc-shell{width:min(1180px,calc(100% - 48px));margin:0 auto}#azwc-ss .azwc-kicker{margin:0 0 17px;color:#9b7000;font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}#azwc-ss .azwc-kicker.light{color:var(--yellow2)}#azwc-ss h1,#azwc-ss h2,#azwc-ss h3,#azwc-ss p{margin-top:0}#azwc-ss h1{max-width:820px;margin-bottom:26px;color:#f7f8fb!important;font-size:clamp(44px,5.6vw,74px);font-weight:570;line-height:1.035;letter-spacing:-.045em;text-wrap:balance;text-shadow:0 2px 24px rgba(0,0,0,.2)}#azwc-ss h1 em{color:var(--yellow2)!important;font-style:normal;font-weight:610}#azwc-ss .azwc-heading{margin-bottom:0;color:#1b2940!important;font-size:clamp(34px,3.8vw,53px);font-weight:580;line-height:1.08;letter-spacing:-.04em;text-wrap:balance}#azwc-ss .azwc-heading em{color:#987000!important;font-style:normal;font-weight:610}
#azwc-ss .azwc-product-nav{background:#fff;border-bottom:1px solid #e7e9ec}#azwc-ss .azwc-product-nav .azwc-shell{min-height:62px;display:flex;align-items:stretch}#azwc-ss .azwc-product-label,#azwc-ss .azwc-product-nav a{display:flex;align-items:center;padding:0 23px;font-size:12px;font-weight:800}#azwc-ss .azwc-product-label{padding-left:0;color:#8f6500;letter-spacing:.08em;text-transform:uppercase}#azwc-ss .azwc-product-nav a{position:relative;color:#536075;border-left:1px solid #eceef1}#azwc-ss .azwc-product-nav a:hover{color:#16233a;background:#faf8f1}#azwc-ss .azwc-product-nav a.active{color:#121b2b;background:var(--yellow)}#azwc-ss .azwc-product-nav a.active:after{content:"";position:absolute;left:50%;bottom:-7px;width:14px;height:14px;background:var(--yellow);transform:translateX(-50%) rotate(45deg)}
#azwc-ss .azwc-hero{position:relative;min-height:660px;display:flex;align-items:center;overflow:hidden;padding:100px 0;background:radial-gradient(circle at 82% 40%,rgba(244,196,48,.17),transparent 28%),linear-gradient(125deg,#071426,#0d1d34 64%,#142744);color:#fff}#azwc-ss .azwc-hero:before{content:"";position:absolute;inset:0;opacity:.25;background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);background-size:56px 56px;mask-image:linear-gradient(to right,#000,transparent 78%)}#azwc-ss .azwc-hero-grid{position:relative;z-index:2;display:grid;grid-template-columns:1.08fr .92fr;gap:75px;align-items:center}#azwc-ss .azwc-hero-copy>p{max-width:670px;margin:0;color:#c7cfdb;font-size:17px;font-weight:400;line-height:1.82}#azwc-ss .azwc-hero-trust{display:flex;flex-wrap:wrap;gap:18px;margin:31px 0 0;padding:0;list-style:none;color:#d9e0eb;font-size:12px;font-weight:600}#azwc-ss .azwc-hero-trust li{display:flex;align-items:center;gap:7px}#azwc-ss .azwc-hero-trust svg{width:15px;height:15px;color:var(--yellow)}
#azwc-ss .azwc-security-orb{position:relative;width:min(430px,100%);aspect-ratio:1;margin:auto;border:1px solid rgba(244,196,48,.18);border-radius:50%;box-shadow:0 0 100px rgba(244,196,48,.1)}#azwc-ss .azwc-security-orb:before,#azwc-ss .azwc-security-orb:after{content:"";position:absolute;border:1px solid rgba(244,196,48,.16);border-radius:50%}#azwc-ss .azwc-security-orb:before{inset:13%}#azwc-ss .azwc-security-orb:after{inset:27%}#azwc-ss .azwc-hero-icon{position:absolute;z-index:2;left:50%;top:50%;width:138px;height:138px;display:grid;place-items:center;color:#172238;background:linear-gradient(145deg,var(--yellow2),#d9a800);border:1px solid rgba(255,255,255,.4);border-radius:36px;box-shadow:0 28px 70px rgba(0,0,0,.38);transform:translate(-50%,-50%) rotate(-4deg)}#azwc-ss .azwc-hero-icon svg{width:65px;height:65px;stroke-width:1.45}#azwc-ss .azwc-orbit-tag{position:absolute;z-index:3;display:flex;align-items:center;gap:9px;padding:11px 14px;color:#223149;background:#fff;border-radius:12px;box-shadow:0 17px 38px rgba(0,0,0,.28);font-size:10px;font-weight:800}#azwc-ss .azwc-orbit-tag svg{width:18px;height:18px;color:#9b7000}#azwc-ss .azwc-tag-a{left:-2%;top:19%;transform:rotate(-4deg)}#azwc-ss .azwc-tag-b{right:-5%;top:32%;transform:rotate(4deg)}#azwc-ss .azwc-tag-c{left:9%;bottom:8%;transform:rotate(3deg)}
#azwc-ss .azwc-confidence{background:var(--cream);border-bottom:1px solid rgba(19,37,62,.1)}#azwc-ss .azwc-confidence-grid{min-height:112px;display:grid;grid-template-columns:repeat(3,1fr)}#azwc-ss .azwc-confidence-item{display:grid;grid-template-columns:42px 1fr;gap:13px;align-items:center;padding:24px 30px;border-left:1px solid rgba(19,37,62,.11)}#azwc-ss .azwc-confidence-item:last-child{border-right:1px solid rgba(19,37,62,.11)}#azwc-ss .azwc-confidence-item svg{width:29px;height:29px;color:#9c7000}#azwc-ss .azwc-confidence-item strong,#azwc-ss .azwc-confidence-item span{display:block}#azwc-ss .azwc-confidence-item strong{font-size:13px;color:#1d2b41}#azwc-ss .azwc-confidence-item span{margin-top:3px;color:#717b8a;font-size:10px;line-height:1.5}
#azwc-ss .azwc-plans{padding:120px 0 105px}#azwc-ss .azwc-section-intro{max-width:760px;margin:0 auto 56px;text-align:center}#azwc-ss .azwc-section-intro>p:last-child{margin:21px auto 0;color:var(--muted);font-size:16px;line-height:1.75}#azwc-ss .azwc-plan-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;align-items:stretch}#azwc-ss .azwc-plan{position:relative;overflow:hidden;padding:31px 28px 28px;background:#fff;border:1px solid #e0e3e7;border-radius:18px;box-shadow:0 12px 35px rgba(17,34,58,.055);transition:transform .25s,border-color .25s,box-shadow .25s}#azwc-ss .azwc-plan:hover{transform:translateY(-5px);border-color:#dbc15e;box-shadow:0 22px 55px rgba(17,34,58,.13)}#azwc-ss .azwc-plan.popular{color:#fff;background:#10213b;border-color:#10213b;box-shadow:0 25px 60px rgba(16,33,59,.23)}#azwc-ss .azwc-plan.popular:after{content:"";position:absolute;right:-100px;bottom:-100px;width:240px;height:240px;border:1px solid rgba(244,196,48,.17);border-radius:50%}#azwc-ss .azwc-popular{position:absolute;right:24px;top:0;padding:9px 12px;color:#18243a;background:var(--yellow);border-radius:0 0 9px 9px;font-size:8px;font-weight:900;letter-spacing:.11em;text-transform:uppercase}#azwc-ss .azwc-plan-label{display:inline-flex;margin-bottom:24px;padding:7px 10px;color:#8b6500;background:#fff3c7;border-radius:999px;font-size:9px;font-weight:850;letter-spacing:.09em;text-transform:uppercase}#azwc-ss .popular .azwc-plan-label{color:var(--yellow2);background:rgba(244,196,48,.12)}#azwc-ss .azwc-plan h3{margin:0;color:#14223a;font-size:25px;line-height:1.15;letter-spacing:-.035em}#azwc-ss .popular h3{color:#fff}#azwc-ss .azwc-plan-summary{min-height:69px;margin:13px 0 22px;color:#737f90;font-size:12px;line-height:1.65}#azwc-ss .popular .azwc-plan-summary{color:#aebbd0}#azwc-ss .azwc-price{display:flex;align-items:flex-end;gap:3px;margin-bottom:5px;color:#14223a}#azwc-ss .popular .azwc-price{color:#fff}#azwc-ss .azwc-price sup{align-self:flex-start;margin-top:7px;font-size:18px;font-weight:800}#azwc-ss .azwc-price strong{font-size:48px;line-height:.93;letter-spacing:-.055em}#azwc-ss .azwc-price span{padding-bottom:5px;color:#7b8798;font-size:10px}#azwc-ss .azwc-scope{margin-bottom:24px;color:#8b96a5;font-size:10px;font-weight:750}#azwc-ss .azwc-checkout{position:relative;z-index:2;margin:0 0 25px}#azwc-ss .azwc-checkout button{width:100%;min-height:49px;display:flex;align-items:center;justify-content:center;gap:9px;padding:0 16px;color:#13213a;background:var(--yellow);border:0;border-radius:10px;cursor:pointer;font:inherit;font-size:11px;font-weight:850;box-shadow:0 11px 24px rgba(42,32,0,.14);transition:transform .2s,background .2s}#azwc-ss .azwc-checkout button:hover{background:var(--yellow2);transform:translateY(-2px)}#azwc-ss .azwc-checkout button svg{width:15px;height:15px}#azwc-ss .azwc-features{position:relative;z-index:2;display:grid;gap:12px;margin:0;padding:0;list-style:none}#azwc-ss .azwc-features li{display:grid;grid-template-columns:17px 1fr;gap:8px;align-items:start;color:#5d6b7d;font-size:10px;line-height:1.55}#azwc-ss .popular .azwc-features li{color:#c4cedd}#azwc-ss .azwc-features svg{width:15px;height:15px;color:#a17400}
#azwc-ss .azwc-ssl-grid{grid-template-columns:repeat(2,1fr);gap:20px}#azwc-ss .azwc-ssl-grid .azwc-plan{display:grid;grid-template-columns:1fr 1.06fr;gap:28px;padding:30px}#azwc-ss .azwc-ssl-grid .azwc-plan-summary{min-height:0}#azwc-ss .azwc-ssl-grid .azwc-plan-left{display:flex;flex-direction:column}#azwc-ss .azwc-ssl-grid .azwc-checkout{margin-top:auto;margin-bottom:0}#azwc-ss .azwc-ssl-grid .azwc-features{padding-left:26px;border-left:1px solid #e5e7ea}#azwc-ss .azwc-ssl-grid .popular .azwc-features{border-left-color:rgba(255,255,255,.13)}
#azwc-ss .azwc-note{max-width:940px;margin:26px auto 0;color:#8d96a3;font-size:10px;line-height:1.7;text-align:center}#azwc-ss .azwc-checkout-note{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:18px;color:#7f8998;font-size:10px}#azwc-ss .azwc-checkout-note svg{width:14px;height:14px;color:#9b7000}
#azwc-ss .azwc-explain{padding:120px 0;background:var(--cream);border-top:1px solid rgba(20,37,61,.06)}#azwc-ss .azwc-explain-grid{display:grid;grid-template-columns:.82fr 1.18fr;gap:95px;align-items:start}#azwc-ss .azwc-explain-copy>p:last-child{margin:22px 0 0;color:var(--muted);font-size:15px;line-height:1.78}#azwc-ss .azwc-explain-list{border-top:1px solid rgba(24,42,66,.15)}#azwc-ss .azwc-explain-item{display:grid;grid-template-columns:42px 1fr;gap:15px;padding:27px 0;border-bottom:1px solid rgba(24,42,66,.15)}#azwc-ss .azwc-explain-item b{color:#9b7000;font-size:10px;letter-spacing:.09em}#azwc-ss .azwc-explain-item h3{margin:0 0 7px;color:#1b2a42;font-size:18px}#azwc-ss .azwc-explain-item p{margin:0;color:#6c7787;font-size:12px;line-height:1.7}
#azwc-ss .azwc-faq{padding:115px 0;background:#fff}#azwc-ss .azwc-faq-grid{display:grid;grid-template-columns:.7fr 1.3fr;gap:90px;align-items:start}#azwc-ss .azwc-faq-list{border-top:1px solid var(--line)}#azwc-ss details{border-bottom:1px solid var(--line)}#azwc-ss summary{position:relative;padding:24px 44px 24px 0;color:#1a2940;cursor:pointer;font-size:15px;font-weight:800;list-style:none}#azwc-ss summary::-webkit-details-marker{display:none}#azwc-ss summary:after{content:"+";position:absolute;right:3px;top:20px;color:#9c7000;font-size:25px;font-weight:400}#azwc-ss details[open] summary:after{content:"−"}#azwc-ss details p{max-width:720px;margin:0;padding:0 50px 24px 0;color:#697588;font-size:12px;line-height:1.75}
#azwc-ss .azwc-final{padding:72px 0;color:#fff;background:#0b192e}#azwc-ss .azwc-final-grid{display:flex;align-items:center;justify-content:space-between;gap:35px}#azwc-ss .azwc-final h2{max-width:710px;margin:0;color:#f6f8fb!important;font-size:clamp(28px,2.8vw,39px);font-weight:560;line-height:1.15;letter-spacing:-.032em;text-wrap:balance;text-shadow:0 2px 18px rgba(0,0,0,.18)}#azwc-ss .azwc-final p{margin:13px 0 0;color:#c2cad7;font-size:13px;font-weight:400;line-height:1.7}#azwc-ss .azwc-final a{min-height:48px;display:inline-flex;align-items:center;justify-content:center;padding:0 21px;color:#172238;background:var(--yellow);border-radius:10px;font-size:11px;font-weight:800;white-space:nowrap}
@media(max-width:1024px){#azwc-ss .azwc-hero-grid{grid-template-columns:1fr .78fr;gap:35px}#azwc-ss .azwc-plan-grid{grid-template-columns:1fr}#azwc-ss .azwc-plan{max-width:680px;width:100%;margin:0 auto}#azwc-ss .azwc-ssl-grid .azwc-plan{max-width:900px}#azwc-ss .azwc-explain-grid,#azwc-ss .azwc-faq-grid{gap:50px}}
@media(max-width:767px){#azwc-ss .azwc-shell{width:min(100% - 30px,1180px)}#azwc-ss .azwc-product-nav .azwc-shell{overflow-x:auto}#azwc-ss .azwc-product-label,#azwc-ss .azwc-product-nav a{min-height:54px;padding:0 15px;white-space:nowrap}#azwc-ss .azwc-product-label{padding-left:0}#azwc-ss .azwc-hero{min-height:auto;padding:75px 0 70px}#azwc-ss .azwc-hero-grid{grid-template-columns:1fr}#azwc-ss .azwc-hero h1{font-size:clamp(40px,11.8vw,56px);line-height:1.06}#azwc-ss .azwc-hero-copy>p{font-size:15px;line-height:1.78}#azwc-ss .azwc-security-orb{width:280px;margin-top:20px}#azwc-ss .azwc-hero-icon{width:102px;height:102px;border-radius:28px}#azwc-ss .azwc-hero-icon svg{width:48px;height:48px}#azwc-ss .azwc-orbit-tag{font-size:8px}#azwc-ss .azwc-confidence-grid{grid-template-columns:1fr}#azwc-ss .azwc-confidence-item,#azwc-ss .azwc-confidence-item:last-child{border-right:0;border-left:0;border-bottom:1px solid rgba(19,37,62,.11)}#azwc-ss .azwc-plans,#azwc-ss .azwc-explain,#azwc-ss .azwc-faq{padding:82px 0}#azwc-ss .azwc-plan,#azwc-ss .azwc-ssl-grid .azwc-plan{display:block;padding:25px 21px}#azwc-ss .azwc-ssl-grid .azwc-features{margin-top:25px;padding:24px 0 0;border-top:1px solid #e5e7ea;border-left:0}#azwc-ss .azwc-ssl-grid .popular .azwc-features{border-top-color:rgba(255,255,255,.13);border-left:0}#azwc-ss .azwc-explain-grid,#azwc-ss .azwc-faq-grid{grid-template-columns:1fr;gap:42px}#azwc-ss .azwc-final-grid{display:block}#azwc-ss .azwc-final a{margin-top:25px}}
@media(prefers-reduced-motion:reduce){#azwc-ss *{scroll-behavior:auto!important;transition:none!important}}

/* Product-first layout modeled on the GoDaddy reseller catalog hierarchy. */
#azwc-ss .azwc-catalog-head{padding:66px 0 36px;background:#fff}
#azwc-ss .azwc-catalog-head h1{max-width:none;margin:0 0 14px;color:#111827!important;font-size:clamp(38px,4.5vw,58px);font-weight:620;line-height:1.08;letter-spacing:-.04em;text-shadow:none}
#azwc-ss .azwc-catalog-head>div>p{max-width:790px;margin:0;color:#606a79;font-size:17px;line-height:1.7}
#azwc-ss .azwc-validation-tabs{display:flex;align-items:flex-end;margin-top:34px;border-bottom:1px solid #d8dce2}
#azwc-ss .azwc-validation-tabs span{position:relative;display:inline-flex;align-items:center;min-height:46px;padding:0 22px;color:#172238;border:1px solid #d8dce2;border-bottom-color:#fff;background:#fff;font-size:12px;font-weight:800}
#azwc-ss .azwc-validation-tabs span:after{content:"";position:absolute;left:0;right:0;bottom:-2px;height:3px;background:var(--yellow)}
#azwc-ss .azwc-plans{padding:38px 0 78px;background:#f3f3f3}
#azwc-ss .azwc-plan-grid,#azwc-ss .azwc-ssl-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;align-items:stretch}
#azwc-ss .azwc-plan,#azwc-ss .azwc-plan.popular,#azwc-ss .azwc-ssl-grid .azwc-plan{display:block;min-width:0;max-width:none;margin:0;padding:34px 34px 38px;color:#111827;background:#fff;border:1px solid #d7d9dd;border-radius:2px;box-shadow:none;transform:none}
#azwc-ss .azwc-plan:hover,#azwc-ss .azwc-plan.popular:hover{border-color:#c6c9cf;box-shadow:0 9px 24px rgba(15,23,42,.06);transform:none}
#azwc-ss .azwc-plan.popular:after,#azwc-ss .azwc-popular{display:none}
#azwc-ss .azwc-plan h3,#azwc-ss .azwc-plan.popular h3{min-height:0;margin:0 0 14px;color:#10131a!important;font-size:24px;font-weight:700;line-height:1.2;letter-spacing:-.025em}
#azwc-ss .azwc-plan-label,#azwc-ss .popular .azwc-plan-label{display:block;margin:0 0 8px;padding:0;color:#4f5868;background:transparent;border-radius:0;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
#azwc-ss .azwc-price,#azwc-ss .popular .azwc-price{display:flex;align-items:baseline;gap:3px;margin:0 0 21px;color:#27334a}
#azwc-ss .azwc-price sup{align-self:auto;margin:0;font-size:17px;font-weight:750;line-height:1}
#azwc-ss .azwc-price strong{font-size:23px;font-weight:750;line-height:1;letter-spacing:-.025em}
#azwc-ss .azwc-price span{padding:0;color:#111827;font-size:13px;font-weight:500}
#azwc-ss .azwc-checkout{margin:0 0 23px}
#azwc-ss .azwc-checkout button{width:auto;min-height:46px;padding:0 19px;border-radius:2px;box-shadow:none;font-size:12px;font-weight:800}
#azwc-ss .azwc-checkout button svg{display:none}
#azwc-ss .azwc-scope{margin:0 0 25px;color:#111827;font-size:13px;font-weight:750}
#azwc-ss .azwc-features,#azwc-ss .popular .azwc-features,#azwc-ss .azwc-ssl-grid .azwc-features,#azwc-ss .azwc-ssl-grid .popular .azwc-features{display:block;margin:0;padding:0 0 0 22px;border:0}
#azwc-ss .azwc-features li,#azwc-ss .popular .azwc-features li{display:list-item;margin:0 0 14px;color:#20242c;font-size:13px;line-height:1.55;list-style:disc}
#azwc-ss .azwc-ssl-grid .azwc-plan-left{display:block}
#azwc-ss .azwc-checkout-note{margin-top:25px}
#azwc-ss .azwc-note{margin-top:15px}
@media(max-width:1024px){#azwc-ss .azwc-plan-grid,#azwc-ss .azwc-ssl-grid{grid-template-columns:repeat(2,minmax(0,1fr))}#azwc-ss .azwc-plan,#azwc-ss .azwc-ssl-grid .azwc-plan{max-width:none;margin:0}}
@media(max-width:767px){#azwc-ss .azwc-catalog-head{padding:48px 0 27px}#azwc-ss .azwc-catalog-head h1{font-size:40px}#azwc-ss .azwc-catalog-head>div>p{font-size:15px}#azwc-ss .azwc-plans{padding:28px 0 62px}#azwc-ss .azwc-plan-grid,#azwc-ss .azwc-ssl-grid{grid-template-columns:1fr}#azwc-ss .azwc-plan,#azwc-ss .azwc-ssl-grid .azwc-plan{padding:28px 24px 32px}#azwc-ss .azwc-features,#azwc-ss .azwc-ssl-grid .azwc-features{margin:0;padding:0 0 0 20px;border:0}}
</style>
CSS;
}

function azwc_ss_product_nav( $type ) {
    ?>
    <nav class="azwc-product-nav" aria-label="Security products">
        <div class="azwc-shell">
            <span class="azwc-product-label">Security</span>
            <a href="<?php echo esc_url( home_url( '/website-security/' ) ); ?>" class="<?php echo 'website-security' === $type ? 'active' : ''; ?>">Website Security</a>
            <a href="<?php echo esc_url( home_url( '/ssl-certificates/' ) ); ?>" class="<?php echo 'ssl-certificates' === $type ? 'active' : ''; ?>">SSL Certificates</a>
        </div>
    </nav>
    <?php
}

function azwc_ss_hero( $type ) {
    $is_security = 'website-security' === $type;
    ?>
    <section class="azwc-catalog-head">
        <div class="azwc-shell">
            <?php if ( $is_security ) : ?>
                <h1>Website Security</h1>
                <p>Protect your site and keep customers safe with a straightforward combination of firewall protection, malware scanning, cleanup support and secure backups.</p>
            <?php else : ?>
                <h1>SSL Certificates</h1>
                <p>Enable HTTPS and protect information moving between your website and its visitors with a domain-validated SSL certificate.</p>
                <div class="azwc-validation-tabs" aria-label="SSL validation type"><span>Domain Validation</span></div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

function azwc_ss_confidence( $type ) {
    $security = 'website-security' === $type;
    $items = $security ? array(
        array( 'shield', 'Block threats earlier', 'A firewall helps stop malicious traffic before it reaches your site.' ),
        array( 'scan', 'Find and repair malware', 'Continuous scanning plus cleanup options when an infection is found.' ),
        array( 'backup', 'Recover with confidence', 'Secure backup capacity is included with Advanced and Premium.' ),
    ) : array(
        array( 'lock', 'Encrypt customer connections', 'HTTPS protects information moving between the browser and your server.' ),
        array( 'scan', 'Choose manual or automated', 'Pay less and manage it yourself—or reduce maintenance with automation.' ),
        array( 'globe', 'Cover the right footprint', 'Protect one site, five domains or unlimited subdomains.' ),
    );
    ?>
    <section class="azwc-confidence" aria-label="Key benefits"><div class="azwc-shell azwc-confidence-grid">
        <?php foreach ( $items as $item ) : ?>
            <div class="azwc-confidence-item"><?php echo azwc_ss_icon( $item[0] ); ?><div><strong><?php echo esc_html( $item[1] ); ?></strong><span><?php echo esc_html( $item[2] ); ?></span></div></div>
        <?php endforeach; ?>
    </div></section>
    <?php
}

function azwc_ss_render_security_plans() {
    $plans = azwc_ss_security_plans();
    ?>
    <section class="azwc-plans" id="plans"><div class="azwc-shell">
        <div class="azwc-plan-grid">
            <?php foreach ( $plans as $plan ) : ?>
                <article class="azwc-plan">
                    <h3><?php echo esc_html( $plan['name'] ); ?></h3>
                    <div class="azwc-price"><sup>$</sup><strong><?php echo esc_html( $plan['price'] ); ?></strong><span>per month</span></div>
                    <?php echo azwc_ss_checkout_form( $plan['product'], $plan['name'] . ' Website Security' ); ?>
                    <div class="azwc-scope">Per site</div>
                    <ul class="azwc-features">
                        <?php foreach ( $plan['features'] as $feature ) : ?><li><?php echo esc_html( $feature ); ?></li><?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="azwc-checkout-note"><?php echo azwc_ss_icon( 'lock' ); ?> Secure checkout is fulfilled through AZ Web Corp’s GoDaddy-powered reseller storefront.</p>
        <p class="azwc-note">Prices shown are the current storefront prices and exclude applicable taxes and ICANN fees. Product availability and renewal pricing may vary.</p>
    </div></section>
    <?php
}

function azwc_ss_render_ssl_plans() {
    $plans = azwc_ss_ssl_plans();
    ?>
    <section class="azwc-plans" id="plans"><div class="azwc-shell">
        <div class="azwc-plan-grid azwc-ssl-grid">
            <?php foreach ( $plans as $plan ) : ?>
                <article class="azwc-plan">
                    <div class="azwc-plan-left">
                        <span class="azwc-plan-label"><?php echo esc_html( $plan['tier'] ); ?></span>
                        <h3><?php echo esc_html( $plan['name'] ); ?></h3>
                        <div class="azwc-price"><sup>$</sup><strong><?php echo esc_html( $plan['price'] ); ?></strong><span>per year</span></div>
                        <?php echo azwc_ss_checkout_form( $plan['product'], $plan['tier'] . ' ' . $plan['name'] ); ?>
                        <div class="azwc-scope"><?php echo esc_html( $plan['scope'] ); ?>.</div>
                    </div>
                    <ul class="azwc-features">
                        <?php foreach ( $plan['features'] as $feature ) : ?><li><?php echo esc_html( $feature ); ?></li><?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="azwc-checkout-note"><?php echo azwc_ss_icon( 'lock' ); ?> Each button adds the selected certificate directly to AZ Web Corp’s secure GoDaddy reseller cart.</p>
        <p class="azwc-note">Prices shown are the current storefront prices and exclude applicable taxes and ICANN fees. Issuance time begins after successful domain validation. Product availability and renewal pricing may vary.</p>
    </div></section>
    <?php
}

function azwc_ss_explain( $type ) {
    if ( 'website-security' === $type ) {
        $kicker = 'Security without the scare tactics';
        $heading = 'A practical safety net for your online business.';
        $copy = 'Good website security is layered. One tool watches the traffic, another looks for malicious code, and a dependable backup gives you a clean path back when something goes wrong.';
        $items = array(
            array( '01', 'Firewall first', 'Suspicious traffic is filtered before it can interact with vulnerable website code.' ),
            array( '02', 'Scan continuously', 'Malware monitoring looks for harmful files and changes that should not be there.' ),
            array( '03', 'Recover deliberately', 'Cleanup support and secure backups reduce downtime and guesswork after an incident.' ),
        );
    } else {
        $kicker = 'Choose based on maintenance—not jargon';
        $heading = 'Basic saves money. Automated saves hands-on work.';
        $copy = 'Both plan families provide domain-validated encryption. The meaningful difference is who handles installation, reinstallation, monitoring and certificate lifecycle changes.';
        $items = array(
            array( '01', 'Basic DV SSL', 'Best when you or your developer can install, reissue and troubleshoot certificates manually.' ),
            array( '02', 'Automated DV SSL', 'Best when you want guided setup, change alerts and certificate lifecycle automation.' ),
            array( '03', 'Wildcard coverage', 'Best for one primary domain with many subdomains that need encrypted HTTPS connections.' ),
        );
    }
    ?>
    <section class="azwc-explain"><div class="azwc-shell azwc-explain-grid">
        <div class="azwc-explain-copy"><p class="azwc-kicker"><?php echo esc_html( $kicker ); ?></p><h2 class="azwc-heading"><?php echo esc_html( $heading ); ?></h2><p><?php echo esc_html( $copy ); ?></p></div>
        <div class="azwc-explain-list">
            <?php foreach ( $items as $item ) : ?><article class="azwc-explain-item"><b><?php echo esc_html( $item[0] ); ?></b><div><h3><?php echo esc_html( $item[1] ); ?></h3><p><?php echo esc_html( $item[2] ); ?></p></div></article><?php endforeach; ?>
        </div>
    </div></section>
    <?php
}

function azwc_ss_faq( $type ) {
    $faqs = 'website-security' === $type ? azwc_ss_security_faqs() : azwc_ss_ssl_faqs();
    ?>
    <section class="azwc-faq"><div class="azwc-shell azwc-faq-grid">
        <div><p class="azwc-kicker">Straight answers</p><h2 class="azwc-heading">A few things worth knowing.</h2></div>
        <div class="azwc-faq-list">
            <?php foreach ( $faqs as $index => $faq ) : ?><details <?php echo 0 === $index ? 'open' : ''; ?>><summary><?php echo esc_html( $faq[0] ); ?></summary><p><?php echo esc_html( $faq[1] ); ?></p></details><?php endforeach; ?>
        </div>
    </div></section>
    <?php
}

function azwc_ss_final_cta( $type ) {
    $other = 'website-security' === $type ? 'ssl-certificates' : 'website-security';
    $heading = 'website-security' === $type ? 'Need a dedicated SSL certificate too?' : 'Want protection beyond encryption?';
    $copy = 'website-security' === $type ? 'Compare certificates for one site, multiple domains or unlimited subdomains.' : 'Add a firewall, malware scanning, cleanup assistance, DDoS defense and backups.';
    $label = 'website-security' === $type ? 'Compare SSL Certificates' : 'Explore Website Security';
    ?>
    <section class="azwc-final"><div class="azwc-shell azwc-final-grid"><div><h2><?php echo esc_html( $heading ); ?></h2><p><?php echo esc_html( $copy ); ?></p></div><a href="<?php echo esc_url( home_url( '/' . $other . '/' ) ); ?>"><?php echo esc_html( $label ); ?></a></div></section>
    <?php
}

function azwc_ss_render_page( $type ) {
    static $rendering = false;
    if ( $rendering ) {
        return '';
    }
    $rendering = true;
    ob_start();
    echo '<!-- AZWC_SECURITY_SUITE:' . esc_html( $type ) . ' -->';
    echo azwc_ss_styles();
    ?>
    <main id="azwc-ss" data-azwc-page="<?php echo esc_attr( $type ); ?>">
        <?php azwc_ss_product_nav( $type ); ?>
        <?php azwc_ss_hero( $type ); ?>
        <?php 'website-security' === $type ? azwc_ss_render_security_plans() : azwc_ss_render_ssl_plans(); ?>
        <?php azwc_ss_confidence( $type ); ?>
        <?php azwc_ss_explain( $type ); ?>
        <?php azwc_ss_faq( $type ); ?>
        <?php azwc_ss_final_cta( $type ); ?>
    </main>
    <?php
    $html = ob_get_clean();
    $rendering = false;
    return $html;
}
'''


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Inspect or deploy AZ Web Corp's Website Security and SSL pages."
    )
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Deploy the integration. Without this flag the script is read-only.",
    )
    parser.add_argument("--host", default=DEFAULT_HOST)
    parser.add_argument("--user", default=DEFAULT_USER)
    parser.add_argument("--port", type=int, default=DEFAULT_PORT)
    parser.add_argument("--site-url", default=DEFAULT_SITE_URL)
    parser.add_argument(
        "--wp-root",
        default=DEFAULT_WP_ROOT,
        help="Known remote WordPress root, normally html.",
    )
    parser.add_argument(
        "--password-env",
        default="AZWEBCORP_SSH_PASSWORD",
        help="Environment variable containing the SSH password.",
    )
    parser.add_argument(
        "--accept-new-host-key",
        action="store_true",
        help="Accept a previously unknown SSH host key for this run.",
    )
    parser.add_argument(
        "--no-browser",
        action="store_true",
        help="Do not open the two live pages after deployment.",
    )
    parser.add_argument(
        "--output-dir",
        default=str(Path.cwd() / "azwebcorp_security_ssl_deployment"),
        help="Local directory for reports and backups.",
    )
    parser.add_argument(
        "--export-php",
        action="store_true",
        help="Write the generated MU-plugin PHP locally and exit without connecting.",
    )
    return parser.parse_args()


def export_php(output_dir: Path) -> Path:
    output_dir.mkdir(parents=True, exist_ok=True)
    path = output_dir / MU_PLUGIN_FILENAME
    path.write_text(build_mu_plugin(), encoding="utf-8")
    ok(f"Generated integration: {path}")
    return path


def main() -> int:
    args = parse_args()
    output_dir = Path(args.output_dir).expanduser().resolve()
    output_dir.mkdir(parents=True, exist_ok=True)

    print("=" * 78)
    print("AZ Web Corp — Website Security + SSL Deployer")
    print("=" * 78)
    print("Mode:", "APPLY" if args.apply else "INSPECTION ONLY")
    print("Website Security:", args.site_url.rstrip("/") + "/website-security/")
    print("SSL Certificates:", args.site_url.rstrip("/") + "/ssl-certificates/")
    print("=" * 78)

    if args.export_php:
        export_php(output_dir)
        return 0

    paramiko = ensure_dependency("paramiko", "paramiko>=3.4,<5")
    password = os.environ.get(args.password_env)
    if not password:
        password = getpass.getpass("SSH password: ")
    if not password:
        raise DeploymentError("No SSH password was provided.")

    remote: Optional[RemoteWordPress] = None
    try:
        remote = RemoteWordPress(
            paramiko_module=paramiko,
            host=args.host,
            username=args.user,
            password=password,
            port=args.port,
            accept_new_host_key=args.accept_new_host_key,
        )
        wp_root = detect_wp_root(remote, args.wp_root)
        before = inspect_installation(
            remote,
            wp_root,
            args.site_url,
            mode="inspection-before-deployment",
        )
        print_report(before)
        before_path = save_report(before, output_dir, "before")
        info(f"Inspection report saved: {before_path}")

        if not args.apply:
            print()
            ok("Inspection finished. No remote files or WordPress settings were changed.")
            print("\nDeploy after reviewing the report with:")
            print(f'  "{sys.executable}" "{Path(__file__).resolve()}" --apply --wp-root html')
            return 0

        deployment_id = utc_timestamp()
        local_backup, remote_backup = backup_existing(
            remote,
            wp_root,
            output_dir,
            deployment_id,
            before.wp_cli_available,
        )
        ok(f"Local backup created: {local_backup}")
        ok(f"Remote backup created: {remote_backup}")

        php = build_mu_plugin()
        generated_path = output_dir / MU_PLUGIN_FILENAME
        generated_path.write_text(php, encoding="utf-8")
        deploy_mu_plugin(remote, wp_root, php)

        # First public request runs the versioned page installer.
        for slug in PAGE_SPECS:
            url = f"{args.site_url.rstrip('/')}/{slug}/?azwc_install={deployment_id}"
            status, _, final_url, request_error = fetch_url(url, timeout=60)
            if request_error:
                warn(f"Installer request for /{slug}/ reported: {request_error}")
            else:
                info(f"Installer request /{slug}/: HTTP {status} -> {final_url}")

        if before.wp_cli_available:
            code, out, err = run_wp(remote, wp_root, "rewrite flush")
            if code == 0:
                ok("WordPress rewrite rules flushed.")
            else:
                warn(f"Rewrite flush was not confirmed: {err or out}")
            code, out, err = run_wp(remote, wp_root, "cache flush")
            if code == 0:
                ok("WordPress object cache flushed.")
            else:
                warn(f"Object-cache flush was not confirmed: {err or out}")

        time.sleep(2)
        after = inspect_installation(
            remote,
            wp_root,
            args.site_url,
            mode="inspection-after-deployment",
        )
        print_report(after)
        after_path = save_report(after, output_dir, "after")
        info(f"Post-deployment report saved: {after_path}")

        failures = []
        for route in after.live_routes:
            if route["http_status"] != 200:
                failures.append(f"/{route['slug']}/ returned HTTP {route['http_status']}")
            if not route["route_correct"]:
                failures.append(
                    f"/{route['slug']}/ resolved to {route['final_url']} instead of its own route"
                )
            if not route["marker_found"]:
                failures.append(f"/{route['slug']}/ did not contain the integration marker")
            if route["product_ids_missing"]:
                failures.append(
                    f"/{route['slug']}/ missed product IDs: "
                    + ", ".join(route["product_ids_missing"])
                )
            if not route["checkout_action_found"]:
                failures.append(f"/{route['slug']}/ did not contain the secure cart action")

        print()
        print("=" * 78)
        print("DEPLOYMENT RESULT")
        print("=" * 78)
        print("Website Security:", args.site_url.rstrip("/") + "/website-security/")
        print("SSL Certificates:", args.site_url.rstrip("/") + "/ssl-certificates/")
        print("Local backup:      ", local_backup)
        print("Remote backup:     ", remote_backup)
        print("Verification:      ", after_path)
        print("=" * 78)

        if failures:
            for item in failures:
                fail(item)
            raise DeploymentError(
                "Deployment completed but live verification did not fully pass. "
                "Review the report and purge the managed WordPress cache before rerunning."
            )

        ok("Both pages, routes, and all nine reseller checkout mappings verified.")
        if not args.no_browser:
            webbrowser.open(args.site_url.rstrip("/") + "/website-security/")
            webbrowser.open(args.site_url.rstrip("/") + "/ssl-certificates/")
        return 0
    finally:
        if remote:
            remote.close()


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except KeyboardInterrupt:
        fail("Cancelled by user.")
        raise SystemExit(130)
    except DeploymentError as exc:
        fail(str(exc))
        raise SystemExit(1)

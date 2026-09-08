"""Pick a GA4 OAuth token that actually works.

    from ga4_token import resolve_ga4_token
    token_path = resolve_ga4_token(scopes)

WHY THIS EXISTS
ga4-oauth-token.json expired and could not be refreshed, which silently broke
sync_portal.py and the EIT monthly report - both hardcoded that one path, so
the only symptom was a RefreshError deep in a run. Meanwhile two other tokens
issued from the same OAuth client still authenticated against every property.

Hardcoding the working one instead would just move the problem: it would need
remembering and undoing once the intended token is restored, and a temporary
fix nobody has to revisit is a permanent one. So this prefers the intended
token and falls back only when it genuinely cannot be used. Re-authorise
ga4-oauth-token.json and both callers return to it with no code change.

A fallback is announced on stderr rather than made quietly - running against
a broader-scoped credential than intended is worth seeing in the log.
"""

from pathlib import Path

TOOLS = Path(r"C:\Users\yasir\.claude-tools")

# Preference order. The first is what these scripts are meant to use; the rest
# are same-client tokens that carry at least analytics.readonly.
CANDIDATES = [
    TOOLS / "ga4-oauth-token.json",
    TOOLS / "ga4-admin-token.json",
    TOOLS / "ga4-edit-token.json",
]


def _usable(path: Path, scopes) -> bool:
    """True if the token loads and holds a currently-valid or refreshable grant."""
    from google.auth.transport.requests import Request
    from google.oauth2.credentials import Credentials

    try:
        creds = Credentials.from_authorized_user_file(str(path), list(scopes))
    except Exception:
        return False

    if creds.valid:
        return True
    if not (creds.expired and creds.refresh_token):
        return False

    try:
        creds.refresh(Request())
    except Exception:
        return False

    # Persist the refreshed access token so the next run starts valid.
    try:
        path.write_text(creds.to_json(), encoding="utf-8")
    except OSError:
        pass
    return True


def resolve_ga4_token(scopes, quiet: bool = False) -> Path:
    """Return the first candidate token that authenticates for `scopes`.

    Falls back to the preferred path unchanged if none can be verified - for
    instance with no network - so this never becomes a new failure mode of its
    own. The caller then fails with its own, more specific error.
    """
    import sys

    preferred = CANDIDATES[0]
    for candidate in CANDIDATES:
        if not candidate.exists():
            continue
        if _usable(candidate, scopes):
            if candidate != preferred and not quiet:
                print(
                    f"[ga4_token] {preferred.name} is not usable; falling back to "
                    f"{candidate.name}. Re-authorise {preferred.name} to restore it.",
                    file=sys.stderr,
                )
            return candidate

    if not quiet:
        print(
            f"[ga4_token] no GA4 token could be verified; using {preferred.name} "
            "and letting the caller report the failure.",
            file=sys.stderr,
        )
    return preferred


if __name__ == "__main__":
    chosen = resolve_ga4_token(["https://www.googleapis.com/auth/analytics.readonly"])
    print(f"resolved: {chosen}")

"""
Mint a Sanctum bearer token from the Union Bay CRM PublicApi.

Usage:
    uv run login.py
    uv run login.py --url http://localhost:8190 --email admin@example.com

Prompts for password if not given. Prints an export line you can paste into
your shell or Claude Desktop config.
"""

from __future__ import annotations

import argparse
import getpass
import json
import os
import sys

import httpx


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--url", default=os.environ.get("UNION_BAY_CRM_URL", "http://localhost:8190"))
    p.add_argument("--email", default=os.environ.get("UNION_BAY_CRM_EMAIL"))
    p.add_argument("--password", default=os.environ.get("UNION_BAY_CRM_PASSWORD"))
    args = p.parse_args()

    email = args.email or input("Email: ").strip()
    password = args.password or getpass.getpass("Password: ")

    url = args.url.rstrip("/") + "/api/v1/auth/login"
    try:
        r = httpx.post(url, json={"email": email, "password": password}, timeout=15.0)
    except httpx.HTTPError as e:
        print(f"login failed (network): {e}", file=sys.stderr)
        return 2

    if r.status_code != 200:
        print(f"login failed: HTTP {r.status_code}", file=sys.stderr)
        try:
            print(json.dumps(r.json(), indent=2), file=sys.stderr)
        except Exception:
            print(r.text, file=sys.stderr)
        return 1

    data = r.json()
    token = data.get("token")
    if not token:
        print(f"unexpected response: {data}", file=sys.stderr)
        return 1

    user = data.get("user", {})
    print(f"# Authenticated as {user.get('name')} <{user.get('email')}> (id={user.get('id')})", file=sys.stderr)
    print(f"# Add to your shell or Claude Desktop env:")
    print(f"export UNION_BAY_CRM_URL={args.url}")
    print(f"export UNION_BAY_CRM_TOKEN={token}")
    return 0


if __name__ == "__main__":
    sys.exit(main())

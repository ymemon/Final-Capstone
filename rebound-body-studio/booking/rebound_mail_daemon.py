"""Keep the Rebound authenticated queue worker alive under the S4U mail task."""

from __future__ import annotations

import msvcrt
from pathlib import Path
import time

from rebound_mail_worker import logger, run


LOCK_PATH = Path(__file__).resolve().parent / ".rebound_mail_daemon.lock"


def acquire_single_instance():
    lock = LOCK_PATH.open("a+b")
    if lock.tell() == 0:
        lock.write(b"0")
        lock.flush()
    lock.seek(0)
    try:
        msvcrt.locking(lock.fileno(), msvcrt.LK_NBLCK, 1)
    except OSError:
        return None
    return lock


def main() -> int:
    lock = acquire_single_instance()
    if lock is None:
        return 0

    logger.info("logoff-safe worker daemon started; marketing preference checks enabled (20260912)")
    while True:
        try:
            result = run()
            if result:
                logger.warning("worker cycle returned %s", result)
        except Exception:
            logger.exception("worker daemon cycle failed")
        time.sleep(30)


if __name__ == "__main__":
    raise SystemExit(main())

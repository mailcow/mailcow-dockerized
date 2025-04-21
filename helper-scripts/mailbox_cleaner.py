#!/usr/bin/env python3
"""
This script cleans up old messages from specified mailboxes (e.g., Trash, Junk)
in a Mailcow environment. It can process a single user or all users, and it
supports dry-run mode.

Ideally, this script should be run daily via cron.
"""

import argparse
import logging
import os
import re
import subprocess
import sys

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

DEFAULT_DAYS_BACK: int = 30
DEFAULT_MAILCOW_DIR: str = "/opt/mailcow-dockerized"
DEFAULT_MAILBOXES: list[str] = ["Trash", "Junk"]


def _run_doveadm_command(mailcow_dir: str, user: str | None, command: list[str]) -> str:
    """
    Runs a doveadm command within the dovecot-mailcow container.

    Args:
        mailcow_dir: The path to the mailcow-dockerized directory.
        user: The email address of the user to run the command for, or None for all users.
        command: The doveadm command to run as a list of strings.

    Returns:
        The standard output of the command.
    """
    command = ["docker", "compose", "--project-directory", mailcow_dir, "exec", "-T", "dovecot-mailcow",
               "doveadm"] + command
    if user:
        command.extend(["-u", user])
    logging.debug(f"Executing command: {' '.join(command)}")
    try:
        result = subprocess.run(command, capture_output=True, text=True, check=True)
        return result.stdout.strip()
    except subprocess.CalledProcessError as e:
        logging.error(f"Command execution failed: {' '.join(command)} (return code: {e.returncode})")
        logging.error(f"Stderr: {e.stderr}")
        logging.error(f"Stdout: {e.stdout}")
        raise


def main() -> None:
    """
    Main function to parse arguments and execute the cleanup process.
    """
    parser = argparse.ArgumentParser(
        description="Clean up old messages from specified mailboxes in a Mailcow environment.",
        formatter_class=argparse.ArgumentDefaultsHelpFormatter,
    )
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--user", help="Email address of the single user to process.")
    group.add_argument("--all", action="store_true", help="Process all users found via doveadm.")
    parser.add_argument("--days-back", type=int, default=DEFAULT_DAYS_BACK,
                        help="Number of days back to consider for message deletion.")
    parser.add_argument("--mailcow-directory", default=DEFAULT_MAILCOW_DIR,
                        help="Path to the mailcow-dockerized directory.")
    parser.add_argument("--mailboxes", nargs='+', default=DEFAULT_MAILBOXES,
                        help="List of top-level mailboxes (and their subfolders) to process (e.g., Trash Junk).")
    parser.add_argument("--debug", action="store_true", help="Enable debug logging.")
    parser.add_argument("--dry-run", action="store_true", help="Perform a dry run without deleting anything.")

    args = parser.parse_args()

    if args.debug:
        logging.getLogger().setLevel(logging.DEBUG)

    if not os.path.isdir(args.mailcow_directory):
        raise FileNotFoundError(
            f"Mailcow directory '{args.mailcow_directory}' does not exist or is not a directory.")
    # If --all is specified, get all users
    if args.all:
        doveadm_output = _run_doveadm_command(args.mailcow_directory, None, ["user", "*"])
        users = [line.strip() for line in doveadm_output.splitlines() if line.strip()]
    # Otherwise, use the specified user
    else:
        try:
            _run_doveadm_command(args.mailcow_directory, None, ["user", args.user])
        except subprocess.CalledProcessError:
            logging.error(f"User '{args.user}' not found.")
            sys.exit(1)
        users = [args.user]
    logging.info(f"Starting processing for {len(users)} users.")
    logging.debug(f"Users to process: {', '.join(users)}")
    # Iterate over each user
    for user in users:
        # Get all mailboxes for the current user
        logging.info(f"Processing user: '{user}'.")
        doveadm_output = _run_doveadm_command(args.mailcow_directory, user, ["mailbox", "list"])
        # get all user mailboxes, sorted in reverse order
        mailboxes = sorted([line.strip() for line in doveadm_output.splitlines() if line.strip()], reverse=True)
        logging.info(f"User '{user}' has {len(mailboxes)} mailboxes.")
        logging.debug(f"Mailboxes for user '{user}': {', '.join(mailboxes)}")
        for mailbox in mailboxes:
            # Iterate over each mailbox
            logging.debug(f"Processing mailbox '{mailbox}' for user '{user}'.")
            # Check if the mailbox is a target mailbox
            if not any(re.match(rf"{re.escape(tmb)}(/|$)", mailbox, re.IGNORECASE) for tmb in args.mailboxes):
                logging.debug(f"Skipping mailbox '{mailbox}' for user '{user}' as it is not a target mailbox.")
                continue
            # Expunge old messages from the mailbox
            logging.info(
                f"Expunging messages older than {args.days_back} days from mailbox '{mailbox}' for user '{user}'.")
            if args.dry_run:
                logging.info(f"[DRY-RUN] Skipping expunge command for mailbox '{mailbox}' of user '{user}'.")
            else:
                # Run the expunge command
                _run_doveadm_command(args.mailcow_directory, user,
                                     ["expunge", "mailbox", mailbox, "savedbefore", f"{args.days_back}d"])
            # Check if the mailbox is a sub-mailbox
            if "/" not in mailbox:
                logging.debug(
                    f"Skipping deletion check for top-level mailbox '{mailbox}' to preserve standard folders.")
                continue
            # Check if the mailbox is empty
            doveadm_output = _run_doveadm_command(args.mailcow_directory, user, ["mailbox", "status", "messages", mailbox])
            messages_count = int(doveadm_output.split("=")[1])
            logging.debug(f"Mailbox '{mailbox}' for user '{user}' contains {messages_count} messages.")
            if messages_count > 0:
                logging.info(f"Skipping deletion of mailbox '{mailbox}' for user '{user}' as it is not empty.")
                continue
            # Delete the mailbox if it's empty
            logging.info(f"Deleting mailbox '{mailbox}' for user '{user}' (only if empty).")
            if args.dry_run:
                logging.info(f"[DRY-RUN] Skipping delete command for mailbox '{mailbox}' of user '{user}'.")
            else:
                # As a safeguard, -e flag prevents mailbox deletion in case it's not empty
                _run_doveadm_command(args.mailcow_directory, user, ["mailbox", "delete", "-e", "-s", mailbox])


if __name__ == "__main__":
    main()

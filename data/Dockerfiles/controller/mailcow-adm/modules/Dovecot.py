import os

from modules.Docker import Docker

class Dovecot:
    def __init__(self):
        self.docker = Docker()

    def decryptMaildir(self, source_dir="/var/vmail/", output_dir=None):
        """
        Decrypt files in /var/vmail using doveadm if they are encrypted.
        :param output_dir: Directory inside the Dovecot container to store decrypted files, Default overwrite.
        """
        private_key = "/mail_crypt/ecprivkey.pem"
        public_key = "/mail_crypt/ecpubkey.pem"

        if output_dir:
            # Ensure the output directory exists inside the container
            mkdir_result = self.docker.exec_command("dovecot-mailcow", f"bash -c 'mkdir -p {output_dir} && chown vmail:vmail {output_dir}'")
            if mkdir_result.get("status") != "success":
                print(f"Error creating output directory: {mkdir_result.get('output')}")
                return

        find_command = [
            "find", source_dir, "-type", "f", "-regextype", "egrep", "-regex", ".*S=.*W=.*"
        ]

        try:
            find_result = self.docker.exec_command("dovecot-mailcow", " ".join(find_command))
            if find_result.get("status") != "success":
                print(f"Error finding files: {find_result.get('output')}")
                return

            files = find_result.get("output", "").splitlines()

            for file in files:
                head_command = f"head -c7 {file}"
                head_result = self.docker.exec_command("dovecot-mailcow", head_command)
                if head_result.get("status") == "success" and head_result.get("output", "").strip() == "CRYPTED":
                    if output_dir:
                        # Preserve the directory structure in the output directory
                        relative_path = os.path.relpath(file, source_dir)
                        output_file = os.path.join(output_dir, relative_path)
                        current_path = output_dir
                        for part in os.path.dirname(relative_path).split(os.sep):
                            current_path = os.path.join(current_path, part)
                            mkdir_result = self.docker.exec_command("dovecot-mailcow", f"bash -c '[ ! -d {current_path} ] && mkdir {current_path} && chown vmail:vmail {current_path}'")
                            if mkdir_result.get("status") != "success":
                                print(f"Error creating directory {current_path}: {mkdir_result.get('output')}")
                                continue
                    else:
                        # Overwrite the original file
                        output_file = file

                    decrypt_command = (
                        f"bash -c 'doveadm fs get compress lz4:1:crypt:private_key_path={private_key}:public_key_path={public_key}:posix:prefix=/ {file} > {output_file}'"
                    )

                    decrypt_result = self.docker.exec_command("dovecot-mailcow", decrypt_command)
                    if decrypt_result.get("status") == "success":
                        print(f"Decrypted {file}")

                        # Verify the file size and set permissions
                        size_check_command = f"bash -c '[ -s {output_file} ] && chmod 600 {output_file} && chown vmail:vmail {output_file} || rm -f {output_file}'"
                        size_check_result = self.docker.exec_command("dovecot-mailcow", size_check_command)
                        if size_check_result.get("status") != "success":
                            print(f"Error setting permissions for {output_file}: {size_check_result.get('output')}\n")

        except Exception as e:
            print(f"Error during decryption: {e}")

        return "Done"

    def encryptMaildir(self, source_dir="/var/vmail/", output_dir=None):
        """
        Encrypt files in /var/vmail using doveadm if they are not already encrypted.
        :param source_dir: Directory inside the Dovecot container to encrypt files.
        :param output_dir: Directory inside the Dovecot container to store encrypted files, Default overwrite.
        """
        private_key = "/mail_crypt/ecprivkey.pem"
        public_key = "/mail_crypt/ecpubkey.pem"

        if output_dir:
            # Ensure the output directory exists inside the container
            mkdir_result = self.docker.exec_command("dovecot-mailcow", f"mkdir -p {output_dir}")
            if mkdir_result.get("status") != "success":
                print(f"Error creating output directory: {mkdir_result.get('output')}")
                return

        find_command = [
            "find", source_dir, "-type", "f", "-regextype", "egrep", "-regex", ".*S=.*W=.*"
        ]

        try:
            find_result = self.docker.exec_command("dovecot-mailcow", " ".join(find_command))
            if find_result.get("status") != "success":
                print(f"Error finding files: {find_result.get('output')}")
                return

            files = find_result.get("output", "").splitlines()

            for file in files:
                head_command = f"head -c7 {file}"
                head_result = self.docker.exec_command("dovecot-mailcow", head_command)
                if head_result.get("status") == "success" and head_result.get("output", "").strip() != "CRYPTED":
                    if output_dir:
                        # Preserve the directory structure in the output directory
                        relative_path = os.path.relpath(file, source_dir)
                        output_file = os.path.join(output_dir, relative_path)
                        current_path = output_dir
                        for part in os.path.dirname(relative_path).split(os.sep):
                            current_path = os.path.join(current_path, part)
                            mkdir_result = self.docker.exec_command("dovecot-mailcow", f"bash -c '[ ! -d {current_path} ] && mkdir {current_path} && chown vmail:vmail {current_path}'")
                            if mkdir_result.get("status") != "success":
                                print(f"Error creating directory {current_path}: {mkdir_result.get('output')}")
                                continue
                    else:
                        # Overwrite the original file
                        output_file = file

                    encrypt_command = (
                        f"bash -c 'doveadm fs put crypt private_key_path={private_key}:public_key_path={public_key}:posix:prefix=/ {file} {output_file}'"
                    )

                    encrypt_result = self.docker.exec_command("dovecot-mailcow", encrypt_command)
                    if encrypt_result.get("status") == "success":
                        print(f"Encrypted {file}")

                        # Set permissions
                        permissions_command = f"bash -c 'chmod 600 {output_file} && chown 5000:5000 {output_file}'"
                        permissions_result = self.docker.exec_command("dovecot-mailcow", permissions_command)
                        if permissions_result.get("status") != "success":
                            print(f"Error setting permissions for {output_file}: {permissions_result.get('output')}\n")

        except Exception as e:
            print(f"Error during encryption: {e}")

        return "Done"

    def listDeletedMaildirs(self, source_dir="/var/vmail/_garbage"):
        """
        List deleted maildirs in the specified garbage directory.
        :param source_dir: Directory to search for deleted maildirs.
        :return: List of maildirs.
        """
        list_command = ["bash", "-c", f"ls -la {source_dir}"]

        try:
            result = self.docker.exec_command("dovecot-mailcow", list_command)
            if result.get("status") != "success":
                print(f"Error listing deleted maildirs: {result.get('output')}")
                return []

            lines = result.get("output", "").splitlines()
            maildirs = {}

            for idx, line in enumerate(lines):
                parts = line.split()
                if "_" in line:
                    folder_name = parts[-1]
                    time, maildir = folder_name.split("_", 1)

                    if maildir.endswith("_index"):
                        main_item = maildir[:-6]
                        if main_item in maildirs:
                            maildirs[main_item]["has_index"] = True
                    else:
                        maildirs[maildir] = {"item": idx, "time": time, "name": maildir, "has_index": False}

            return list(maildirs.values())

        except Exception as e:
            print(f"Error during listing deleted maildirs: {e}")
            return []

    def restoreMaildir(self, username, item, source_dir="/var/vmail/_garbage"):
        """
        Restore a maildir item for a specific user from the deleted maildirs.
        :param username: Username to restore the item to.
        :param item: Item to restore (e.g., mailbox, folder).
        :param source_dir: Directory containing deleted maildirs.
        :return: Response from Dovecot.
        """
        username_splitted = username.split("@")
        maildirs = self.listDeletedMaildirs()

        maildir = None
        for mdir in maildirs:
            if mdir["item"] == int(item):
                maildir = mdir
                break
        if not maildir:
            return {"status": "error", "message": "Maildir not found."}

        restore_command = f"mv {source_dir}/{maildir['time']}_{maildir['name']} /var/vmail/{username_splitted[1]}/{username_splitted[0]}"
        restore_index_command = f"mv {source_dir}/{maildir['time']}_{maildir['name']}_index /var/vmail_index/{username}"

        result = self.docker.exec_command("dovecot-mailcow", ["bash", "-c", restore_command])
        if result.get("status") != "success":
            return {"status": "error", "message": "Failed to restore maildir."}

        result = self.docker.exec_command("dovecot-mailcow", ["bash", "-c", restore_index_command])
        if result.get("status") != "success":
            return {"status": "error", "message": "Failed to restore maildir index."}

        return "Done"

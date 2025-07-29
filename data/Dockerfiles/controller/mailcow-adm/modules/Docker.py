import docker
from docker.errors import APIError

class Docker:
    def __init__(self):
        self.client = docker.from_env()

    def exec_command(self, container_name, cmd, user=None):
        """
        Execute a command in a container by its container name.
        :param container_name: The name of the container.
        :param cmd: The command to execute as a list (e.g., ["ls", "-la"]).
        :param user: The user to execute the command as (optional).
        :return: A standardized response with status, output, and exit_code.
        """

        filters = {"name": container_name}

        try:
            for container in self.client.containers.list(filters=filters):
                exec_result = container.exec_run(cmd, user=user)
                return {
                    "status": "success",
                    "exit_code": exec_result.exit_code,
                    "output": exec_result.output.decode("utf-8")
                }
        except APIError as e:
            return {
                "status": "error",
                "exit_code": "APIError",
                "output": str(e)
            }
        except Exception as e:
            return {
                "status": "error",
                "exit_code": "Exception",
                "output": str(e)
            }

    def start_container(self, container_name):
        """
        Start a container by its container name.
        :param container_name: The name of the container.
        :return: A standardized response with status, output, and exit_code.
        """

        filters = {"name": container_name}

        try:
            for container in self.client.containers.list(filters=filters):
                container.start()
                return {
                    "status": "success",
                    "exit_code": "0",
                    "output": f"Container '{container_name}' started successfully."
                }
        except APIError as e:
            return {
                "status": "error",
                "exit_code": "APIError",
                "output": str(e)
            }
        except Exception as e:
            return {
                "status": "error",
                "error_type": "Exception",
                "output": str(e)
            }

    def stop_container(self, container_name):
        """
        Stop a container by its container name.
        :param container_name: The name of the container.
        :return: A standardized response with status, output, and exit_code.
        """

        filters = {"name": container_name}

        try:
            for container in self.client.containers.list(filters=filters):
                container.stop()
                return {
                    "status": "success",
                    "exit_code": "0",
                    "output": f"Container '{container_name}' stopped successfully."
                }
        except APIError as e:
            return {
                "status": "error",
                "exit_code": "APIError",
                "output": str(e)
            }
        except Exception as e:
            return {
                "status": "error",
                "exit_code": "Exception",
                "output": str(e)
            }

    def restart_container(self, container_name):
        """
        Restart a container by its container name.
        :param container_name: The name of the container.
        :return: A standardized response with status, output, and exit_code.
        """

        filters = {"name": container_name}

        try:
            for container in self.client.containers.list(filters=filters):
                container.restart()
                return {
                    "status": "success",
                    "exit_code": "0",
                    "output": f"Container '{container_name}' restarted successfully."
                }
        except APIError as e:
            return {
                "status": "error",
                "exit_code": "APIError",
                "output": str(e)
            }
        except Exception as e:
            return {
                "status": "error",
                "exit_code": "Exception",
                "output": str(e)
            }

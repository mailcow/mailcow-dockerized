import os
import sys

def main():
  container_name = os.getenv("CONTAINER_NAME")

  if container_name == "sogo-mailcow":
    from modules.BootstrapSogo import Bootstrap
  elif container_name == "nginx-mailcow":
    from modules.BootstrapNginx import Bootstrap
  elif container_name == "postfix-mailcow":
    from modules.BootstrapPostfix import Bootstrap
  else:
    print(f"No bootstrap handler for container: {container_name}", file=sys.stderr)
    sys.exit(1)

  b = Bootstrap(
    container=container_name,
    db_config = {
      "host": "localhost",
      "user": os.getenv("DBUSER"),
      "password": os.getenv("DBPASS"),
      "database": os.getenv("DBNAME"),
      "unix_socket": "/var/run/mysqld/mysqld.sock",
      'connection_timeout': 2
    },
    db_table="service_settings",
    db_settings=['sogo']
  )

  b.bootstrap()

if __name__ == "__main__":
  main()

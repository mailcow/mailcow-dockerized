import os
import sys
import signal

def handle_sigterm(signum, frame):
  print("Received SIGTERM, exiting gracefully...")
  sys.exit(0)

def main():
  signal.signal(signal.SIGTERM, handle_sigterm)

  container_name = os.getenv("CONTAINER_NAME")

  if container_name == "sogo-mailcow":
    from modules.BootstrapSogo import Bootstrap
  elif container_name == "nginx-mailcow":
    from modules.BootstrapNginx import Bootstrap
  elif container_name == "postfix-mailcow":
    from modules.BootstrapPostfix import Bootstrap
  elif container_name == "dovecot-mailcow":
    from modules.BootstrapDovecot import Bootstrap
  elif container_name == "rspamd-mailcow":
    from modules.BootstrapRspamd import Bootstrap
  elif container_name == "clamd-mailcow":
    from modules.BootstrapClamd import Bootstrap
  elif container_name == "mysql-mailcow":
    from modules.BootstrapMysql import Bootstrap
  else:
    print(f"No bootstrap handler for container: {container_name}", file=sys.stderr)
    sys.exit(1)

  b = Bootstrap(
    container=container_name,
    db_config={
      "host": "localhost",
      "user": os.getenv("DBUSER") or os.getenv("MYSQL_USER"),
      "password": os.getenv("DBPASS") or os.getenv("MYSQL_PASSWORD"),
      "database": os.getenv("DBNAME") or os.getenv("MYSQL_DATABASE"),
      "unix_socket": "/var/run/mysqld/mysqld.sock",
      'connection_timeout': 2
    },
    db_table="service_settings",
    db_settings=['sogo'],
    redis_config={
      "host": os.getenv("REDIS_SLAVEOF_IP") or "redis-mailcow",
      "port": int(os.getenv("REDIS_SLAVEOF_PORT") or 6379),
      "password": os.getenv("REDISPASS"),
      "db": 0
    }
  )

  b.bootstrap()

if __name__ == "__main__":
  main()

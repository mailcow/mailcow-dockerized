import os
import sys
import signal

def handle_sigterm(signum, frame):
  print("Received SIGTERM, exiting gracefully...")
  sys.exit(0)

def main():
  signal.signal(signal.SIGTERM, handle_sigterm)

  container_name = os.getenv("CONTAINER_NAME")
  service_name = container_name.replace("-mailcow", "").replace("-", "")
  module_name = f"Bootstrap{service_name.capitalize()}"

  try:
    mod = __import__(f"modules.{module_name}", fromlist=[module_name])
    Bootstrap = getattr(mod, module_name)
  except (ImportError, AttributeError) as e:
    print(f"Failed to load bootstrap module for: {container_name} → {module_name}")
    print(str(e))
    sys.exit(1)

  b = Bootstrap(
    container=container_name,
    service=service_name,
    db_config={
      "host": "localhost",
      "user": os.getenv("DBUSER") or os.getenv("MYSQL_USER"),
      "password": os.getenv("DBPASS") or os.getenv("MYSQL_PASSWORD"),
      "database": os.getenv("DBNAME") or os.getenv("MYSQL_DATABASE"),
      "unix_socket": "/var/run/mysqld/mysqld.sock",
      'connection_timeout': 2,
      'service_table': "service_settings",
      'service_types': [service_name]
    },
    redis_config={
      "read_host": "redis-mailcow",
      "read_port": 6379,
      "write_host": os.getenv("REDIS_SLAVEOF_IP") or "redis-mailcow",
      "write_port": int(os.getenv("REDIS_SLAVEOF_PORT") or 6379),
      "password": os.getenv("REDISPASS"),
      "db": 0
    }
  )

  b.bootstrap()

if __name__ == "__main__":
  main()

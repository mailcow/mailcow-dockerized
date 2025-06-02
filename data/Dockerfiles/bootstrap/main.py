import os
import sys
import signal
import ipaddress

def handle_sigterm(signum, frame):
  print("Received SIGTERM, exiting gracefully...")
  sys.exit(0)

def get_mysql_config(service_name):
  db_config = {
    "user": os.getenv("DBUSER") or os.getenv("MYSQL_USER"),
    "password": os.getenv("DBPASS") or os.getenv("MYSQL_PASSWORD"),
    "database": os.getenv("DBNAME") or os.getenv("MYSQL_DATABASE"),
    "connection_timeout": 2,
    "service_table": "service_settings",
    "service_types": [service_name]
  }

  db_host = os.getenv("DB_HOST")
  if db_host.startswith("/"):
    db_config["host"] = "localhost"
    db_config["unix_socket"] = db_host
  else:
    db_config["host"] = db_host

  return db_config

def get_redis_config():
  redis_config = {
    "read_host": os.getenv("REDIS_HOST"),
    "read_port": 6379,
    "write_host": os.getenv("REDIS_SLAVEOF_IP") or os.getenv("REDIS_HOST"),
    "write_port": int(os.getenv("REDIS_SLAVEOF_PORT") or 6379),
    "password": os.getenv("REDISPASS"),
    "db": 0
  }

  return redis_config

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
    db_config=get_mysql_config(service_name),
    redis_config=get_redis_config()
  )

  b.bootstrap()

if __name__ == "__main__":
  main()
